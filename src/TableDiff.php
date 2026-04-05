<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * TableDiff - v2.0.0
 *
 * Compares the schema (columns, indexes, foreign keys) of two tables —
 * either within the same database or across different databases on the
 * same server — and returns a structured diff.
 *
 * Bugs fixed in v2.0.0:
 * - Added missing namespace declaration (class was in global namespace)
 * - PDO references are now fully qualified (\PDO) for namespace correctness
 * - getForeignKeys() now also captures ON DELETE / ON UPDATE rules from
 *   REFERENTIAL_CONSTRAINTS for a more complete diff
 * - diffForeignKeys() now includes ref_db in signature to catch cross-db changes
 * - compareDatabase() now correctly handles $identical flag on result
 *
 * Typical use-cases:
 *  - Verify a cloned table matches the source after a clone operation.
 *  - Detect drift between a production and staging database.
 *  - Highlight missing indexes or changed column types before a migration.
 *
 * Usage:
 *   $diff = new TableDiff($pdo);
 *   $result = $diff->compare('prod_db', 'users', 'staging_db', 'users');
 *   // $result['columns']['added'], ['removed'], ['changed']
 *   // $result['indexes']['added'], ['removed']
 *   // $result['foreign_keys']['added'], ['removed']
 *   // $result['identical'] === true when no differences found
 */
class TableDiff
{
  public function __construct(private readonly \PDO $pdo) {}

  /**
   * Compare two tables and return a structured diff.
   *
   * @return array{
   *   source: string,
   *   target: string,
   *   identical: bool,
   *   columns: array{added: array, removed: array, changed: array},
   *   indexes: array{added: array, removed: array},
   *   foreign_keys: array{added: array, removed: array}
   * }
   */
  public function compare(
    string $sourceDb,
    string $sourceTable,
    string $targetDb,
    string $targetTable
  ): array {
    $srcCols = $this->getColumns($sourceDb, $sourceTable);
    $tgtCols = $this->getColumns($targetDb, $targetTable);

    $srcIdx  = $this->getIndexes($sourceDb, $sourceTable);
    $tgtIdx  = $this->getIndexes($targetDb, $targetTable);

    $srcFk   = $this->getForeignKeys($sourceDb, $sourceTable);
    $tgtFk   = $this->getForeignKeys($targetDb, $targetTable);

    $colDiff = $this->diffColumns($srcCols, $tgtCols);
    $idxDiff = $this->diffIndexes($srcIdx, $tgtIdx);
    $fkDiff  = $this->diffForeignKeys($srcFk, $tgtFk);

    $identical =
      empty($colDiff['added'])   &&
      empty($colDiff['removed']) &&
      empty($colDiff['changed']) &&
      empty($idxDiff['added'])   &&
      empty($idxDiff['removed']) &&
      empty($fkDiff['added'])    &&
      empty($fkDiff['removed']);

    return [
      'source'       => "`{$sourceDb}`.`{$sourceTable}`",
      'target'       => "`{$targetDb}`.`{$targetTable}`",
      'identical'    => $identical,
      'columns'      => $colDiff,
      'indexes'      => $idxDiff,
      'foreign_keys' => $fkDiff,
    ];
  }

  /**
   * Compare all tables in two databases and return per-table diffs.
   *
   * @return array Keyed by table name.
   */
  public function compareDatabase(string $sourceDb, string $targetDb): array
  {
    $srcTables = $this->listTables($sourceDb);
    $tgtTables = $this->listTables($targetDb);
    $allTables = array_unique(array_merge($srcTables, $tgtTables));
    sort($allTables);

    $results = [];

    foreach ($allTables as $table) {
      $inSrc = in_array($table, $srcTables, true);
      $inTgt = in_array($table, $tgtTables, true);

      if (!$inSrc) {
        $results[$table] = ['status' => 'only_in_target', 'identical' => false];
      } elseif (!$inTgt) {
        $results[$table] = ['status' => 'only_in_source', 'identical' => false];
      } else {
        $diff = $this->compare($sourceDb, $table, $targetDb, $table);
        $diff['status'] = $diff['identical'] ? 'identical' : 'different';
        $results[$table] = $diff;
      }
    }

    return $results;
  }

  // ------------------------------------------------------------------
  // Schema introspection
  // ------------------------------------------------------------------

  /** @return array<string, array> */
  private function getColumns(string $db, string $table): array
  {
    $stmt = $this->pdo->prepare(
      'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
       FROM information_schema.COLUMNS
       WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
       ORDER BY ORDINAL_POSITION'
    );
    $stmt->execute([$db, $table]);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $cols = [];
    foreach ($rows as $row) {
      $cols[$row['COLUMN_NAME']] = [
        'name'     => $row['COLUMN_NAME'],
        'type'     => $row['COLUMN_TYPE'],
        'nullable' => $row['IS_NULLABLE'],
        'default'  => $row['COLUMN_DEFAULT'],
        'extra'    => $row['EXTRA'],
      ];
    }
    return $cols;
  }

  /** @return array<string, array> */
  private function getIndexes(string $db, string $table): array
  {
    $stmt = $this->pdo->prepare(
      'SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX
       FROM information_schema.STATISTICS
       WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
       ORDER BY INDEX_NAME, SEQ_IN_INDEX'
    );
    $stmt->execute([$db, $table]);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $indexes = [];
    foreach ($rows as $row) {
      $name = $row['INDEX_NAME'];
      if (!isset($indexes[$name])) {
        $indexes[$name] = [
          'name'    => $name,
          'unique'  => !(bool)(int) $row['NON_UNIQUE'],
          'columns' => [],
        ];
      }
      $indexes[$name]['columns'][] = $row['COLUMN_NAME'];
    }
    return $indexes;
  }

  /** @return array<string, array> */
  private function getForeignKeys(string $db, string $table): array
  {
    $stmt = $this->pdo->prepare(
      'SELECT
         kcu.CONSTRAINT_NAME,
         kcu.COLUMN_NAME,
         kcu.REFERENCED_TABLE_SCHEMA,
         kcu.REFERENCED_TABLE_NAME,
         kcu.REFERENCED_COLUMN_NAME,
         rc.DELETE_RULE,
         rc.UPDATE_RULE
       FROM information_schema.KEY_COLUMN_USAGE kcu
       JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
         ON  rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
         AND rc.CONSTRAINT_NAME   = kcu.CONSTRAINT_NAME
       WHERE kcu.TABLE_SCHEMA = ? AND kcu.TABLE_NAME = ?
         AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
       ORDER BY kcu.CONSTRAINT_NAME'
    );
    $stmt->execute([$db, $table]);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $fks = [];
    foreach ($rows as $row) {
      $name = $row['CONSTRAINT_NAME'];
      $fks[$name] = [
        'name'        => $name,
        'column'      => $row['COLUMN_NAME'],
        'ref_db'      => $row['REFERENCED_TABLE_SCHEMA'],
        'ref_table'   => $row['REFERENCED_TABLE_NAME'],
        'ref_column'  => $row['REFERENCED_COLUMN_NAME'],
        'on_delete'   => $row['DELETE_RULE'],
        'on_update'   => $row['UPDATE_RULE'],
      ];
    }
    return $fks;
  }

  /** @return string[] */
  private function listTables(string $db): array
  {
    $stmt = $this->pdo->prepare(
      "SELECT TABLE_NAME FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
       ORDER BY TABLE_NAME"
    );
    $stmt->execute([$db]);
    return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
  }

  // ------------------------------------------------------------------
  // Diff helpers
  // ------------------------------------------------------------------

  /** @return array{added: array, removed: array, changed: array} */
  private function diffColumns(array $src, array $tgt): array
  {
    $added   = [];
    $removed = [];
    $changed = [];

    foreach ($tgt as $name => $col) {
      if (!isset($src[$name])) {
        $added[] = $col;
      }
    }

    foreach ($src as $name => $col) {
      if (!isset($tgt[$name])) {
        $removed[] = $col;
      } else {
        $diff = [];
        foreach (['type', 'nullable', 'default', 'extra'] as $attr) {
          if ((string)($src[$name][$attr] ?? '') !== (string)($tgt[$name][$attr] ?? '')) {
            $diff[$attr] = [
              'source' => $src[$name][$attr] ?? null,
              'target' => $tgt[$name][$attr] ?? null,
            ];
          }
        }
        if (!empty($diff)) {
          $changed[] = ['column' => $name, 'diff' => $diff];
        }
      }
    }

    return compact('added', 'removed', 'changed');
  }

  /** @return array{added: array, removed: array} */
  private function diffIndexes(array $src, array $tgt): array
  {
    $srcKeys = array_map(
      fn($i) => implode(',', $i['columns']) . '|unique=' . ($i['unique'] ? '1' : '0'),
      $src
    );
    $tgtKeys = array_map(
      fn($i) => implode(',', $i['columns']) . '|unique=' . ($i['unique'] ? '1' : '0'),
      $tgt
    );

    $added   = array_values(array_filter($tgt, fn($i, $n) => !in_array($tgtKeys[$n], $srcKeys, true), ARRAY_FILTER_USE_BOTH));
    $removed = array_values(array_filter($src, fn($i, $n) => !in_array($srcKeys[$n], $tgtKeys, true), ARRAY_FILTER_USE_BOTH));

    return compact('added', 'removed');
  }

  /** @return array{added: array, removed: array} */
  private function diffForeignKeys(array $src, array $tgt): array
  {
    // Include ref_db in signature to catch cross-db reference changes
    $signature = fn(array $fk) => "{$fk['column']}->{$fk['ref_db']}.{$fk['ref_table']}.{$fk['ref_column']}|del={$fk['on_delete']}|upd={$fk['on_update']}";

    $srcSigs = array_map($signature, $src);
    $tgtSigs = array_map($signature, $tgt);

    $added   = array_values(array_filter($tgt, fn($fk, $n) => !in_array($tgtSigs[$n], $srcSigs, true), ARRAY_FILTER_USE_BOTH));
    $removed = array_values(array_filter($src, fn($fk, $n) => !in_array($srcSigs[$n], $tgtSigs, true), ARRAY_FILTER_USE_BOTH));

    return compact('added', 'removed');
  }
}
