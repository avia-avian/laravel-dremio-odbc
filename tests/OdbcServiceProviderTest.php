<?php

namespace AviaAvian\DremioOdbc\Tests;

use AviaAvian\DremioOdbc\Database\DremioApiConnection;
use AviaAvian\DremioOdbc\Providers\OdbcServiceProvider;
use Orchestra\Testbench\TestCase;

class OdbcServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [OdbcServiceProvider::class];
    }

    public function test_config_is_merged()
    {
        $config = $this->app['config']->get('dremio_odbc');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('connection', $config);
        $this->assertArrayHasKey('driver', $config);
        $this->assertArrayHasKey('api_base_url', $config);
        $this->assertArrayHasKey('api_token', $config);
        $this->assertArrayHasKey('api_sql_endpoint', $config);
        $this->assertArrayHasKey('api_timeout', $config);
        $this->assertArrayHasKey('api_verify_ssl', $config);
        $this->assertArrayHasKey('api_context', $config);
    }

    public function test_config_default_connection_is_odbc()
    {
        $this->assertEquals('odbc', $this->app['config']->get('dremio_odbc.connection'));
    }

    public function test_config_default_driver_is_dremio()
    {
        $this->assertEquals('dremio', $this->app['config']->get('dremio_odbc.driver'));
    }

    public function test_dremio_driver_is_registered()
    {
        // The 'dremio' driver should be extended on the DatabaseManager.
        // We verify by checking it doesn't throw "unsupported driver" but
        // throws the expected missing-config error instead.
        $this->app['config']->set('database.connections.dremio_test', [
            'driver' => 'dremio',
            'connection' => 'api',
            'api_base_url' => 'https://dremio.test',
            'api_token' => 'tok',
            'api_sql_endpoint' => '/api/v3/sql',
            'api_timeout' => 10,
            'api_verify_ssl' => false,
            'api_context' => null,
            'case' => 'original',
        ]);

        $conn = $this->app['db']->connection('dremio_test');

        $this->assertInstanceOf(DremioApiConnection::class, $conn);
    }

    public function test_create_api_connection_throws_without_base_url()
    {
        $this->app['config']->set('database.connections.dremio_no_url', [
            'driver' => 'dremio',
            'connection' => 'api',
            'api_base_url' => '',
            'api_token' => '',
            'api_sql_endpoint' => '/api/v3/sql',
            'api_timeout' => 30,
            'api_verify_ssl' => true,
            'api_context' => null,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DREMIO API base URL is required');

        $this->app['db']->connection('dremio_no_url');
    }

    public function test_api_context_json_string_is_decoded()
    {
        $this->app['config']->set('database.connections.dremio_ctx', [
            'driver' => 'dremio',
            'connection' => 'api',
            'api_base_url' => 'https://dremio.test',
            'api_token' => 'tok',
            'api_sql_endpoint' => '/api/v3/sql',
            'api_timeout' => 10,
            'api_verify_ssl' => false,
            'api_context' => '["mySpace","myFolder"]',
            'case' => 'original',
        ]);

        $conn = $this->app['db']->connection('dremio_ctx');

        $this->assertInstanceOf(DremioApiConnection::class, $conn);

        $ref = new \ReflectionProperty($conn, 'apiConfig');
        $ref->setAccessible(true);
        $apiConfig = $ref->getValue($conn);

        $this->assertEquals(['mySpace', 'myFolder'], $apiConfig['api_context']);
    }

    public function test_api_context_null_stays_null()
    {
        $this->app['config']->set('database.connections.dremio_noctx', [
            'driver' => 'dremio',
            'connection' => 'api',
            'api_base_url' => 'https://dremio.test',
            'api_token' => 'tok',
            'api_sql_endpoint' => '/api/v3/sql',
            'api_timeout' => 10,
            'api_verify_ssl' => true,
            'api_context' => null,
            'case' => 'original',
        ]);

        $conn = $this->app['db']->connection('dremio_noctx');

        $ref = new \ReflectionProperty($conn, 'apiConfig');
        $ref->setAccessible(true);
        $apiConfig = $ref->getValue($conn);

        $this->assertNull($apiConfig['api_context']);
    }
}
