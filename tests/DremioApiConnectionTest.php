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
            'api_poll_interval' => 10,
            'api_results_limit' => 500,
            'api_verify_ssl' => true,
            'api_context' => null,
            'case' => 'original',
        ], $overrides);

        return new DremioApiConnection($config, 'testdb', '', $config);
    }

    private function fakeAsyncFlow(array $rows = [], string $jobId = 'job-123'): void
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['id' => $jobId]),
            'dremio.test/api/v3/job/' . $jobId => Http::response([
                'jobState' => 'COMPLETED',
                'rowCount' => count($rows),
            ]),
            'dremio.test/api/v3/job/' . $jobId . '/results*' => Http::response([
                'rows' => $rows,
            ]),
        ]);
    }

    // --- select() ---

    public function test_select_submits_polls_and_fetches_results()
    {
        $this->fakeAsyncFlow([
            ['ID' => 1, 'NAME' => 'Alice'],
            ['ID' => 2, 'NAME' => 'Bob'],
        ]);

        $conn = $this->makeConnection();
        $results = $conn->select('SELECT * FROM users');

        $this->assertCount(2, $results);
        $this->assertEquals('Alice', $results[0]->NAME);

        Http::assertSent(fn(Request $r) => $r->url() === 'https://dremio.test/api/v3/sql');
        Http::assertSent(fn(Request $r) => str_contains($r->url(), '/api/v3/job/job-123'));
        Http::assertSent(fn(Request $r) => str_contains($r->url(), '/api/v3/job/job-123/results'));
    }

    public function test_select_sends_correct_sql_payload()
    {
        $this->fakeAsyncFlow([]);

        $conn = $this->makeConnection();
        $conn->select('SELECT * FROM users WHERE id = ?', [42]);

        Http::assertSent(function (Request $r) {
            if ($r->url() === 'https://dremio.test/api/v3/sql') {
                return $r->data()['sql'] === 'SELECT * FROM users WHERE id = 42'
                    && !isset($r->data()['context']);
            }
            return false;
        });
    }

    public function test_select_includes_context()
    {
        $this->fakeAsyncFlow([]);

        $conn = $this->makeConnection(['api_context' => ['mySpace', 'myFolder']]);
        $conn->select('SELECT 1');

        Http::assertSent(function (Request $r) {
            if ($r->url() === 'https://dremio.test/api/v3/sql') {
                return $r->data()['context'] === ['mySpace', 'myFolder'];
            }
            return false;
        });
    }

    public function test_select_applies_lower_case()
    {
        $this->fakeAsyncFlow([['NAME' => 'Alice', 'AGE' => 30]]);
        $results = $this->makeConnection(['case' => 'lower'])->select('SELECT *');
        $this->assertObjectHasProperty('name', $results[0]);
        $this->assertObjectHasProperty('age', $results[0]);
    }

    public function test_select_applies_upper_case()
    {
        $this->fakeAsyncFlow([['name' => 'Alice']]);
        $results = $this->makeConnection(['case' => 'upper'])->select('SELECT *');
        $this->assertObjectHasProperty('NAME', $results[0]);
    }

    public function test_select_keeps_original_case()
    {
        $this->fakeAsyncFlow([['MixedCase' => 'val']]);
        $results = $this->makeConnection()->select('SELECT *');
        $this->assertObjectHasProperty('MixedCase', $results[0]);
    }

    public function test_select_returns_empty_when_no_rows()
    {
        $this->fakeAsyncFlow([]);
        $results = $this->makeConnection()->select('SELECT 1');
        $this->assertCount(0, $results);
    }

    // --- statement() ---

    public function test_statement_returns_true()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['id' => 'job-stmt']),
            'dremio.test/api/v3/job/job-stmt' => Http::response(['jobState' => 'COMPLETED']),
        ]);

        $this->assertTrue($this->makeConnection()->statement('CREATE TABLE t (id INT)'));
    }

    // --- affectingStatement() ---

    public function test_affecting_statement_returns_row_count()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['id' => 'job-aff']),
            'dremio.test/api/v3/job/job-aff' => Http::response(['jobState' => 'COMPLETED', 'rowCount' => 5]),
        ]);

        $this->assertEquals(5, $this->makeConnection()->affectingStatement('UPDATE t SET x = 1'));
    }

    public function test_affecting_statement_returns_zero_when_no_count()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['id' => 'job-z']),
            'dremio.test/api/v3/job/job-z' => Http::response(['jobState' => 'COMPLETED']),
        ]);

        $this->assertEquals(0, $this->makeConnection()->affectingStatement('INSERT INTO t VALUES (1)'));
    }

    // --- Job polling ---

    public function test_poll_waits_through_running_states()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['id' => 'job-poll']),
            'dremio.test/api/v3/job/job-poll' => Http::sequence()
                ->push(['jobState' => 'RUNNING'])
                ->push(['jobState' => 'RUNNING'])
                ->push(['jobState' => 'COMPLETED']),
            'dremio.test/api/v3/job/job-poll/results*' => Http::response(['rows' => [['x' => 1]]]),
        ]);

        $results = $this->makeConnection(['api_poll_interval' => 10])->select('SELECT 1');
        $this->assertCount(1, $results);
    }

    public function test_poll_throws_on_failed_job()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['id' => 'job-fail']),
            'dremio.test/api/v3/job/job-fail' => Http::response([
                'jobState' => 'FAILED',
                'errorMessage' => 'Table not found',
            ]),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Dremio job failed: Table not found');
        $this->makeConnection()->select('SELECT *');
    }

    public function test_poll_throws_on_canceled_job()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['id' => 'job-c']),
            'dremio.test/api/v3/job/job-c' => Http::response(['jobState' => 'CANCELED']),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Dremio job failed');
        $this->makeConnection()->select('SELECT 1');
    }

    public function test_poll_throws_on_timeout()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['id' => 'job-slow']),
            'dremio.test/api/v3/job/job-slow' => Http::response(['jobState' => 'RUNNING']),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('timed out');
        $this->makeConnection(['api_timeout' => 0.05, 'api_poll_interval' => 10])->select('SELECT 1');
    }

    // --- Submit errors ---

    public function test_submit_throws_when_no_job_id()
    {
        Http::fake(['dremio.test/api/v3/sql' => Http::response(['x' => 'y'])]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('did not return a job ID');
        $this->makeConnection()->select('SELECT 1');
    }

    public function test_submit_throws_on_http_error()
    {
        Http::fake(['dremio.test/api/v3/sql' => Http::response(['errorMessage' => 'Syntax error'], 400)]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Dremio API error: Syntax error');
        $this->makeConnection()->select('SELECT ???');
    }

    // --- Pagination ---

    public function test_fetch_results_paginates()
    {
        Http::fake([
            'dremio.test/api/v3/sql' => Http::response(['id' => 'job-pg']),
            'dremio.test/api/v3/job/job-pg' => Http::response(['jobState' => 'COMPLETED']),
            'dremio.test/api/v3/job/job-pg/results*' => Http::sequence()
                ->push(['rows' => [['a' => 1], ['a' => 2]]])
                ->push(['rows' => [['a' => 3]]]),
        ]);

        $results = $this->makeConnection(['api_results_limit' => 2])->select('SELECT a');
        $this->assertCount(3, $results);
        $this->assertEquals(3, $results[2]->a);
    }

    // --- Auth header ---

    public function test_sends_dremio_auth_header()
    {
        $this->fakeAsyncFlow([]);
        $this->makeConnection(['api_token' => 'my-token'])->select('SELECT 1');
        Http::assertSent(fn(Request $r) => $r->hasHeader('Authorization', '_dremiomy-token'));
    }

    public function test_custom_sql_endpoint()
    {
        Http::fake([
            'dremio.test/api/v4/query' => Http::response(['id' => 'job-ep']),
            'dremio.test/api/v3/job/job-ep' => Http::response(['jobState' => 'COMPLETED']),
            'dremio.test/api/v3/job/job-ep/results*' => Http::response(['rows' => []]),
        ]);

        $this->makeConnection(['api_sql_endpoint' => '/api/v4/query'])->select('SELECT 1');
        Http::assertSent(fn(Request $r) => $r->url() === 'https://dremio.test/api/v4/query');
    }

    // --- Bindings ---

    public function test_string_bindings()
    {
        $this->fakeAsyncFlow([]);
        $this->makeConnection()->select("SELECT * FROM t WHERE name = ?", ["O'Brien"]);
        Http::assertSent(fn(Request $r) => $r->url() === 'https://dremio.test/api/v3/sql' && str_contains($r->data()['sql'], "O''Brien"));
    }

    public function test_null_bindings()
    {
        $this->fakeAsyncFlow([]);
        $this->makeConnection()->select('SELECT * FROM t WHERE x = ?', [null]);
        Http::assertSent(fn(Request $r) => $r->url() === 'https://dremio.test/api/v3/sql' && str_contains($r->data()['sql'], 'NULL'));
    }

    public function test_numeric_bindings()
    {
        $this->fakeAsyncFlow([]);
        $this->makeConnection()->select('SELECT * FROM t WHERE id = ? AND s > ?', [42, 3.14]);
        Http::assertSent(function (Request $r) {
            if ($r->url() !== 'https://dremio.test/api/v3/sql') return false;
            $sql = $r->data()['sql'];
            return str_contains($sql, '42') && str_contains($sql, '3.14');
        });
    }

    public function test_no_bindings()
    {
        $this->fakeAsyncFlow([['c' => 1]]);
        $results = $this->makeConnection()->select('SELECT 1 AS c');
        Http::assertSent(fn(Request $r) => $r->url() === 'https://dremio.test/api/v3/sql' && $r->data()['sql'] === 'SELECT 1 AS c');
        $this->assertCount(1, $results);
    }

    // --- Auto-login ---

    public function test_auto_login_with_username_password()
    {
        Http::fake([
            'dremio.test/apiv2/login' => Http::response(['token' => 'auto-tok']),
            'dremio.test/api/v3/sql' => Http::response(['id' => 'job-al']),
            'dremio.test/api/v3/job/job-al' => Http::response(['jobState' => 'COMPLETED']),
            'dremio.test/api/v3/job/job-al/results*' => Http::response(['rows' => [['id' => 1]]]),
        ]);

        $conn = $this->makeConnection(['api_token' => '', 'api_username' => 'myuser', 'api_password' => 'mypass']);
        $results = $conn->select('SELECT 1');

        Http::assertSent(function (Request $r) {
            if ($r->url() !== 'https://dremio.test/apiv2/login') return false;
            return $r->data()['userName'] === 'myuser' && $r->data()['password'] === 'mypass';
        });
        Http::assertSent(fn(Request $r) => $r->url() === 'https://dremio.test/api/v3/sql' && $r->hasHeader('Authorization', '_dremioauto-tok'));
        $this->assertCount(1, $results);
    }

    public function test_auto_login_caches_token()
    {
        Http::fake([
            'dremio.test/apiv2/login' => Http::response(['token' => 'cached']),
            'dremio.test/api/v3/sql' => Http::response(['id' => 'job-cc']),
            'dremio.test/api/v3/job/job-cc' => Http::response(['jobState' => 'COMPLETED']),
            'dremio.test/api/v3/job/job-cc/results*' => Http::response(['rows' => []]),
        ]);

        $conn = $this->makeConnection(['api_token' => '', 'api_username' => 'u', 'api_password' => 'p']);
        $conn->select('SELECT 1');
        $conn->select('SELECT 2');

        $logins = Http::recorded(fn(Request $r) => $r->url() === 'https://dremio.test/apiv2/login');
        $this->assertCount(1, $logins);
    }

    public function test_auto_login_throws_on_failed_login()
    {
        Http::fake(['dremio.test/apiv2/login' => Http::response(['errorMessage' => 'Bad creds'], 401)]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Dremio API login failed: Bad creds');
        $this->makeConnection(['api_token' => '', 'api_username' => 'x', 'api_password' => 'y'])->select('SELECT 1');
    }

    public function test_auto_login_throws_when_no_token()
    {
        Http::fake(['dremio.test/apiv2/login' => Http::response(['ok' => true])]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('no token returned');
        $this->makeConnection(['api_token' => '', 'api_username' => 'u', 'api_password' => 'p'])->select('SELECT 1');
    }

    public function test_custom_login_endpoint()
    {
        Http::fake([
            'dremio.test/custom/login' => Http::response(['token' => 'tok']),
            'dremio.test/api/v3/sql' => Http::response(['id' => 'job-cl']),
            'dremio.test/api/v3/job/job-cl' => Http::response(['jobState' => 'COMPLETED']),
            'dremio.test/api/v3/job/job-cl/results*' => Http::response(['rows' => []]),
        ]);

        $this->makeConnection(['api_token' => '', 'api_username' => 'u', 'api_password' => 'p', 'api_login_endpoint' => '/custom/login'])->select('SELECT 1');
        Http::assertSent(fn(Request $r) => $r->url() === 'https://dremio.test/custom/login');
    }

    public function test_token_takes_priority_over_credentials()
    {
        $this->fakeAsyncFlow([]);
        $this->makeConnection(['api_token' => 'explicit', 'api_username' => 'u', 'api_password' => 'p'])->select('SELECT 1');
        Http::assertNotSent(fn(Request $r) => str_contains($r->url(), 'login'));
        Http::assertSent(fn(Request $r) => $r->hasHeader('Authorization', '_dremioexplicit'));
    }
}
