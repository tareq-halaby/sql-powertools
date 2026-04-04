<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * Paginator - SQL Query Pagination Helper
 *
 * Wraps a QueryBuilder instance to provide cursor-based and
 * offset-based pagination with metadata.
 *
 * @package SqlPowertools
 * @version 1.3.0
 */
class Paginator
{
    private int $total = 0;
    private array $items = [];

    public function __construct(
        private readonly QueryBuilder $query,
        private readonly int $perPage = 15,
        private readonly int $currentPage = 1
    ) {}

    /**
     * Execute pagination and return results with metadata.
     */
    public function paginate(): array
    {
        $offset = ($this->currentPage - 1) * $this->perPage;
        $this->items = $this->query->limit($this->perPage)->offset($offset)->get();
        return [
            'data' => $this->items,
            'meta' => $this->getMeta(),
        ];
    }

    /**
     * Get pagination metadata.
     */
    public function getMeta(): array
    {
        return [
            'current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'has_next' => count($this->items) === $this->perPage,
            'has_previous' => $this->currentPage > 1,
        ];
    }

    /**
     * Check if there is a next page.
     */
    public function hasNextPage(): bool
    {
        return count($this->items) === $this->perPage;
    }

    /**
     * Get the next page number.
     */
    public function nextPage(): int
    {
        return $this->currentPage + 1;
    }

    /**
     * Get the previous page number.
     */
    public function previousPage(): int
    {
        return max(1, $this->currentPage - 1);
    }
}
