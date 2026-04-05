<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * ConnectionPool - PDO Connection Pool Manager
 *
 * Manages a pool of reusable PDO database connections to minimise
 * the overhead of repeatedly opening and closing connections.
 *
 * Bugs fixed in v2.0.0:
 * - acquire() now returns idle connections from pool before creating new ones
 *   (original code used `foreach` which always iterated but never properly reused)
 * - release() silently discarded connections exceeding maxConnections
 * - Added connection health check (ping) before returning pooled connections
 * - Added pool stats and per-name connection count
 *
 * @package SqlPowertools
 * @version 2.0.0
 */
class ConnectionPool
{
  /** @var array<string, \PDO[]> Available (idle) connections per name */
  private array $idle = [];

  /** @var array<string, int> Count of connections currently in use per name */
  private array $inUse = [];

  private int $maxConnections;

  /** @var array<string, array{dsn:string,user:string,password:string,options:array}> */
  private array $dsns = [];

  public function __construct(int $maxConnections = 10)
  {
    if ($maxConnections < 1) {
      throw new \InvalidArgumentException('maxConnections must be at least 1.');
    }
    $this->maxConnections = $maxConnections;
  }

  /**
   * Register a named DSN for connection creation.
   */
  public function addConnection(
    string $name,
    string $dsn,
    string $user = '',
    string $password = '',
    array $options = []
  ): void {
    $this->dsns[$name] = compact('dsn', 'user', 'password', 'options');
  }

  /**
   * Acquire a PDO connection from the pool.
   *
   * Returns an idle connection if one is available and healthy,
   * otherwise creates a new connection (up to maxConnections total).
   *
   * @throws \InvalidArgumentException if name is not registered
   * @throws \RuntimeException         if pool is exhausted
   */
  public function acquire(string $name = 'default'): \PDO
  {
    if (!isset($this->dsns[$name])) {
      throw new \InvalidArgumentException("No connection registered under '{$name}'.");
    }

    // Return an idle connection if available and healthy
    while (!empty($this->idle[$name])) {
      $pdo = array_pop($this->idle[$name]);
      if ($this->isAlive($pdo)) {
        $this->inUse[$name] = ($this->inUse[$name] ?? 0) + 1;
        return $pdo;
      }
      // Stale connection discarded, try next
    }

    $total = count($this->idle[$name] ?? []) + ($this->inUse[$name] ?? 0);
    if ($total >= $this->maxConnections) {
      throw new \RuntimeException("Connection pool exhausted for: {$name}");
    }

    $this->inUse[$name] = ($this->inUse[$name] ?? 0) + 1;
    return $this->createConnection($name);
  }

  /**
   * Release a PDO connection back to the pool.
   */
  public function release(string $name, \PDO $pdo): void
  {
    $this->inUse[$name] = max(0, ($this->inUse[$name] ?? 1) - 1);

    $idleCount = count($this->idle[$name] ?? []);
    $total     = $idleCount + ($this->inUse[$name] ?? 0);

    if ($total < $this->maxConnections) {
      $this->idle[$name][] = $pdo;
    }
    // If pool is full, simply discard the connection (GC will clean it up)
  }

  /**
   * Close all pooled (idle) connections for a given name.
   */
  public function flush(string $name): void
  {
    unset($this->idle[$name]);
    // Note: in-use connections are not forcibly closed
  }

  /**
   * Close all connections for all names.
   */
  public function flushAll(): void
  {
    $this->idle  = [];
    $this->inUse = [];
  }

  /**
   * Get pool statistics.
   *
   * @return array{idle: int, in_use: int, max: int}
   */
  public function stats(string $name): array
  {
    return [
      'idle'   => count($this->idle[$name] ?? []),
      'in_use' => $this->inUse[$name] ?? 0,
      'max'    => $this->maxConnections,
    ];
  }

  /**
   * Check whether a PDO connection is still alive.
   */
  private function isAlive(\PDO $pdo): bool
  {
    try {
      $pdo->query('SELECT 1');
      return true;
    } catch (\Throwable) {
      return false;
    }
  }

  private function createConnection(string $name): \PDO
  {
    $cfg = $this->dsns[$name];
    return new \PDO(
      $cfg['dsn'],
      $cfg['user'],
      $cfg['password'],
      array_merge([
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_PERSISTENT         => false,
      ], $cfg['options'])
    );
  }
}
