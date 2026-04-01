<?php

namespace AviaAvian\DremioOdbc\Tests;

use AviaAvian\DremioOdbc\Database\DremioApiConnection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

class DremioApiConnectionTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [\AviaAvian\DremioOdbc\Providers\OdbcServiceProvider::class];
    }

    private function makeConnection(array $overrides = []): DremioApiConnection
    {
        $config = array_merge([
            'api_base_url' => 'https://dremio.test',
            'api_token' => 'test-token',
            'api_sql_endpoint' => '/api/v3/sql',
            'api_timeout' => 30,
            'api_verify_ssl' => true,
            'api_context' => null,
            'case' => 'original',
        ], $overrides);

        return new DremioApiConnection($config, 'testdb', '', $config);
    }

    public function test_select_returns_rows_from_rows_key()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response([
                'rows' => [
                    ['ID' => 1, 'NAME' => 'Alice'],
                    ['ID' => 2, 'NAME' => 'Bob'],
                ],
            ]),
        ]);

        $conn = $this->makeConnection();
        $results = $conn->select('SELECT * FROM users');

        $this->assertCount(2, $results);
        $this->assertEquals('Alice', $results[0]['NAME']);
        $this->assertEquals('Bob', $results[1]['NAME']);
    }

    public function test_select_returns_rows_from_data_key()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response([
                'data' => [
                    ['id' => 10],
                ],
            ]),
        ]);

        $conn = $this->makeConnection();
        $results = $conn->select('SELECT id FROM t');

        $this->assertCount(1, $results);
        $this->assertEquals(10, $results[0]['id']);
    }

    public function test_select_returns_rows_from_results_key()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response([
                'results' => [['x' => 1]],
            ]),
        ]);

        $conn = $this->makeConnection();
        $results = $conn->select('SELECT x FROM t');

        $this->assertCount(1, $results);
    }

    public function test_select_returns_rows_from_result_key()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response([
                'result' => [['y' => 2]],
            ]),
        ]);

        $conn = $this->makeConnection();
        $results = $conn->select('SELECT y FROM t');

        $this->assertCount(1, $results);
    }

    public function test_select_returns_empty_when_no_known_key()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response([
                'something_else' => 'value',
            ]),
        ]);

        $conn = $this->makeConnection();
        $results = $conn->select('SELECT 1');

        $this->assertCount(0, $results);
    }

    public function test_select_applies_lower_case_option()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response([
                'rows' => [['NAME' => 'Alice', 'AGE' => 30]],
            ]),
        ]);

        $conn = $this->makeConnection(['case' => 'lower']);
        $results = $conn->select('SELECT * FROM users');

        $this->assertArrayHasKey('name', $results[0]);
        $this->assertArrayHasKey('age', $results[0]);
    }

    public function test_select_applies_upper_case_option()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response([
                'rows' => [['name' => 'Alice']],
            ]),
        ]);

        $conn = $this->makeConnection(['case' => 'upper']);
        $results = $conn->select('SELECT * FROM users');

        $this->assertArrayHasKey('NAME', $results[0]);
    }

    public function test_select_keeps_original_case_by_default()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response([
                'rows' => [['MixedCase' => 'val']],
            ]),
        ]);

        $conn = $this->makeConnection();
        $results = $conn->select('SELECT * FROM t');

        $this->assertArrayHasKey('MixedCase', $results[0]);
    }

    public function test_select_sends_correct_payload()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['rows' => []]),
        ]);

        $conn = $this->makeConnection();
        $conn->select('SELECT * FROM users WHERE id = ?', [42]);

        Http::assertSent(function (Request $request) {
            $body = $request->data();
            return $request->url() === 'https://dremio.test/api/v3/sql'
                && $body['sql'] === 'SELECT * FROM users WHERE id = 42'
                && !isset($body['context']);
        });
    }

    public function test_select_includes_context_when_configured()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['rows' => []]),
        ]);

        $conn = $this->makeConnection([
            'api_context' => ['mySpace', 'myFolder'],
        ]);
        $conn->select('SELECT 1');

        Http::assertSent(function (Request $request) {
            $body = $request->data();
            return $body['context'] === ['mySpace', 'myFolder'];
        });
    }

    public function test_select_sends_bearer_token()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['rows' => []]),
        ]);

        $conn = $this->makeConnection(['api_token' => 'my-secret-token']);
        $conn->select('SELECT 1');

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bearer my-secret-token');
        });
    }

    public function test_statement_returns_true_on_success()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['ok' => true]),
        ]);

        $conn = $this->makeConnection();
        $result = $conn->statement('CREATE TABLE test (id INT)');

        $this->assertTrue($result);
    }

    public function test_affecting_statement_returns_row_count()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['rowCount' => 5]),
        ]);

        $conn = $this->makeConnection();
        $count = $conn->affectingStatement('UPDATE users SET active = 1');

        $this->assertEquals(5, $count);
    }

    public function test_affecting_statement_returns_affected_rows()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['affectedRows' => 3]),
        ]);

        $conn = $this->makeConnection();
        $count = $conn->affectingStatement('DELETE FROM users WHERE active = 0');

        $this->assertEquals(3, $count);
    }

    public function test_affecting_statement_returns_zero_when_no_count()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['ok' => true]),
        ]);

        $conn = $this->makeConnection();
        $count = $conn->affectingStatement('INSERT INTO t VALUES (1)');

        $this->assertEquals(0, $count);
    }

    public function test_request_throws_on_api_error()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response([
                'errorMessage' => 'Table not found',
            ], 400),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Dremio API error: Table not found');

        $conn = $this->makeConnection();
        $conn->select('SELECT * FROM nonexistent');
    }

    public function test_request_throws_on_server_error_with_message_key()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response([
                'message' => 'Internal server error',
            ], 500),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Dremio API error: Internal server error');

        $conn = $this->makeConnection();
        $conn->select('SELECT 1');
    }

    public function test_request_uses_custom_sql_endpoint()
    {
        Http::fake([
            'dremio.test/api/v4/query' => Http::response(['rows' => []]),
        ]);

        $conn = $this->makeConnection([
            'api_sql_endpoint' => '/api/v4/query',
        ]);
        $conn->select('SELECT 1');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://dremio.test/api/v4/query';
        });
    }

    public function test_bindings_with_string_values()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['rows' => []]),
        ]);

        $conn = $this->makeConnection();
        $conn->select("SELECT * FROM users WHERE name = ?", ["O'Brien"]);

        Http::assertSent(function (Request $request) {
            return str_contains($request->data()['sql'], "O''Brien");
        });
    }

    public function test_bindings_with_null_values()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['rows' => []]),
        ]);

        $conn = $this->makeConnection();
        $conn->select('SELECT * FROM users WHERE deleted_at = ?', [null]);

        Http::assertSent(function (Request $request) {
            return str_contains($request->data()['sql'], 'NULL');
        });
    }

    public function test_bindings_with_numeric_values()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['rows' => []]),
        ]);

        $conn = $this->makeConnection();
        $conn->select('SELECT * FROM users WHERE id = ? AND score > ?', [42, 3.14]);

        Http::assertSent(function (Request $request) {
            $sql = $request->data()['sql'];
            return str_contains($sql, '42') && str_contains($sql, '3.14');
        });
    }

    public function test_select_without_bindings()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['rows' => [['c' => 1]]]),
        ]);

        $conn = $this->makeConnection();
        $results = $conn->select('SELECT 1 AS c');

        Http::assertSent(function (Request $request) {
            return $request->data()['sql'] === 'SELECT 1 AS c';
        });

        $this->assertCount(1, $results);
    }
}
