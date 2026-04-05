<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * QueryBuilder - Fluent SQL Query Builder
 *
 * Provides a chainable interface for constructing SQL queries
 * with automatic parameter binding and SQL injection prevention.
 *
 * @package SqlPowertools
 * @version 2.0.0
 */
class QueryBuilder
{
  private string $table = '';
  private array $conditions = [];
  private array $orConditions = [];
  private array $bindings = [];
  private array $columns = ['*'];
  private ?int $limitValue = null;
  private ?int $offsetValue = null;
  private array $orderByParts = [];
  private array $groupByParts = [];
  private array $joins = [];
  private ?string $havingClause = null;
  private int $bindingCounter = 0;

  public function __construct(private readonly \PDO $pdo) {}

  /**
   * Set the target table for the query.
   */
  public function table(string $table): static
  {
    $this->table = $table;
    return $this;
  }

  /**
   * Specify columns to select.
   */
  public function select(string ...$columns): static
  {
    $this->columns = $columns;
    return $this;
  }

  /**
   * Add a WHERE clause condition (AND).
   *
   * @throws \InvalidArgumentException if operator is not allowed
   */
  public function where(string $column, mixed $value, string $operator = '='): static
  {
    $allowed = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL'];
    if (!in_array(strtoupper($operator), $allowed, true)) {
      throw new \InvalidArgumentException("Operator '{$operator}' is not allowed.");
    }

    $this->bindingCounter++;
    $placeholder = ':bind_' . $this->bindingCounter;
    $this->conditions[] = "`{$column}` {$operator} {$placeholder}";
    $this->bindings[$placeholder] = $value;
    return $this;
  }

  /**
   * Add an OR WHERE clause condition.
   *
   * @throws \InvalidArgumentException if operator is not allowed
   */
  public function orWhere(string $column, mixed $value, string $operator = '='): static
  {
    $allowed = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];
    if (!in_array(strtoupper($operator), $allowed, true)) {
      throw new \InvalidArgumentException("Operator '{$operator}' is not allowed.");
    }

    $this->bindingCounter++;
    $placeholder = ':orbind_' . $this->bindingCounter;
    $this->orConditions[] = "`{$column}` {$operator} {$placeholder}";
    $this->bindings[$placeholder] = $value;
    return $this;
  }

  /**
   * Add a JOIN clause.
   *
   * @param string $type JOIN type: INNER, LEFT, RIGHT, CROSS
   */
  public function join(string $table, string $first, string $second, string $type = 'INNER'): static
  {
    $allowed = ['INNER', 'LEFT', 'RIGHT', 'CROSS'];
    $type = strtoupper($type);
    if (!in_array($type, $allowed, true)) {
      throw new \InvalidArgumentException("JOIN type '{$type}' is not allowed.");
    }
    $this->joins[] = "{$type} JOIN `{$table}` ON {$first} = {$second}";
    return $this;
  }

  /**
   * Set ORDER BY clause.
   *
   * @throws \InvalidArgumentException if direction is not ASC or DESC
   */
  public function orderBy(string $column, string $direction = 'ASC'): static
  {
    $direction = strtoupper($direction);
    if (!in_array($direction, ['ASC', 'DESC'], true)) {
      throw new \InvalidArgumentException("ORDER BY direction must be ASC or DESC.");
    }
    $this->orderByParts[] = "`{$column}` {$direction}";
    return $this;
  }

  /**
   * Set GROUP BY clause.
   */
  public function groupBy(string ...$columns): static
  {
    foreach ($columns as $col) {
      $this->groupByParts[] = "`{$col}`";
    }
    return $this;
  }

  /**
   * Set HAVING clause (raw expression).
   */
  public function having(string $expression): static
  {
    $this->havingClause = $expression;
    return $this;
  }

  /**
   * Set query LIMIT.
   *
   * @throws \InvalidArgumentException if limit is less than 1
   */
  public function limit(int $limit): static
  {
    if ($limit < 1) {
      throw new \InvalidArgumentException('Limit must be a positive integer.');
    }
    $this->limitValue = $limit;
    return $this;
  }

  /**
   * Set query OFFSET.
   *
   * @throws \InvalidArgumentException if offset is negative
   */
  public function offset(int $offset): static
  {
    if ($offset < 0) {
      throw new \InvalidArgumentException('Offset must be a non-negative integer.');
    }
    $this->offsetValue = $offset;
    return $this;
  }

  /**
   * Build and return the SQL string.
   *
   * @throws \LogicException if no table has been set
   */
  public function toSql(): string
  {
    if ($this->table === '') {
      throw new \LogicException('No table has been set. Call table() first.');
    }

    $sql = 'SELECT ' . implode(', ', $this->columns);
    $sql .= ' FROM `' . $this->table . '`';

    if (!empty($this->joins)) {
      $sql .= ' ' . implode(' ', $this->joins);
    }

    $whereParts = [];
    if (!empty($this->conditions)) {
      $whereParts[] = '(' . implode(' AND ', $this->conditions) . ')';
    }
    if (!empty($this->orConditions)) {
      $whereParts[] = '(' . implode(' OR ', $this->orConditions) . ')';
    }
    if (!empty($whereParts)) {
      $sql .= ' WHERE ' . implode(' OR ', $whereParts);
    }

    if (!empty($this->groupByParts)) {
      $sql .= ' GROUP BY ' . implode(', ', $this->groupByParts);
    }

    if ($this->havingClause !== null) {
      $sql .= ' HAVING ' . $this->havingClause;
    }

    if (!empty($this->orderByParts)) {
      $sql .= ' ORDER BY ' . implode(', ', $this->orderByParts);
    }

    if ($this->limitValue !== null) {
      $sql .= ' LIMIT ' . $this->limitValue;
    }

    if ($this->offsetValue !== null) {
      $sql .= ' OFFSET ' . $this->offsetValue;
    }

    return $sql;
  }

  /**
   * Execute the query and return all results.
   */
  public function get(): array
  {
    $stmt = $this->pdo->prepare($this->toSql());
    $stmt->execute($this->bindings);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Execute the query and return the first result.
   */
  public function first(): ?array
  {
    $this->limit(1);
    $results = $this->get();
    return $results[0] ?? null;
  }

  /**
   * Count matching records.
   */
  public function count(string $column = '*'): int
  {
    $original = $this->columns;
    $this->columns = ["COUNT({$column}) AS aggregate"];
    $stmt = $this->pdo->prepare($this->toSql());
    $stmt->execute($this->bindings);
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    $this->columns = $original;
    return (int) ($result['aggregate'] ?? 0);
  }

  /**
   * Insert a record into the table.
   *
   * @throws \LogicException if data is empty
   */
  public function insert(array $data): bool
  {
    if (empty($data)) {
      throw new \LogicException('Insert data cannot be empty.');
    }
    $columns = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($data)));
    $placeholders = ':ins_' . implode(', :ins_', array_keys($data));
    $sql = "INSERT INTO `{$this->table}` ({$columns}) VALUES ({$placeholders})";
    $bindings = [];
    foreach ($data as $col => $val) {
      $bindings[':ins_' . $col] = $val;
    }
    $stmt = $this->pdo->prepare($sql);
    return $stmt->execute($bindings);
  }

  /**
   * Insert a record and return its last insert ID.
   */
  public function insertGetId(array $data): int|string
  {
    $this->insert($data);
    return $this->pdo->lastInsertId();
  }

  /**
   * Update records matching current WHERE conditions.
   *
   * @throws \LogicException if data is empty
   */
  public function update(array $data): int
  {
    if (empty($data)) {
      throw new \LogicException('Update data cannot be empty.');
    }
    $setParts = array_map(fn($col) => "`{$col}` = :upd_{$col}", array_keys($data));
    $sql = "UPDATE `{$this->table}` SET " . implode(', ', $setParts);
    if (!empty($this->conditions)) {
      $sql .= ' WHERE ' . implode(' AND ', $this->conditions);
    }
    $bindings = array_merge(
      array_combine(array_map(fn($k) => ":upd_{$k}", array_keys($data)), $data),
      $this->bindings
    );
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($bindings);
    return $stmt->rowCount();
  }

  /**
   * Delete records matching current WHERE conditions.
   * Requires at least one WHERE condition to prevent accidental full-table deletes.
   *
   * @throws \LogicException if no WHERE conditions are set
   */
  public function delete(): int
  {
    if (empty($this->conditions) && empty($this->orConditions)) {
      throw new \LogicException('delete() requires at least one WHERE condition. Use truncate() to remove all rows.');
    }
    $sql = "DELETE FROM `{$this->table}`";
    $whereParts = [];
    if (!empty($this->conditions)) {
      $whereParts[] = '(' . implode(' AND ', $this->conditions) . ')';
    }
    if (!empty($this->orConditions)) {
      $whereParts[] = '(' . implode(' OR ', $this->orConditions) . ')';
    }
    $sql .= ' WHERE ' . implode(' OR ', $whereParts);
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($this->bindings);
    return $stmt->rowCount();
  }

  /**
   * Truncate the table (remove all rows).
   */
  public function truncate(): void
  {
    $this->pdo->exec("TRUNCATE TABLE `{$this->table}`");
  }

  /**
   * Execute a callback inside a database transaction.
   */
  public function transaction(callable $callback): mixed
  {
    $this->pdo->beginTransaction();
    try {
      $result = $callback($this);
      $this->pdo->commit();
      return $result;
    } catch (\Throwable $e) {
      $this->pdo->rollBack();
      throw $e;
    }
  }

  /**
   * Reset the builder state for reuse.
   */
  public function reset(): static
  {
    $this->table = '';
    $this->conditions = [];
    $this->orConditions = [];
    $this->bindings = [];
    $this->columns = ['*'];
    $this->limitValue = null;
    $this->offsetValue = null;
    $this->orderByParts = [];
    $this->groupByParts = [];
    $this->joins = [];
    $this->havingClause = null;
    $this->bindingCounter = 0;
    return $this;
  }
}
