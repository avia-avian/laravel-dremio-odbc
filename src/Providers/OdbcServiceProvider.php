<?php

namespace AviaAvian\DremioOdbc\Providers;

use Illuminate\Support\ServiceProvider;
use AviaAvian\DremioOdbc\Database\DremioApiConnection;
use AviaAvian\DremioOdbc\Database\DremioOdbcConnection;

class OdbcServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/dremio_odbc.php', 'dremio_odbc');

        $this->app['db']->extend('dremio', function ($config, $name) {
            $connectionType = strtolower($config['connection'] ?? 'odbc');

            if ($connectionType === 'api') {
                return $this->createApiConnection($config);
            }

            return $this->createOdbcConnection($config);
        });
    }

    protected function createOdbcConnection(array $config): DremioOdbcConnection
    {
        $driver = $config['dsn'] ?? 'Arrow Flight SQL ODBC Driver';
        $encryption = $config['encryption'] ?? 1;
        $disableCert = $config['disable_cert_verification'] ?? 1;

        $dsn = "Driver={$driver};" .
            "ConnectionType=Direct;" .
            "HOST={$config['host']};" .
            "PORT={$config['port']};" .
            "Encryption={$encryption};" .
            "DisableCertificateVerification={$disableCert};" .
            "AuthenticationType=Plain;" .
            "UID={$config['username']};" .
            "PWD={$config['password']};";

        $odbc = odbc_connect($dsn, '', '');
        if (!$odbc) {
            throw new \Exception("ODBC connect failed: " . odbc_errormsg());
        }

        return new DremioOdbcConnection($odbc, $config['database'] ?? '', $config['prefix'] ?? '', $config);
    }

    protected function createApiConnection(array $config): DremioApiConnection
    {
        $context = $config['api_context'] ?? null;
        if (is_string($context) && $context !== '') {
            $decoded = json_decode($context, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $config['api_context'] = $decoded;
            }
        }

        if (empty($config['api_base_url'])) {
            throw new \InvalidArgumentException('DREMIO API base URL is required when connection=api.');
        }

        return new DremioApiConnection($config, $config['database'] ?? '', $config['prefix'] ?? '', $config);
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/dremio_odbc.php' => $this->app->basePath('config/dremio_odbc.php'),
        ], 'config');
    }
}
