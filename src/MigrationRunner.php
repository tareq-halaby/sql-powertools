<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * MigrationRunner - Database Migration Execution Engine
 *
 * Handles running, tracking, and rolling back database schema migrations
 * in a safe and deterministic order. Each batch of migrations shares
 * the same batch number so they can be rolled back together.
 *
 * @package SqlPowertools
 * @version 2.0.0
 */
class MigrationRunner
{
  private const MIGRATIONS_TABLE = 'sql_powertools_migrations';

  public function __construct(private readonly \PDO $pdo)
  {
    $this->ensureMigrationsTable();
  }

  /**
   * Run all pending migrations from a given directory.
   *
   * Each call to runPending() uses a single shared batch number
   * so all migrations executed in that call can be rolled back together.
   *
   * @param  string $migrationsDir Absolute path to migrations directory
   * @return string[]              Names of migrations that were executed
   * @throws \RuntimeException     if a migration file is invalid
   */
  public function runPending(string $migrationsDir): array
  {
    $files = $this->getMigrationFiles($migrationsDir);
    $ran   = $this->getRanMigrations();
    $executed = [];

    // Determine the next batch number once, shared by all pending migrations
    $nextBatch = ($this->getLastBatch() ?? 0) + 1;

    foreach ($files as $file) {
      $name = pathinfo($file, PATHINFO_FILENAME);

      if (in_array($name, $ran, true)) {
        continue;
      }

      $this->runMigration($file, $name, $nextBatch);
      $executed[] = $name;
    }

    return $executed;
  }

  /**
   * Roll back the last batch of migrations.
   *
   * @param  string $migrationsDir Absolute path to migrations directory
   * @return string[]              Names of migrations that were rolled back
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
      $file = rtrim($migrationsDir, '/') . '/' . $name . '.php';

      if (file_exists($file)) {
        $migration = require $file;

        if (is_object($migration) && method_exists($migration, 'down')) {
          $this->pdo->beginTransaction();
          try {
            $migration->down($this->pdo);
            $this->pdo->commit();
          } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw new \RuntimeException(
              "Rollback failed for migration '{$name}': " . $e->getMessage(),
              0,
              $e
            );
          }
        }
      }

      $this->removeMigration($name);
      $rolledBack[] = $name;
    }

    return $rolledBack;
  }

  /**
   * Get the status of all migrations (ran vs pending).
   *
   * @param  string $migrationsDir Absolute path to migrations directory
   * @return array[] Each entry has keys: name, status, batch
   */
  public function status(string $migrationsDir): array
  {
    $files = $this->getMigrationFiles($migrationsDir);
    $ran   = $this->getRanMigrationsWithBatch();
    $result = [];

    foreach ($files as $file) {
      $name = pathinfo($file, PATHINFO_FILENAME);
      $result[] = [
        'name'   => $name,
        'status' => isset($ran[$name]) ? 'ran' : 'pending',
        'batch'  => $ran[$name] ?? null,
      ];
    }

    return $result;
  }

  /**
   * Roll back ALL migrations in reverse order.
   *
   * @param  string $migrationsDir Absolute path to migrations directory
   * @return string[]              All migration names that were rolled back
   */
  public function reset(string $migrationsDir): array
  {
    $allRolledBack = [];

    while ($this->getLastBatch() !== null) {
      $batch = $this->rollback($migrationsDir);
      $allRolledBack = array_merge($allRolledBack, $batch);
    }

    return $allRolledBack;
  }

  // ------------------------------------------------------------------
  // Private helpers
  // ------------------------------------------------------------------

  private function ensureMigrationsTable(): void
  {
    $this->pdo->exec('
      CREATE TABLE IF NOT EXISTS ' . self::MIGRATIONS_TABLE . ' (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        migration   VARCHAR(255) NOT NULL UNIQUE,
        batch       INT          NOT NULL,
        executed_at DATETIME     DEFAULT CURRENT_TIMESTAMP
      )
    ');
  }

  /** @return string[] Sorted list of migration file paths */
  private function getMigrationFiles(string $dir): array
  {
    if (!is_dir($dir)) {
      throw new \InvalidArgumentException("Migrations directory does not exist: {$dir}");
    }
    $files = glob(rtrim($dir, '/') . '/*.php') ?: [];
    sort($files);
    return $files;
  }

  /** @return string[] List of already-ran migration names */
  private function getRanMigrations(): array
  {
    $stmt = $this->pdo->query('SELECT migration FROM ' . self::MIGRATIONS_TABLE);
    return $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
  }

  /** @return array<string, int> Map of migration name => batch number */
  private function getRanMigrationsWithBatch(): array
  {
    $stmt = $this->pdo->query('SELECT migration, batch FROM ' . self::MIGRATIONS_TABLE);
    $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
    $map  = [];
    foreach ($rows as $row) {
      $map[$row['migration']] = (int) $row['batch'];
    }
    return $map;
  }

  private function getLastBatch(): ?int
  {
    $stmt   = $this->pdo->query('SELECT MAX(batch) FROM ' . self::MIGRATIONS_TABLE);
    $result = $stmt ? $stmt->fetchColumn() : false;
    return ($result !== false && $result !== null) ? (int) $result : null;
  }

  /**
   * Execute a single migration file and record it.
   *
   * @throws \RuntimeException if the migration up() fails
   */
  private function runMigration(string $file, string $name, int $batch): void
  {
    $migration = require $file;

    if (!is_object($migration) || !method_exists($migration, 'up')) {
      throw new \RuntimeException(
        "Migration file '{$file}' must return an object with an up() method."
      );
    }

    $this->pdo->beginTransaction();
    try {
      $migration->up($this->pdo);
      $this->pdo->commit();
    } catch (\Throwable $e) {
      $this->pdo->rollBack();
      throw new \RuntimeException(
        "Migration '{$name}' failed: " . $e->getMessage(),
        0,
        $e
      );
    }

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
