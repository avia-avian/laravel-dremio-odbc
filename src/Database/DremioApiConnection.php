<?php

namespace AviaAvian\DremioOdbc\Database;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Http;

class DremioApiConnection extends Connection
{
    protected array $apiConfig;
    protected string $caseOption;
    protected ?string $cachedToken = null;

    public function __construct(array $apiConfig, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct(null, $database, $tablePrefix, $config);

        $this->apiConfig = $apiConfig;
        $this->caseOption = $config['case'] ?? 'original';
    }

    /**
     * Get the API token, logging in with username/password if needed.
     */
    protected function resolveToken(): ?string
    {
        if ($this->cachedToken !== null) {
            return $this->cachedToken;
        }

        if (!empty($this->apiConfig['api_token'])) {
            $this->cachedToken = $this->apiConfig['api_token'];
            return $this->cachedToken;
        }

        $username = $this->apiConfig['api_username'] ?? '';
        $password = $this->apiConfig['api_password'] ?? '';

        if ($username !== '' && $password !== '') {
            $this->cachedToken = $this->login($username, $password);
            return $this->cachedToken;
        }

        return null;
    }

    /**
     * Login to Dremio API with username/password and return the token.
     */
    protected function login(string $username, string $password): string
    {
        $loginEndpoint = $this->apiConfig['api_login_endpoint'] ?? '/apiv2/login';
        $url = rtrim($this->apiConfig['api_base_url'], '/') . '/' . ltrim($loginEndpoint, '/');

        $http = Http::acceptJson()
            ->timeout((int) ($this->apiConfig['api_timeout'] ?? 30));

        if (!($this->apiConfig['api_verify_ssl'] ?? true)) {
            $http = $http->withoutVerifying();
        }

        $response = $http->post($url, [
            'userName' => $username,
            'password' => $password,
        ]);

        if ($response->failed()) {
            $body = $response->json() ?? [];
            $message = $body['errorMessage'] ?? $body['message'] ?? ('HTTP ' . $response->status());
            throw new \Exception('Dremio API login failed: ' . $message);
        }

        $token = $response->json('token');
        if (empty($token)) {
            throw new \Exception('Dremio API login succeeded but no token returned.');
        }

        return $token;
    }

    /**
     * Run a select statement and return results as array.
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        $query = $this->applyBindings($query, $bindings);

        $payload = ['sql' => $query];
        if (!empty($this->apiConfig['api_context'])) {
            $payload['context'] = $this->apiConfig['api_context'];
        }

        $response = $this->request('POST', $this->apiConfig['api_sql_endpoint'], $payload);

        $rows = $this->extractRows($response);

        if ($this->caseOption === 'lower') {
            $rows = array_map(fn($row) => array_change_key_case($row, CASE_LOWER), $rows);
        } elseif ($this->caseOption === 'upper') {
            $rows = array_map(fn($row) => array_change_key_case($row, CASE_UPPER), $rows);
        }

        return $rows;
    }

    /**
     * Run a general statement (DDL / DML without result set).
     */
    public function statement($query, $bindings = [])
    {
        $query = $this->applyBindings($query, $bindings);

        $payload = ['sql' => $query];
        if (!empty($this->apiConfig['api_context'])) {
            $payload['context'] = $this->apiConfig['api_context'];
        }

        $this->request('POST', $this->apiConfig['api_sql_endpoint'], $payload);

        return true;
    }

    /**
     * Run a statement that affects rows (UPDATE / DELETE / INSERT).
     */
    public function affectingStatement($query, $bindings = [])
    {
        $query = $this->applyBindings($query, $bindings);

        $payload = ['sql' => $query];
        if (!empty($this->apiConfig['api_context'])) {
            $payload['context'] = $this->apiConfig['api_context'];
        }

        $response = $this->request('POST', $this->apiConfig['api_sql_endpoint'], $payload);

        if (isset($response['rowCount']) && is_numeric($response['rowCount'])) {
            return (int) $response['rowCount'];
        }

        if (isset($response['affectedRows']) && is_numeric($response['affectedRows'])) {
            return (int) $response['affectedRows'];
        }

        return 0;
    }

    /**
     * Make request to Dremio REST API using Illuminate HTTP client.
     */
    protected function request(string $method, string $endpoint, array $payload = []): array
    {
        $url = rtrim($this->apiConfig['api_base_url'], '/') . '/' . ltrim($endpoint, '/');

        $http = Http::acceptJson()
            ->timeout((int) ($this->apiConfig['api_timeout'] ?? 30));

        if (!($this->apiConfig['api_verify_ssl'] ?? true)) {
            $http = $http->withoutVerifying();
        }

        $token = $this->resolveToken();
        if (!empty($token)) {
            $http = $http->withHeaders([
                'Authorization' => '_dremio' . $token,
            ]);
        }

        $response = $http->send(strtoupper($method), $url, ['json' => $payload]);

        if ($response->failed()) {
            $body = $response->json() ?? [];
            $message = $body['errorMessage'] ?? $body['message'] ?? ('HTTP ' . $response->status());
            throw new \Exception('Dremio API error: ' . $message);
        }

        $decoded = $response->json();
        if (!is_array($decoded)) {
            throw new \Exception('Invalid Dremio API response: ' . $response->body());
        }

        return $decoded;
    }

    /**
     * Normalize possible result payload shapes into row array.
     */
    protected function extractRows(array $response): array
    {
        if (isset($response['rows']) && is_array($response['rows'])) {
            return $response['rows'];
        }

        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        if (isset($response['results']) && is_array($response['results'])) {
            return $response['results'];
        }

        if (isset($response['result']) && is_array($response['result'])) {
            return $response['result'];
        }

        return [];
    }

    /**
     * Apply bindings into query string.
     */
    protected function applyBindings($query, array $bindings)
    {
        if (empty($bindings)) {
            return $query;
        }

        $bindings = array_map(function ($value) {
            if (is_null($value)) {
                return 'NULL';
            }

            return is_numeric($value)
                ? $value
                : "'" . str_replace("'", "''", (string) $value) . "'";
        }, $bindings);

        return vsprintf(str_replace('?', '%s', $query), $bindings);
    }
}
