<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * MigrationRunner - Database Migration Execution Engine
 *
 * Handles running, tracking, and rolling back database schema migrations
 * in a safe and deterministic order.
 *
 * @package SqlPowertools
 * @version 1.2.0
 */
class MigrationRunner
{
    private const MIGRATIONS_TABLE = 'sql_powertools_migrations';

    public function __construct(private readonly \PDO $pdo) {
        $this->ensureMigrationsTable();
    }

    /**
     * Run all pending migrations from a given directory.
     */
    public function runPending(string $migrationsDir): array
    {
        $files = $this->getMigrationFiles($migrationsDir);
        $ran = $this->getRanMigrations();
        $executed = [];

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (in_array($name, $ran, true)) {
                continue;
            }
            $this->runMigration($file, $name);
            $executed[] = $name;
        }

        return $executed;
    }

    /**
     * Roll back the last batch of migrations.
     */
    public function rollback(string $migrationsDir): array
    {
        $batch = $this->getLastBatch();
        if ($batch === null) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT migration FROM ' . self::MIGRATIONS_TABLE . ' WHERE batch = ? ORDER BY id DESC'
        );
        $stmt->execute([$batch]);
        $migrations = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $rolledBack = [];

        foreach ($migrations as $name) {
            $file = $migrationsDir . '/' . $name . '.php';
            if (file_exists($file)) {
                $migration = require $file;
                if (is_object($migration) && method_exists($migration, 'down')) {
                    $migration->down($this->pdo);
                }
            }
            $this->removeMigration($name);
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    private function ensureMigrationsTable(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS ' . self::MIGRATIONS_TABLE . ' (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    private function getMigrationFiles(string $dir): array
    {
        $files = glob($dir . '/*.php') ?: [];
        sort($files);
        return $files;
    }

    private function getRanMigrations(): array
    {
        $stmt = $this->pdo->query('SELECT migration FROM ' . self::MIGRATIONS_TABLE);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function getLastBatch(): ?int
    {
        $stmt = $this->pdo->query('SELECT MAX(batch) FROM ' . self::MIGRATIONS_TABLE);
        $result = $stmt->fetchColumn();
        return $result !== false ? (int) $result : null;
    }

    private function runMigration(string $file, string $name): void
    {
        $migration = require $file;
        if (is_object($migration) && method_exists($migration, 'up')) {
            $migration->up($this->pdo);
        }
        $batch = ($this->getLastBatch() ?? 0) + 1;
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . self::MIGRATIONS_TABLE . ' (migration, batch) VALUES (?, ?)'
        );
        $stmt->execute([$name, $batch]);
    }

    private function removeMigration(string $name): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM ' . self::MIGRATIONS_TABLE . ' WHERE migration = ?');
        $stmt->execute([$name]);
    }
}
