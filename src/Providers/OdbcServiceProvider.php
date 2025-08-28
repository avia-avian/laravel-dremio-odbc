<?php

namespace AviaAvian\DremioOdbc\Providers;

use Illuminate\Support\ServiceProvider;
use AviaAvian\DremioOdbc\Database\DremioOdbcConnection;

class OdbcServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/dremio.php', 'dremio');

        $this->app['db']->extend('dremio', function ($config, $name) {
            $driver = $config['driver'] ?? 'Arrow Flight SQL ODBC Driver';
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

            return new DremioOdbcConnection($odbc, $config['database'], $config['prefix'] ?? '', $config);
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/dremio.php' => config_path('dremio.php'),
        ], 'config');
    }
}
