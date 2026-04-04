<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * ConnectionPool - PDO Connection Pool Manager
 *
 * Manages a pool of reusable PDO database connections to minimise
 * the overhead of repeatedly opening and closing connections.
 *
 * @package SqlPowertools
 * @version 1.2.0
 */
class ConnectionPool
{
    private array $pool = [];
    private int $maxConnections;
    private array $dsns = [];

    public function __construct(int $maxConnections = 10)
    {
        $this->maxConnections = $maxConnections;
    }

    /**
     * Register a named DSN for connection creation.
     */
    public function addConnection(string $name, string $dsn, string $user = '', string $password = '', array $options = []): void
    {
        $this->dsns[$name] = compact('dsn', 'user', 'password', 'options');
    }

    /**
     * Acquire a PDO connection from the pool.
     */
    public function acquire(string $name = 'default'): \PDO
    {
        if (!isset($this->dsns[$name])) {
            throw new \InvalidArgumentException("No connection registered under '{$name}'");
        }

        foreach ($this->pool[$name] ?? [] as $key => $pdo) {
            unset($this->pool[$name][$key]);
            return $pdo;
        }

        if (count($this->pool[$name] ?? []) >= $this->maxConnections) {
            throw new \RuntimeException('Connection pool exhausted for: ' . $name);
        }

        return $this->createConnection($name);
    }

    /**
     * Release a PDO connection back to the pool.
     */
    public function release(string $name, \PDO $pdo): void
    {
        if (count($this->pool[$name] ?? []) < $this->maxConnections) {
            $this->pool[$name][] = $pdo;
        }
    }

    /**
     * Close all pooled connections for a given name.
     */
    public function flush(string $name): void
    {
        unset($this->pool[$name]);
    }

    private function createConnection(string $name): \PDO
    {
        $cfg = $this->dsns[$name];
        $pdo = new \PDO($cfg['dsn'], $cfg['user'], $cfg['password'], array_merge([
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_PERSISTENT => false,
        ], $cfg['options']));
        return $pdo;
    }
}
