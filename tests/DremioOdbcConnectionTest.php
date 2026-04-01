<?php

namespace AviaAvian\DremioOdbc\Tests;

use AviaAvian\DremioOdbc\Database\DremioOdbcConnection;
use PHPUnit\Framework\TestCase;

class DremioOdbcConnectionTest extends TestCase
{
    private function makeConnection(string $case = 'original'): DremioOdbcConnection
    {
        // Pass a dummy value; we won't call ODBC functions in these tests.
        return new DremioOdbcConnection('dummy_odbc', 'testdb', '', ['case' => $case]);
    }

    // --- applyBindings tests (via reflection) ---

    private function callApplyBindings(DremioOdbcConnection $conn, string $query, array $bindings): string
    {
        $ref = new \ReflectionMethod($conn, 'applyBindings');
        $ref->setAccessible(true);
        return $ref->invoke($conn, $query, $bindings);
    }

    public function test_apply_bindings_with_no_bindings()
    {
        $conn = $this->makeConnection();
        $result = $this->callApplyBindings($conn, 'SELECT * FROM users', []);
        $this->assertEquals('SELECT * FROM users', $result);
    }

    public function test_apply_bindings_with_numeric_values()
    {
        $conn = $this->makeConnection();
        $result = $this->callApplyBindings($conn, 'SELECT * FROM users WHERE id = ? AND age > ?', [42, 18]);
        $this->assertEquals('SELECT * FROM users WHERE id = 42 AND age > 18', $result);
    }

    public function test_apply_bindings_with_string_values()
    {
        $conn = $this->makeConnection();
        $result = $this->callApplyBindings($conn, "SELECT * FROM users WHERE name = ?", ['Alice']);
        $this->assertEquals("SELECT * FROM users WHERE name = 'Alice'", $result);
    }

    public function test_apply_bindings_escapes_single_quotes()
    {
        $conn = $this->makeConnection();
        $result = $this->callApplyBindings($conn, "SELECT * FROM users WHERE name = ?", ["O'Brien"]);
        $this->assertEquals("SELECT * FROM users WHERE name = 'O''Brien'", $result);
    }

    public function test_apply_bindings_with_mixed_types()
    {
        $conn = $this->makeConnection();
        $result = $this->callApplyBindings($conn, 'SELECT * FROM t WHERE id = ? AND name = ?', [1, 'test']);
        $this->assertEquals("SELECT * FROM t WHERE id = 1 AND name = 'test'", $result);
    }

    public function test_apply_bindings_with_float()
    {
        $conn = $this->makeConnection();
        $result = $this->callApplyBindings($conn, 'SELECT * FROM t WHERE score > ?', [3.14]);
        $this->assertEquals('SELECT * FROM t WHERE score > 3.14', $result);
    }

    // --- constructor tests ---

    public function test_default_case_option_is_original()
    {
        $conn = new DremioOdbcConnection('dummy', 'db', '', []);
        $ref = new \ReflectionProperty($conn, 'caseOption');
        $ref->setAccessible(true);
        $this->assertEquals('original', $ref->getValue($conn));
    }

    public function test_case_option_lower()
    {
        $conn = new DremioOdbcConnection('dummy', 'db', '', ['case' => 'lower']);
        $ref = new \ReflectionProperty($conn, 'caseOption');
        $ref->setAccessible(true);
        $this->assertEquals('lower', $ref->getValue($conn));
    }

    public function test_case_option_upper()
    {
        $conn = new DremioOdbcConnection('dummy', 'db', '', ['case' => 'upper']);
        $ref = new \ReflectionProperty($conn, 'caseOption');
        $ref->setAccessible(true);
        $this->assertEquals('upper', $ref->getValue($conn));
    }
}
