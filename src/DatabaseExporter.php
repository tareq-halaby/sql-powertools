<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * DatabaseExporter - SQL and CSV Export Utility
 *
 * Handles database export operations for SQL PowerTools.
 * Supports SQL dump (via mysqldump) and CSV export (via PDO).
 *
 * Enhancements in v2.0.0:
 * - Added declare(strict_types=1) and namespace
 * - Added missing password constructor parameter
 * - Implemented exportToCsv() using PDO (no shell dependency)
 * - Added table name validation to prevent command injection
 * - Added port support in constructor
 * - Improved output file naming with sanitized database name
 *
 * @package SqlPowertools
 * @version 2.0.0
 */
class DatabaseExporter
{
    public function __construct(
        private readonly string $host,
        private readonly string $user,
        private readonly string $password,
        private readonly string $database,
        private readonly int    $port = 3306
    ) {
        if ($this->database === '') {
            throw new \InvalidArgumentException('Database name must not be empty.');
        }
    }

    /**
     * Export database tables to a SQL dump file via mysqldump.
     *
     * @param string[] $tables   List of tables to export (empty = all)
     * @param int      $rowLimit Maximum rows per table (0 = no limit)
     * @return string            Path to the exported .sql file
     * @throws \InvalidArgumentException on invalid table name
     * @throws \RuntimeException         if mysqldump fails
     */
    public function exportToSql(array $tables = [], int $rowLimit = 0): string
    {
        foreach ($tables as $table) {
            $this->validateIdentifier($table);
        }

        $safeName    = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->database);
        $outputFile  = sys_get_temp_dir() . '/' . $safeName . '_' . date('Ymd_His') . '.sql';
        $cmd         = $this->buildMysqldumpCommand($tables, $rowLimit);
        $cmd        .= ' > ' . escapeshellarg($outputFile);

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('mysqldump failed with exit code: ' . $returnCode);
        }

        return $outputFile;
    }

    /**
     * Export a table to a CSV file using a PDO connection.
     *
     * @param \PDO     $pdo      Active PDO connection
     * @param string   $table    Table name to export
     * @param string[] $columns  Columns to include (empty = all)
     * @return string            Path to the exported .csv file
     * @throws \InvalidArgumentException on invalid table/column name
     * @throws \RuntimeException         if file cannot be written
     */
    public function exportToCsv(\PDO $pdo, string $table, array $columns = []): string
    {
        $this->validateIdentifier($table);
        foreach ($columns as $col) {
            $this->validateIdentifier($col);
        }

        $cols = empty($columns) ? '*' : implode(', ', array_map(fn($c) => '`' . $c . '`', $columns));
        $stmt = $pdo->query('SELECT ' . $cols . ' FROM `' . $table . '`');
        if (!$stmt) {
            throw new \RuntimeException('Failed to query table: ' . $table);
        }

        $safeName   = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $table);
        $outputFile = sys_get_temp_dir() . '/' . $safeName . '_' . date('Ymd_His') . '.csv';
        $handle     = fopen($outputFile, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open file for writing: ' . $outputFile);
        }

        $headerWritten = false;
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (!$headerWritten) {
                fputcsv($handle, array_keys($row));
                $headerWritten = true;
            }
            fputcsv($handle, $row);
        }

        fclose($handle);
        return $outputFile;
    }

    /**
     * Get the configured database name.
     */
    public function getDatabase(): string
    {
        return $this->database;
    }

    /**
     * Build a safe mysqldump shell command.
     *
     * @param string[] $tables
     * @param int      $rowLimit
     */
    private function buildMysqldumpCommand(array $tables, int $rowLimit): string
    {
        $cmd = sprintf(
            'mysqldump --host=%s --port=%d --user=%s --password=%s %s',
            escapeshellarg($this->host),
            $this->port,
            escapeshellarg($this->user),
            escapeshellarg($this->password),
            escapeshellarg($this->database)
        );

        if ($rowLimit > 0) {
            $cmd .= ' --where=' . escapeshellarg('1=1 LIMIT ' . (int) $rowLimit);
        }

        foreach ($tables as $table) {
            $cmd .= ' ' . escapeshellarg($table);
        }

        return $cmd;
    }

    /**
     * Validate a SQL identifier (table or column name).
     *
     * @throws \InvalidArgumentException on invalid name
     */
    private function validateIdentifier(string $name): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException(
                "Invalid identifier '" . $name . "'. Only alphanumeric characters and underscores are allowed."
            );
        }
    }
}
