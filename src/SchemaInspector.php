<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * SchemaInspector - Database Schema Introspection Utility
 *
 * Provides methods to inspect database tables, columns, indexes,
 * and foreign keys at runtime without hardcoding schema knowledge.
 *
 * Enhancements in v2.0.0:
 * - Added table name validation to prevent SQL injection
 * - Added getForeignKeys() method
 * - Fixed getPrimaryKey() to return column names only
 * - Added tableExists() helper method
 * - Added getColumnNames() convenience method
 *
 * @package SqlPowertools
 * @version 2.0.0
 */
class SchemaInspector
{
    public function __construct(private readonly \PDO $pdo) {}

    /**
     * Validate a table or column identifier to prevent injection.
     *
     * @throws \InvalidArgumentException if the name contains invalid characters
     */
    private function validateIdentifier(string $name): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException(
                "Invalid identifier '" . $name . "'. Only alphanumeric characters and underscores are allowed."
            );
        }
    }

    /**
     * List all table names in the current database.
     *
     * @return string[]
     */
    public function getTables(): array
    {
        $stmt = $this->pdo->query('SHOW TABLES');
        return $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
    }

    /**
     * Check if a table exists in the current database.
     */
    public function tableExists(string $table): bool
    {
        return in_array($table, $this->getTables(), true);
    }

    /**
     * Get column metadata for a given table.
     *
     * @return array<int, array<string, mixed>>
     * @throws \InvalidArgumentException on invalid table name
     */
    public function getColumns(string $table): array
    {
        $this->validateIdentifier($table);
        $stmt = $this->pdo->prepare('DESCRIBE `' . $table . '`');
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get just the column names of a table.
     *
     * @return string[]
     */
    public function getColumnNames(string $table): array
    {
        return array_column($this->getColumns($table), 'Field');
    }

    /**
     * Get index information for a given table.
     *
     * @return array<int, array<string, mixed>>
     * @throws \InvalidArgumentException on invalid table name
     */
    public function getIndexes(string $table): array
    {
        $this->validateIdentifier($table);
        $stmt = $this->pdo->prepare('SHOW INDEX FROM `' . $table . '`');
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get foreign key information for a given table.
     *
     * @return array<int, array<string, mixed>>
     * @throws \InvalidArgumentException on invalid table name
     */
    public function getForeignKeys(string $table): array
    {
        $this->validateIdentifier($table);
        $stmt = $this->pdo->prepare(
            'SELECT
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME,
                CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL'
        );
        $stmt->execute([$table]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Check if a specific column exists on a table.
     */
    public function columnExists(string $table, string $column): bool
    {
        return in_array($column, $this->getColumnNames($table), true);
    }

    /**
     * Get the primary key column names of a table.
     *
     * @return string[]
     */
    public function getPrimaryKey(string $table): array
    {
        $primaryCols = array_filter(
            $this->getColumns($table),
            fn($col) => $col['Key'] === 'PRI'
        );
        return array_values(array_column($primaryCols, 'Field'));
    }
}
