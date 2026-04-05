<?php

declare(strict_types=1);

namespace SqlPowertools;

/**
 * QueryProfiler - MySQL Query Profiler with EXPLAIN Analysis
 *
 * Profiles MySQL queries by wrapping them with timing, EXPLAIN analysis,
 * and index-usage warnings. Useful for identifying slow or unindexed queries
 * before running expensive clone/export operations.
 *
 * Enhancements in v2.0.0:
 * - Added missing namespace declaration
 * - Added getHistory() to retrieve all profiled queries
 * - Added clearHistory() helper
 * - Slow query threshold made configurable via constructor
 * - Added getSlowQueries() to filter history for slow entries
 *
 * @package SqlPowertools
 * @version 2.0.0
 *
 * Usage:
 *   $profiler = new QueryProfiler($pdo);
 *   $result   = $profiler->profile('SELECT * FROM orders WHERE status = ?', ['pending']);
 *   // $result['duration_ms'], $result['rows'], $result['warnings'], $result['explain']
 */
class QueryProfiler
{
    private \PDO $pdo;
    private int  $slowThresholdMs;

    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    /**
     * @param \PDO $pdo             Active PDO connection
     * @param int  $slowThresholdMs Milliseconds above which a query is flagged slow (default 1000)
     */
    public function __construct(\PDO $pdo, int $slowThresholdMs = 1000)
    {
        $this->pdo             = $pdo;
        $this->slowThresholdMs = max(1, $slowThresholdMs);
    }

    /**
     * Profile a single SELECT query.
     *
     * @param string               $sql    The SQL query to profile (SELECT only).
     * @param array<int|string, mixed> $params PDO bound parameters.
     * @return array<string, mixed> {
     *   sql: string,
     *   params: array,
     *   duration_ms: float,
     *   rows: int,
     *   explain: array,
     *   warnings: string[],
     *   error: string|null
     * }
     */
    public function profile(string $sql, array $params = []): array
    {
        $result = [
            'sql'         => $sql,
            'params'      => $params,
            'duration_ms' => 0.0,
            'rows'        => 0,
            'explain'     => [],
            'warnings'    => [],
            'error'       => null,
        ];

        try {
            // Run EXPLAIN first (does not execute the query)
            $explainSql  = 'EXPLAIN ' . $sql;
            $explainStmt = $this->pdo->prepare($explainSql);
            $explainStmt->execute($params);
            $result['explain']   = $explainStmt->fetchAll(\PDO::FETCH_ASSOC);
            $result['warnings']  = $this->analyzeExplain($result['explain']);

            // Execute the real query and measure time
            $start = hrtime(true);
            $stmt  = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $elapsed = hrtime(true) - $start;

            $result['duration_ms'] = round($elapsed / 1_000_000, 3);
            $result['rows']        = $stmt->rowCount();

            // Add slow-query warning
            if ($result['duration_ms'] > $this->slowThresholdMs) {
                $result['warnings'][] = sprintf(
                    'Slow query: took %.1f ms (> %d ms threshold)',
                    $result['duration_ms'],
                    $this->slowThresholdMs
                );
            }
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        $this->history[] = $result;
        return $result;
    }

    /**
     * Return all profiled query results.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    /**
     * Return only queries that exceeded the slow threshold.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSlowQueries(): array
    {
        return array_values(array_filter(
            $this->history,
            fn($entry) => $entry['duration_ms'] > $this->slowThresholdMs
        ));
    }

    /**
     * Clear the query history.
     */
    public function clearHistory(): void
    {
        $this->history = [];
    }

    /**
     * Analyze an EXPLAIN result and return human-readable warnings.
     *
     * @param array<int, array<string, mixed>> $explain
     * @return string[]
     */
    private function analyzeExplain(array $explain): array
    {
        $warnings = [];
        foreach ($explain as $row) {
            if (isset($row['type']) && in_array($row['type'], ['ALL', 'index'], true)) {
                $warnings[] = sprintf(
                    "Full table scan (type=%s) on table '%s' - consider adding an index.",
                    $row['type'],
                    $row['table'] ?? '?'
                );
            }
            if (empty($row['key']) && !empty($row['possible_keys'])) {
                $warnings[] = sprintf(
                    "Table '%s' has possible keys (%s) but none are used.",
                    $row['table'] ?? '?',
                    $row['possible_keys']
                );
            }
        }
        return $warnings;
    }
}
