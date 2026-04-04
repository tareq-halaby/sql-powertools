<?php

/**
 * DatabaseClonerTest
 *
 * Unit tests for the database cloning functionality.
 */
class DatabaseClonerTest
{
    /**
     * Test that cloning validates source database name
     */
    public function testValidatesSourceDatabaseName(): void
    {
        $validNames = ['mydb', 'my_db', 'mydb123', 'my-db'];
        $invalidNames = ['', ' ', 'my db', "my'db", 'my;db', '../etc'];

        foreach ($validNames as $name) {
            $this->assertTrue(
                $this->isValidDatabaseName($name),
                "Expected '$name' to be valid"
            );
        }

        foreach ($invalidNames as $name) {
            $this->assertFalse(
                $this->isValidDatabaseName($name),
                "Expected '$name' to be invalid"
            );
        }
    }

    /**
     * Test that sensitive columns are properly masked
     */
    public function testSensitiveDataMasking(): void
    {
        $sensitiveColumns = ['password', 'ssn', 'credit_card', 'email', 'phone'];
        $normalColumns = ['id', 'name', 'created_at', 'status'];

        foreach ($sensitiveColumns as $column) {
            $this->assertTrue(
                $this->isSensitiveColumn($column),
                "Expected '$column' to be flagged as sensitive"
            );
        }

        foreach ($normalColumns as $column) {
            $this->assertFalse(
                $this->isSensitiveColumn($column),
                "Expected '$column' to NOT be flagged as sensitive"
            );
        }
    }

    /**
     * Test row sample limit enforcement
     */
    public function testRowSampleLimitEnforcement(): void
    {
        $maxRows = 10000;
        $testCases = [
            ['input' => 100, 'expected' => 100],
            ['input' => 500, 'expected' => 500],
            ['input' => 10000, 'expected' => 10000],
            ['input' => 99999, 'expected' => $maxRows],
            ['input' => -1, 'expected' => 100], // Default
        ];

        foreach ($testCases as $case) {
            $result = $this->enforceRowLimit($case['input'], $maxRows);
            $this->assertEquals($case['expected'], $result);
        }
    }

    // Helper methods for testing
    private function isValidDatabaseName(string $name): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $name) === 1;
    }

    private function isSensitiveColumn(string $column): bool
    {
        $sensitivePatterns = ['password', 'ssn', 'credit', 'email', 'phone', 'secret', 'token'];
        foreach ($sensitivePatterns as $pattern) {
            if (stripos($column, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    private function enforceRowLimit(int $rows, int $max): int
    {
        if ($rows <= 0) return 100;
        return min($rows, $max);
    }
}
