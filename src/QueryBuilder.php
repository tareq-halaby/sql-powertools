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
 * @version 1.2.0
 */
class QueryBuilder
{
    private string $table = '';
    private array $conditions = [];
    private array $bindings = [];
    private array $columns = ['*'];
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    private array $orderBy = [];
    private array $joins = [];

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
     * Add a WHERE clause condition.
     */
    public function where(string $column, mixed $value, string $operator = '='): static
    {
        $placeholder = ':' . str_replace('.', '_', $column) . count($this->bindings);
        $this->conditions[] = "{$column} {$operator} {$placeholder}";
        $this->bindings[$placeholder] = $value;
        return $this;
    }

    /**
     * Add an INNER JOIN clause.
     */
    public function join(string $table, string $first, string $second, string $type = 'INNER'): static
    {
        $this->joins[] = "{$type} JOIN {$table} ON {$first} = {$second}";
        return $this;
    }

    /**
     * Set ORDER BY clause.
     */
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orderBy[] = "{$column} " . strtoupper($direction);
        return $this;
    }

    /**
     * Set query LIMIT.
     */
    public function limit(int $limit): static
    {
        $this->limitValue = $limit;
        return $this;
    }

    /**
     * Set query OFFSET.
     */
    public function offset(int $offset): static
    {
        $this->offsetValue = $offset;
        return $this;
    }

    /**
     * Build and return the SQL string.
     */
    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns);
        $sql .= ' FROM ' . $this->table;

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        if (!empty($this->conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->conditions);
        }

        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
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
     * Insert a record into the table.
     */
    public function insert(array $data): bool
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }

    /**
     * Update records matching current WHERE conditions.
     */
    public function update(array $data): int
    {
        $setParts = array_map(fn($col) => "{$col} = :set_{$col}", array_keys($data));
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts);

        if (!empty($this->conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->conditions);
        }

        $bindings = array_merge(
            array_combine(array_map(fn($k) => ":set_{$k}", array_keys($data)), $data),
            $this->bindings
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    /**
     * Delete records matching current WHERE conditions.
     */
    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}";

        if (!empty($this->conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->conditions);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->rowCount();
    }
}
