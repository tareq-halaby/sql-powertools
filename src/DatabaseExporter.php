<?php

/**
 * DatabaseExporter
 *
 * Handles database export operations for SQL PowerTools.
 * Supports SQL and CSV export formats.
 */
class DatabaseExporter
{
    private string $host;
    private string $user;
    private string $database;

    public function __construct(string $host, string $user, string $database)
    {
        $this->host     = $host;
        $this->user     = $user;
        $this->database = $database;
    }

    /**
     * Export database tables to SQL format.
     *
     * @param array $tables List of tables to export (empty = all)
     * @param int $rowLimit Maximum rows per table
     * @return string Path to exported file
     */
    public function exportToSql(array $tables = [], int $rowLimit = 0): string
    {
        $outputFile = sys_get_temp_dir() . '/' . $this->database . '_' . date('Ymd_His') . '.sql';

        $cmd = $this->buildMysqldumpCommand($tables, $rowLimit);
        $cmd .= ' > ' . escapeshellarg($outputFile);

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException('Export failed with exit code: ' . $returnCode);
        }

        return $outputFile;
    }

    /**
     * Build a safe mysqldump command.
     *
     * @param array $tables Tables to include
     * @param int $rowLimit Rows per table limit
     * @return string The mysqldump command
     */
    private function buildMysqldumpCommand(array $tables, int $rowLimit): string
    {
        $cmd = sprintf(
            'mysqldump --host=%s --user=%s %s',
            escapeshellarg($this->host),
            escapeshellarg($this->user),
            escapeshellarg($this->database)
        );

        if ($rowLimit > 0) {
            $cmd .= ' --where=' . escapeshellarg('1=1 LIMIT ' . (int)$rowLimit);
        }

        foreach ($tables as $table) {
            $cmd .= ' ' . escapeshellarg($table);
        }

        return $cmd;
    }

    /**
     * Get the database name.
     */
    public function getDatabase(): string
    {
        return $this->database;
    }
}
