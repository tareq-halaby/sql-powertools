<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * RateLimiter - Query Rate Limiting Utility
 *
 * Prevents abuse by limiting the number of queries that can
 * be executed within a configurable time window.
 *
 * Enhancements in v2.0.0:
 * - Added retryAfter() to get seconds until next allowed attempt
 * - Added throttle() helper to run a callback only if not rate-limited
 * - Validated constructor arguments
 * - $buckets stores floats from microtime() for sub-second accuracy
 *
 * @package SqlPowertools
 * @version 2.0.0
 */
class RateLimiter
{
  /** @var array<string, float[]> */
  private array $buckets = [];

  public function __construct(
    private readonly int $maxAttempts = 100,
    private readonly int $windowSeconds = 60
  ) {
    if ($maxAttempts < 1) {
      throw new \InvalidArgumentException('maxAttempts must be at least 1.');
    }
    if ($windowSeconds < 1) {
      throw new \InvalidArgumentException('windowSeconds must be at least 1.');
    }
  }

  /**
   * Check whether a key is allowed to proceed and record the attempt.
   *
   * Returns true (allowed) or false (rate-limited).
   */
  public function attempt(string $key): bool
  {
    $this->cleanup($key);
    $count = count($this->buckets[$key] ?? []);

    if ($count >= $this->maxAttempts) {
      return false;
    }

    $this->buckets[$key][] = microtime(true);
    return true;
  }

  /**
   * Get remaining attempts for a key.
   */
  public function remaining(string $key): int
  {
    $this->cleanup($key);
    return max(0, $this->maxAttempts - count($this->buckets[$key] ?? []));
  }

  /**
   * Get seconds until the rate limit resets for this key.
   * Returns 0 if not currently rate-limited.
   */
  public function retryAfter(string $key): int
  {
    $this->cleanup($key);
    $count = count($this->buckets[$key] ?? []);

    if ($count < $this->maxAttempts) {
      return 0;
    }

    // Oldest timestamp in the bucket
    $oldest = min($this->buckets[$key]);
    $resetsAt = (int) ceil($oldest + $this->windowSeconds);
    return max(0, $resetsAt - (int) microtime(true));
  }

  /**
   * Execute a callback only if rate limit has not been reached.
   *
   * @param  callable $callback The work to perform
   * @param  callable|null $onThrottle Optional handler called when rate-limited
   * @return mixed Return value of $callback, or null if throttled
   */
  public function throttle(string $key, callable $callback, ?callable $onThrottle = null): mixed
  {
    if (!$this->attempt($key)) {
      if ($onThrottle !== null) {
        return $onThrottle($this->retryAfter($key));
      }
      return null;
    }
    return $callback();
  }

  /**
   * Reset attempts for a key.
   */
  public function reset(string $key): void
  {
    unset($this->buckets[$key]);
  }

  /**
   * Reset all tracked keys.
   */
  public function resetAll(): void
  {
    $this->buckets = [];
  }

  private function cleanup(string $key): void
  {
    $cutoff = microtime(true) - $this->windowSeconds;
    $this->buckets[$key] = array_values(array_filter(
      $this->buckets[$key] ?? [],
      fn(float $ts) => $ts > $cutoff
    ));
  }
}
