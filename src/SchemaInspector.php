<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * SchemaInspector - Database Schema Introspection Utility
 *
 * Provides methods to inspect database tables, columns, indexes,
 * and foreign keys at runtime without hardcoding schema knowledge.
 *
 * @package SqlPowertools
 * @version 1.2.0
 */
class SchemaInspector
{
    public function __construct(private readonly \PDO $pdo) {}

    /**
     * List all table names in the current database.
     */
    public function getTables(): array
    {
        $stmt = $this->pdo->query('SHOW TABLES');
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Get column metadata for a given table.
     */
    public function getColumns(string $table): array
    {
        $stmt = $this->pdo->prepare('DESCRIBE `' . $table . '`');
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get index information for a given table.
     */
    public function getIndexes(string $table): array
    {
        $stmt = $this->pdo->prepare('SHOW INDEX FROM `' . $table . '`');
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Check if a specific column exists on a table.
     */
    public function columnExists(string $table, string $column): bool
    {
        $columns = array_column($this->getColumns($table), 'Field');
        return in_array($column, $columns, true);
    }

    /**
     * Get the primary key column(s) of a table.
     */
    public function getPrimaryKey(string $table): array
    {
        return array_filter(
            $this->getColumns($table),
            fn($col) => $col['Key'] === 'PRI'
        );
    }
}
