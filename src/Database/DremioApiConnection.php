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

        $http = $this->buildHttp();

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
     * Submit SQL, wait for job completion, then fetch and return result rows.
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        $query = $this->applyBindings($query, $bindings);

        $jobId = $this->submitSql($query);
        $this->waitForJob($jobId);
        $rows = $this->fetchJobResults($jobId);

        if ($this->caseOption === 'lower') {
            $rows = array_map(fn($row) => array_change_key_case($row, CASE_LOWER), $rows);
        } elseif ($this->caseOption === 'upper') {
            $rows = array_map(fn($row) => array_change_key_case($row, CASE_UPPER), $rows);
        }

        return array_map(fn($row) => (object) $row, $rows);
    }

    /**
     * Submit SQL and wait for completion (DDL / DML without result set).
     */
    public function statement($query, $bindings = [])
    {
        $query = $this->applyBindings($query, $bindings);

        $jobId = $this->submitSql($query);
        $this->waitForJob($jobId);

        return true;
    }

    /**
     * Submit SQL, wait for completion, return affected row count.
     */
    public function affectingStatement($query, $bindings = [])
    {
        $query = $this->applyBindings($query, $bindings);

        $jobId = $this->submitSql($query);
        $job = $this->waitForJob($jobId);

        return (int) ($job['rowCount'] ?? 0);
    }

    /**
     * Submit a SQL query to Dremio and return the job ID.
     */
    protected function submitSql(string $sql): string
    {
        $payload = ['sql' => $sql];
        if (!empty($this->apiConfig['api_context'])) {
            $payload['context'] = $this->apiConfig['api_context'];
        }

        $endpoint = $this->apiConfig['api_sql_endpoint'] ?? '/api/v3/sql';
        $response = $this->request('POST', $endpoint, $payload);

        if (empty($response['id'])) {
            throw new \Exception('Dremio API did not return a job ID.');
        }

        return $response['id'];
    }

    /**
     * Poll the job status until it reaches a terminal state.
     * Returns the final job status response.
     */
    protected function waitForJob(string $jobId): array
    {
        $pollInterval = (int) ($this->apiConfig['api_poll_interval'] ?? 500); // ms
        $maxWait = (int) ($this->apiConfig['api_timeout'] ?? 30);
        $elapsed = 0;

        while (true) {
            $job = $this->request('GET', "/api/v3/job/{$jobId}");
            $state = $job['jobState'] ?? 'UNKNOWN';

            if ($state === 'COMPLETED') {
                return $job;
            }

            if (in_array($state, ['FAILED', 'CANCELED', 'CANCELLED'], true)) {
                $errorMsg = $job['errorMessage'] ?? $job['failureInfo']['message'] ?? ('Job ' . $state);
                throw new \Exception('Dremio job failed: ' . $errorMsg);
            }

            // States: ENQUEUED, STARTING, RUNNING, etc. — keep polling
            $sleepSeconds = $pollInterval / 1000;
            $elapsed += $sleepSeconds;

            if ($elapsed >= $maxWait) {
                throw new \Exception("Dremio job {$jobId} timed out after {$maxWait}s (state: {$state}).");
            }

            usleep($pollInterval * 1000);
        }
    }

    /**
     * Fetch all result rows for a completed job, handling pagination.
     */
    protected function fetchJobResults(string $jobId): array
    {
        $offset = 0;
        $limit = (int) ($this->apiConfig['api_results_limit'] ?? 500);
        $allRows = [];

        while (true) {
            $response = $this->request('GET', "/api/v3/job/{$jobId}/results?offset={$offset}&limit={$limit}");

            $rows = $response['rows'] ?? [];
            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $allRows[] = $row;
            }

            if (count($rows) < $limit) {
                break;
            }

            $offset += $limit;
        }

        return $allRows;
    }

    /**
     * Build an HTTP client instance with common settings.
     */
    protected function buildHttp()
    {
        $http = Http::acceptJson()
            ->timeout((int) ($this->apiConfig['api_timeout'] ?? 30));

        if (!($this->apiConfig['api_verify_ssl'] ?? true)) {
            $http = $http->withoutVerifying();
        }

        return $http;
    }

    /**
     * Make an authenticated request to Dremio REST API.
     */
    protected function request(string $method, string $endpoint, array $payload = []): array
    {
        $url = rtrim($this->apiConfig['api_base_url'], '/') . '/' . ltrim($endpoint, '/');

        $http = $this->buildHttp();

        $token = $this->resolveToken();
        if (!empty($token)) {
            $http = $http->withHeaders([
                'Authorization' => '_dremio' . $token,
            ]);
        }

        $options = strtoupper($method) === 'GET' ? [] : ['json' => $payload];
        $response = $http->send(strtoupper($method), $url, $options);

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
