<?php

declare(strict_types=1);

/**
 * QueryProfiler — v2.0.0
 *
 * Profiles MySQL queries by wrapping them with timing, EXPLAIN analysis,
 * and index-usage warnings. Useful for identifying slow or unindexed queries
 * before running expensive clone/export operations.
 *
 * Usage:
 *   $profiler = new QueryProfiler($pdo);
 *   $result   = $profiler->profile('SELECT * FROM orders WHERE status = ?', ['pending']);
 *   // $result['duration_ms'], $result['rows'], $result['warnings'], $result['explain']
 */
class QueryProfiler
{
    private PDO $pdo;

    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Profile a single SELECT query.
     *
     * @param  string  $sql    The SQL query to profile (SELECT only).
     * @param  array<int|string, mixed>  $params PDO bound parameters.
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
            $explainSql = 'EXPLAIN ' . $sql;
            $explainStmt = $this->pdo->prepare($explainSql);
            $explainStmt->execute($params);
            $result['explain'] = $explainStmt->fetchAll(PDO::FETCH_ASSOC);
            $result['warnings'] = $this->analyzeExplain($result['explain']);

            // Execute the real query and measure time
            $start = hrtime(true);
            $stmt  = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $elapsed = hrtime(true) - $start;

            $result['duration_ms'] = round($elapsed / 1_000_000, 3);
            $result['rows']        = $stmt->rowCount();

            // Add slow-query warning
            if ($result['duration_ms'] > 1000) {
                $result['warnings'][] = sprintf(
                    'Slow query: took %.1f ms (> 1 s threshold)',
                    $result['duration_ms']
                );
            }
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        $this->history[] = $result;
        return $result;
    }

    /**
     * Profile the estimated cost of a full table scan for a given table.
     * Useful to pre-check tables before cloning.
     *
     * @return array<string, mixed>
     */
    public function profileTable(string $db, string $table): array
    {
        $sql    = 'SELECT * FROM `' . $db . '`.`' . $table . '` LIMIT 0';
        $result = $this->profile($sql);

        // Enrich with storage engine and row estimate from information_schema
        try {
            $stmt = $this->pdo->prepare(
                'SELECT ENGINE, TABLE_ROWS, ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS size_mb
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
            );
            $stmt->execute([$db, $table]);
            $meta = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($meta) {
                $result['engine']   = $meta['ENGINE'];
                $result['row_estimate'] = (int) ($meta['TABLE_ROWS'] ?? 0);
                $result['size_mb']  = (float) ($meta['size_mb'] ?? 0.0);

                if (strtoupper((string) ($meta['ENGINE'] ?? '')) === 'MYISAM') {
                    $result['warnings'][] = 'Table uses MyISAM engine — consider migrating to InnoDB for better reliability.';
                }
            }
        } catch (Throwable) {
            // Non-fatal
        }

        return $result;
    }

    /**
     * Analyze EXPLAIN rows and return human-readable warning strings.
     *
     * @param  array<int, array<string, mixed>>  $explainRows
     * @return string[]
     */
    private function analyzeExplain(array $explainRows): array
    {
        $warnings = [];
        foreach ($explainRows as $row) {
            $type  = strtoupper((string) ($row['type']  ?? ''));
            $extra = strtolower((string) ($row['Extra'] ?? ''));
            $table = (string) ($row['table'] ?? '?');
            $key   = $row['key'] ?? null;
            $rows  = (int) ($row['rows'] ?? 0);

            if ($type === 'ALL') {
                $warnings[] = "Full table scan on `{$table}` (type=ALL) — add an index to avoid scanning {$rows} rows.";
            } elseif ($type === 'INDEX') {
                $warnings[] = "Full index scan on `{$table}` (type=index) — query may still be slow with large datasets.";
            }

            if ($key === null || $key === '') {
                $warnings[] = "No index used on `{$table}` — consider adding an appropriate index.";
            }

            if (str_contains($extra, 'using filesort')) {
                $warnings[] = "Filesort on `{$table}` — ORDER BY cannot use an index; performance may degrade on large tables.";
            }

            if (str_contains($extra, 'using temporary')) {
                $warnings[] = "Temporary table on `{$table}` — GROUP BY or DISTINCT without an index; may be memory-intensive.";
            }
        }

        return array_values(array_unique($warnings));
    }

    /**
     * Return the profiling history (all queries profiled in this request).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    /**
     * Clear the profiling history.
     */
    public function clearHistory(): void
    {
        $this->history = [];
    }

    /**
     * Return a summary of all profiled queries.
     *
     * @return array{total: int, total_ms: float, slowest_ms: float, warnings: int}
     */
    public function summary(): array
    {
        $totalMs  = 0.0;
        $slowest  = 0.0;
        $warnings = 0;

        foreach ($this->history as $entry) {
            $totalMs  += $entry['duration_ms'];
            $slowest   = max($slowest, $entry['duration_ms']);
            $warnings += count($entry['warnings']);
        }

        return [
            'total'      => count($this->history),
            'total_ms'   => round($totalMs, 3),
            'slowest_ms' => round($slowest, 3),
            'warnings'   => $warnings,
        ];
    }
}
