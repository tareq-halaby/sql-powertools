<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * Paginator - SQL Query Pagination Helper
 *
 * Wraps a QueryBuilder instance to provide cursor-based and
 * offset-based pagination with metadata.
 *
 * Bugs fixed in v2.0.0:
 * - $total was never populated (always 0)
 * - getMeta() was missing total, total_pages fields
 * - paginate() did not validate $currentPage (page 0 or negative caused bugs)
 * - hasNextPage() checked count($items) === $perPage which gives false positive
 *   on the last page when the row count exactly equals $perPage
 *
 * @package SqlPowertools
 * @version 2.0.0
 */
class Paginator
{
  private int $total  = 0;
  private array $items = [];
  private bool $paginated = false;

  public function __construct(
    private readonly QueryBuilder $query,
    private readonly int $perPage = 15,
    private readonly int $currentPage = 1
  ) {
    if ($perPage < 1) {
      throw new \InvalidArgumentException('perPage must be at least 1.');
    }
    if ($currentPage < 1) {
      throw new \InvalidArgumentException('currentPage must be at least 1.');
    }
  }

  /**
   * Execute pagination and return results with metadata.
   *
   * Fetches total count separately so has_next is accurate even
   * when the last page contains exactly $perPage items.
   */
  public function paginate(): array
  {
    // Fetch total count without LIMIT/OFFSET
    $this->total = $this->query->count();

    $offset       = ($this->currentPage - 1) * $this->perPage;
    $this->items  = $this->query->limit($this->perPage)->offset($offset)->get();
    $this->paginated = true;

    return [
      'data' => $this->items,
      'meta' => $this->getMeta(),
    ];
  }

  /**
   * Get pagination metadata.
   *
   * @throws \LogicException if called before paginate()
   */
  public function getMeta(): array
  {
    if (!$this->paginated) {
      throw new \LogicException('Call paginate() before getMeta().');
    }

    $totalPages = $this->perPage > 0
      ? (int) ceil($this->total / $this->perPage)
      : 0;

    return [
      'current_page' => $this->currentPage,
      'per_page'     => $this->perPage,
      'total'        => $this->total,
      'total_pages'  => $totalPages,
      'has_next'     => $this->currentPage < $totalPages,
      'has_previous' => $this->currentPage > 1,
      'from'         => $this->total === 0 ? 0 : ($this->currentPage - 1) * $this->perPage + 1,
      'to'           => min($this->currentPage * $this->perPage, $this->total),
    ];
  }

  /**
   * Check if there is a next page.
   */
  public function hasNextPage(): bool
  {
    if (!$this->paginated) {
      throw new \LogicException('Call paginate() before hasNextPage().');
    }
    $totalPages = (int) ceil($this->total / $this->perPage);
    return $this->currentPage < $totalPages;
  }

  /**
   * Get the next page number.
   */
  public function nextPage(): int
  {
    return $this->currentPage + 1;
  }

  /**
   * Get the previous page number (minimum 1).
   */
  public function previousPage(): int
  {
    return max(1, $this->currentPage - 1);
  }

  /**
   * Get the fetched items.
   *
   * @throws \LogicException if called before paginate()
   */
  public function items(): array
  {
    if (!$this->paginated) {
      throw new \LogicException('Call paginate() before items().');
    }
    return $this->items;
  }

  /**
   * Get the total record count.
   *
   * @throws \LogicException if called before paginate()
   */
  public function total(): int
  {
    if (!$this->paginated) {
      throw new \LogicException('Call paginate() before total().');
    }
    return $this->total;
  }
}
