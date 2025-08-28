<?php

namespace AviaAvian\DremioOdbc\Database;

use Illuminate\Database\Connection;

class DremioOdbcConnection extends Connection
{
    protected $odbc;
    protected $caseOption;

    public function __construct($odbc, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct(null, $database, $tablePrefix, $config);

        $this->odbc = $odbc;
        $this->caseOption = $config['case'] ?? 'original';
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        $query = $this->applyBindings($query, $bindings);

        $result = odbc_exec($this->odbc, $query);
        if (!$result) {
            throw new \Exception("ODBC Error: " . odbc_errormsg($this->odbc));
        }

        $rows = [];
        while ($row = odbc_fetch_array($result)) {
            if ($this->caseOption === 'lower') {
                $rows[] = array_change_key_case($row, CASE_LOWER);
            } elseif ($this->caseOption === 'upper') {
                $rows[] = array_change_key_case($row, CASE_UPPER);
            } else {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public function statement($query, $bindings = [])
    {
        $query = $this->applyBindings($query, $bindings);

        $result = odbc_exec($this->odbc, $query);
        if (!$result) {
            throw new \Exception("ODBC Error: " . odbc_errormsg($this->odbc));
        }

        return true;
    }

    public function affectingStatement($query, $bindings = [])
    {
        $this->statement($query, $bindings);
        return odbc_num_rows($this->odbc);
    }

    protected function applyBindings($query, array $bindings)
    {
        foreach ($bindings as $value) {
            $value = is_numeric($value) ? $value : "'" . addslashes($value) . "'";
            $query = preg_replace('/\?/', $value, $query, 1);
        }
        return $query;
    }
}
