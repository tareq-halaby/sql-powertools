<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * CacheManager - Query Result Cache Layer
 *
 * Provides in-memory and file-based caching for SQL query results
 * to reduce redundant database calls and improve performance.
 *
 * Bugs fixed in v2.0.0:
 * - has() now correctly returns false for cached null values (sentinel pattern)
 * - set() uses atomic write (write to temp file then rename) to prevent corruption
 * - get() handles unserialize() failure gracefully
 * - flush() handles glob() returning false safely
 * - makeKey() now enforces a max key length to prevent filesystem errors
 *
 * @package SqlPowertools
 * @version 2.0.0
 */
class CacheManager
{
  /** Sentinel stored alongside cached values to distinguish "cached null" from "miss" */
  private const SENTINEL = '__sql_powertools_cached__';

  private array $memoryCache = [];
  private string $cacheDir;
  private int $defaultTtl;

  public function __construct(
    string $cacheDir = '/tmp/sql-powertools-cache',
    int $defaultTtl = 3600
  ) {
    if ($defaultTtl < 1) {
      throw new \InvalidArgumentException('Default TTL must be a positive integer.');
    }
    $this->cacheDir   = rtrim($cacheDir, '/');
    $this->defaultTtl = $defaultTtl;

    if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0755, true) && !is_dir($this->cacheDir)) {
      throw new \RuntimeException("Cache directory could not be created: {$this->cacheDir}");
    }
  }

  /**
   * Store a value in cache with optional TTL.
   * Supports caching null values correctly.
   *
   * @throws \RuntimeException if the cache file cannot be written
   */
  public function set(string $key, mixed $value, ?int $ttl = null): void
  {
    $ttl    = ($ttl !== null && $ttl > 0) ? $ttl : $this->defaultTtl;
    $expiry = time() + $ttl;

    $entry = [
      'sentinel' => self::SENTINEL,
      'value'    => $value,
      'expiry'   => $expiry,
    ];

    // Store in memory cache
    $this->memoryCache[$key] = $entry;

    // Write to file atomically: write to temp then rename
    $filePath = $this->getFilePath($key);
    $tmpPath  = $filePath . '.tmp.' . uniqid('', true);
    $data     = serialize($entry);

    if (file_put_contents($tmpPath, $data, LOCK_EX) === false) {
      throw new \RuntimeException("Failed to write cache file: {$tmpPath}");
    }

    if (!rename($tmpPath, $filePath)) {
      @unlink($tmpPath);
      throw new \RuntimeException("Failed to atomically write cache file: {$filePath}");
    }
  }

  /**
   * Retrieve a value from cache.
   * Returns null on cache miss OR if the cached value is null.
   * Use has() to distinguish between null-value-cached and cache-miss.
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

    $raw = file_get_contents($filePath);
    if ($raw === false) {
      return null;
    }

    $entry = @unserialize($raw);

    if (!is_array($entry) || ($entry['sentinel'] ?? null) !== self::SENTINEL) {
      // Corrupted cache file — remove it
      @unlink($filePath);
      return null;
    }

    if ($entry['expiry'] <= time()) {
      @unlink($filePath);
      return null;
    }

    $this->memoryCache[$key] = $entry;
    return $entry['value'];
  }

  /**
   * Check if a cache key exists and is valid (not expired).
   * Correctly handles cached null values.
   */
  public function has(string $key): bool
  {
    // Check memory cache
    if (isset($this->memoryCache[$key])) {
      return $this->memoryCache[$key]['expiry'] > time();
    }

    // Check file cache
    $filePath = $this->getFilePath($key);

    if (!file_exists($filePath)) {
      return false;
    }

    $raw = file_get_contents($filePath);
    if ($raw === false) {
      return false;
    }

    $entry = @unserialize($raw);

    if (!is_array($entry) || ($entry['sentinel'] ?? null) !== self::SENTINEL) {
      @unlink($filePath);
      return false;
    }

    if ($entry['expiry'] <= time()) {
      @unlink($filePath);
      return false;
    }

    $this->memoryCache[$key] = $entry;
    return true;
  }

  /**
   * Remove a specific cache entry.
   */
  public function forget(string $key): void
  {
    unset($this->memoryCache[$key]);
    $filePath = $this->getFilePath($key);
    if (file_exists($filePath)) {
      @unlink($filePath);
    }
  }

  /**
   * Clear all cached entries.
   */
  public function flush(): void
  {
    $this->memoryCache = [];
    $files = glob($this->cacheDir . '/*.cache');
    if ($files === false) {
      return;
    }
    foreach ($files as $file) {
      @unlink($file);
    }
  }

  /**
   * Get or store a value using a callback.
   * Works correctly when the callback returns null.
   */
  public function remember(string $key, callable $callback, ?int $ttl = null): mixed
  {
    if ($this->has($key)) {
      return $this->get($key);
    }

    $value = $callback();
    $this->set($key, $value, $ttl);
    return $value;
  }

  /**
   * Store a value forever (effectively — TTL of ~68 years).
   */
  public function forever(string $key, mixed $value): void
  {
    $this->set($key, $value, PHP_INT_MAX - time());
  }

  /**
   * Remove all expired entries from the file cache (garbage collection).
   *
   * @return int Number of expired files removed
   */
  public function gc(): int
  {
    $removed = 0;
    $files   = glob($this->cacheDir . '/*.cache') ?: [];
    $now     = time();

    foreach ($files as $file) {
      $raw   = @file_get_contents($file);
      $entry = $raw !== false ? @unserialize($raw) : false;

      if (!is_array($entry) || ($entry['expiry'] ?? 0) <= $now) {
        @unlink($file);
        $removed++;
      }
    }

    // Evict stale memory entries too
    foreach ($this->memoryCache as $key => $entry) {
      if ($entry['expiry'] <= $now) {
        unset($this->memoryCache[$key]);
      }
    }

    return $removed;
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
