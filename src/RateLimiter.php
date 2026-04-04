<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * RateLimiter - Query Rate Limiting Utility
 *
 * Prevents abuse by limiting the number of queries that can
 * be executed within a configurable time window.
 *
 * @package SqlPowertools
 * @version 1.2.0
 */
class RateLimiter
{
    private array $buckets = [];

    public function __construct(
        private readonly int $maxAttempts = 100,
        private readonly int $windowSeconds = 60
    ) {}

    /**
     * Check whether a key is allowed to proceed.
     */
    public function attempt(string $key): bool
    {
        $this->cleanup($key);
        $count = count($this->buckets[$key] ?? []);

        if ($count >= $this->maxAttempts) {
            return false;
        }

        $this->buckets[$key][] = time();
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
     * Reset attempts for a key.
     */
    public function reset(string $key): void
    {
        unset($this->buckets[$key]);
    }

    private function cleanup(string $key): void
    {
        $cutoff = time() - $this->windowSeconds;
        $this->buckets[$key] = array_filter(
            $this->buckets[$key] ?? [],
            fn(int $ts) => $ts > $cutoff
        );
    }
}
