<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * CacheManager - Query Result Cache Layer
 *
 * Provides in-memory and file-based caching for SQL query results
 * to reduce redundant database calls and improve performance.
 *
 * @package SqlPowertools
 * @version 1.2.0
 */
class CacheManager
{
    private array $memoryCache = [];
    private string $cacheDir;
    private int $defaultTtl;

    public function __construct(
        string $cacheDir = '/tmp/sql-powertools-cache',
        int $defaultTtl = 3600
    ) {
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->defaultTtl = $defaultTtl;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Store a value in cache with optional TTL.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $ttl ??= $this->defaultTtl;
        $expiry = time() + $ttl;

        $this->memoryCache[$key] = ['value' => $value, 'expiry' => $expiry];

        $filePath = $this->getFilePath($key);
        file_put_contents($filePath, serialize(['value' => $value, 'expiry' => $expiry]));
    }

    /**
     * Retrieve a value from cache.
     */
    public function get(string $key): mixed
    {
        // Check memory cache first
        if (isset($this->memoryCache[$key])) {
            $entry = $this->memoryCache[$key];
            if ($entry['expiry'] > time()) {
                return $entry['value'];
            }
            unset($this->memoryCache[$key]);
        }

        // Check file cache
        $filePath = $this->getFilePath($key);
        if (!file_exists($filePath)) {
            return null;
        }

        $entry = unserialize(file_get_contents($filePath));
        if ($entry['expiry'] <= time()) {
            unlink($filePath);
            return null;
        }

        $this->memoryCache[$key] = $entry;
        return $entry['value'];
    }

    /**
     * Check if a cache key exists and is valid.
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Remove a specific cache entry.
     */
    public function forget(string $key): void
    {
        unset($this->memoryCache[$key]);
        $filePath = $this->getFilePath($key);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Clear all cached entries.
     */
    public function flush(): void
    {
        $this->memoryCache = [];
        foreach (glob($this->cacheDir . '/*.cache') as $file) {
            unlink($file);
        }
    }

    /**
     * Get or store a value using a callback.
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    /**
     * Generate a cache key from a SQL query and bindings.
     */
    public function makeKey(string $sql, array $bindings = []): string
    {
        return 'query_' . md5($sql . serialize($bindings));
    }

    private function getFilePath(string $key): string
    {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }
}
