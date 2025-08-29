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

    /**
     * Run a select statement and return results as array
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        $query = $this->applyBindings($query, $bindings);

        $result = odbc_exec($this->odbc, $query);
        if (!$result) {
            throw new \Exception("ODBC Error: " . odbc_errormsg($this->odbc));
        }

        $rows = [];

        // cek apakah fungsi odbc_fetch_all ada
        
        if (function_exists('odbc_fetch_all')) {
            odbc_fetch_all($result, $rows);
        } else {
            $rows = $this->fetchAllCustom($result);
        }

        // apply case option
        if ($this->caseOption === 'lower') {
            $rows = array_map(fn($row) => array_change_key_case($row, CASE_LOWER), $rows);
        } elseif ($this->caseOption === 'upper') {
            $rows = array_map(fn($row) => array_change_key_case($row, CASE_UPPER), $rows);
        }

        return $rows;
    }

    /**
     * Run a general statement (DDL / DML without result set)
     */
    public function statement($query, $bindings = [])
    {
        $query = $this->applyBindings($query, $bindings);

        $result = odbc_exec($this->odbc, $query);
        if (!$result) {
            throw new \Exception("ODBC Error: " . odbc_errormsg($this->odbc));
        }

        return true;
    }

    /**
     * Run a statement that affects rows (UPDATE / DELETE / INSERT)
     */
    public function affectingStatement($query, $bindings = [])
    {
        $query = $this->applyBindings($query, $bindings);

        $result = odbc_exec($this->odbc, $query);
        if (!$result) {
            throw new \Exception("ODBC Error: " . odbc_errormsg($this->odbc));
        }

        $count = odbc_num_rows($result);
        return $count >= 0 ? $count : 0;
    }

    /**
     * Custom fetchAll replacement if odbc_fetch_all not available
     */
    protected function fetchAllCustom($result)
    {
        $rows = [];
        $cols = odbc_num_fields($result);

        while (odbc_fetch_row($result)) {
            $row = [];
            for ($i = 1; $i <= $cols; $i++) {
                $fieldName = odbc_field_name($result, $i);
                $row[$fieldName] = odbc_result($result, $i);
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Apply bindings into query string
     */
    protected function applyBindings($query, array $bindings)
    {
        if (empty($bindings)) {
            return $query;
        }

        $bindings = array_map(function ($value) {
            return is_numeric($value)
                ? $value
                : "'" . str_replace("'", "''", $value) . "'";
        }, $bindings);

        return vsprintf(str_replace('?', '%s', $query), $bindings);
    }
}
