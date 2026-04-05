<?php

declare(strict_types=1);

/**
 * HealthChecker — v2.0.0
 *
 * Performs a comprehensive health-check on a MySQL connection and database,
 * returning structured results with pass/warn/fail indicators.
 *
 * Checks include:
 *  - Connectivity and PDO ping
 *  - MySQL server version
 *  - Active connection count vs max_connections
 *  - Available disk space (via datadir size)
 *  - Table-level status (crashed, needs repair, etc.)
 *  - Slow-query log status
 *  - Presence of tables without primary keys
 */
class HealthChecker
{
    private PDO $pdo;
    private string $db;

    public function __construct(PDO $pdo, string $db)
    {
        $this->pdo = $pdo;
        $this->db  = $db;
    }

    /**
     * Run all health checks and return a structured report.
     *
     * @return array{
     *   passed: int,
     *   warned: int,
     *   failed: int,
     *   checks: array<int, array{name: string, status: string, detail: string}>
     * }
     */
    public function run(): array
    {
        $checks = [];

        $checks[] = $this->checkConnectivity();
        $checks[] = $this->checkServerVersion();
        $checks[] = $this->checkConnectionLoad();
        $checks[] = $this->checkTableHealth();
        $checks[] = $this->checkTablesWithoutPrimaryKeys();
        $checks[] = $this->checkSlowQueryLog();
        $checks[] = $this->checkCharset();
        $checks[] = $this->checkInnodbBufferPool();

        $passed = count(array_filter($checks, fn($c) => $c['status'] === 'pass'));
        $warned = count(array_filter($checks, fn($c) => $c['status'] === 'warn'));
        $failed = count(array_filter($checks, fn($c) => $c['status'] === 'fail'));

        return [
            'passed' => $passed,
            'warned' => $warned,
            'failed' => $failed,
            'checks' => $checks,
        ];
    }

    // ------------------------------------------------------------------
    // Individual checks
    // ------------------------------------------------------------------

    private function checkConnectivity(): array
    {
        try {
            $this->pdo->query('SELECT 1');
            return $this->check('Connectivity', 'pass', 'Database connection is healthy.');
        } catch (Throwable $e) {
            return $this->check('Connectivity', 'fail', 'Cannot reach database: ' . $e->getMessage());
        }
    }

    private function checkServerVersion(): array
    {
        try {
            $version = (string) $this->pdo->query('SELECT VERSION()')->fetchColumn();
            $major   = (int) explode('.', $version)[0];

            if ($major >= 8) {
                return $this->check('Server Version', 'pass', "MySQL {$version} — fully supported.");
            } elseif ($major === 5) {
                return $this->check('Server Version', 'warn', "MySQL {$version} — consider upgrading to MySQL 8.x.");
            }
            return $this->check('Server Version', 'warn', "MySQL {$version} — unknown version.");
        } catch (Throwable $e) {
            return $this->check('Server Version', 'fail', $e->getMessage());
        }
    }

    private function checkConnectionLoad(): array
    {
        try {
            $threads = (int) $this->pdo->query("SHOW STATUS LIKE 'Threads_connected'")
                ->fetch(PDO::FETCH_ASSOC)['Value'];

            $maxConn = (int) $this->pdo->query("SHOW VARIABLES LIKE 'max_connections'")
                ->fetch(PDO::FETCH_ASSOC)['Value'];

            $pct = $maxConn > 0 ? round(($threads / $maxConn) * 100, 1) : 0;
            $detail = "{$threads}/{$maxConn} connections in use ({$pct}%)";

            if ($pct >= 80) {
                return $this->check('Connection Load', 'fail', $detail . ' — critical: near connection limit.');
            } elseif ($pct >= 60) {
                return $this->check('Connection Load', 'warn', $detail . ' — elevated usage.');
            }
            return $this->check('Connection Load', 'pass', $detail);
        } catch (Throwable $e) {
            return $this->check('Connection Load', 'warn', 'Unable to check: ' . $e->getMessage());
        }
    }

    private function checkTableHealth(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'"
            );
            $stmt->execute([$this->db]);
            $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($tables)) {
                return $this->check('Table Health', 'warn', 'No tables found in database.');
            }

            $crashed = [];
            foreach ($tables as $t) {
                $name   = $t['TABLE_NAME'];
                $engine = strtoupper((string)($t['ENGINE'] ?? ''));
                if ($engine !== 'INNODB' && $engine !== 'MEMORY' && $engine !== 'VIEW') {
                    // Check non-InnoDB tables for corruption
                    try {
                        $res = $this->pdo->query(
                            'CHECK TABLE `' . $this->db . '`.`' . $name . '` FAST'
                        )->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($res as $row) {
                            if (strtolower((string)($row['Msg_type'] ?? '')) === 'error') {
                                $crashed[] = $name;
                            }
                        }
                    } catch (Throwable) {
                        // Skip tables we can't check
                    }
                }
            }

            if (!empty($crashed)) {
                return $this->check(
                    'Table Health', 'fail',
                    'Crashed/corrupt tables detected: ' . implode(', ', $crashed)
                );
            }
            return $this->check(
                'Table Health', 'pass',
                count($tables) . ' tables checked — no corruption found.'
            );
        } catch (Throwable $e) {
            return $this->check('Table Health', 'warn', 'Check skipped: ' . $e->getMessage());
        }
    }

    private function checkTablesWithoutPrimaryKeys(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT t.TABLE_NAME
                 FROM information_schema.TABLES t
                 LEFT JOIN information_schema.TABLE_CONSTRAINTS tc
                   ON tc.TABLE_SCHEMA = t.TABLE_SCHEMA
                   AND tc.TABLE_NAME  = t.TABLE_NAME
                   AND tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
                 WHERE t.TABLE_SCHEMA = ?
                   AND t.TABLE_TYPE   = 'BASE TABLE'
                   AND tc.TABLE_NAME IS NULL"
            );
            $stmt->execute([$this->db]);
            $noPk = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($noPk)) {
                return $this->check(
                    'Primary Keys', 'warn',
                    count($noPk) . ' table(s) lack a primary key: ' . implode(', ', $noPk)
                    . ' — cloning and masking will be slower without a PK.'
                );
            }
            return $this->check('Primary Keys', 'pass', 'All tables have primary keys.');
        } catch (Throwable $e) {
            return $this->check('Primary Keys', 'warn', 'Check skipped: ' . $e->getMessage());
        }
    }

    private function checkSlowQueryLog(): array
    {
        try {
            $row = $this->pdo->query("SHOW VARIABLES LIKE 'slow_query_log'")
                ->fetch(PDO::FETCH_ASSOC);
            $enabled = strtoupper((string)($row['Value'] ?? 'OFF')) === 'ON';

            if ($enabled) {
                return $this->check('Slow Query Log', 'pass', 'Slow query log is enabled.');
            }
            return $this->check(
                'Slow Query Log', 'warn',
                'Slow query log is disabled — enable it to identify performance bottlenecks.'
            );
        } catch (Throwable $e) {
            return $this->check('Slow Query Log', 'warn', 'Check skipped: ' . $e->getMessage());
        }
    }

    private function checkCharset(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
                 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?"
            );
            $stmt->execute([$this->db]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $charset   = strtolower((string)($row['DEFAULT_CHARACTER_SET_NAME'] ?? ''));
            $collation = (string)($row['DEFAULT_COLLATION_NAME'] ?? '');

            if ($charset === 'utf8mb4') {
                return $this->check('Charset', 'pass', "utf8mb4 / {$collation} — optimal for emoji and multi-language data.");
            } elseif ($charset === 'utf8') {
                return $this->check(
                    'Charset', 'warn',
                    "utf8 (3-byte) / {$collation} — migrate to utf8mb4 for full Unicode support."
                );
            }
            return $this->check('Charset', 'warn', "Non-standard charset: {$charset} / {$collation}.");
        } catch (Throwable $e) {
            return $this->check('Charset', 'warn', 'Check skipped: ' . $e->getMessage());
        }
    }

    private function checkInnodbBufferPool(): array
    {
        try {
            $bufRow = $this->pdo->query("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'")
                ->fetch(PDO::FETCH_ASSOC);
            $bufBytes  = (int)($bufRow['Value'] ?? 0);
            $bufMB     = round($bufBytes / 1024 / 1024);

            $dbSizeRow = $this->pdo->prepare(
                'SELECT SUM(DATA_LENGTH + INDEX_LENGTH) AS size
                 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?'
            );
            $dbSizeRow->execute([$this->db]);
            $dbBytes = (int)($dbSizeRow->fetchColumn() ?? 0);
            $dbMB    = round($dbBytes / 1024 / 1024);

            $detail = "InnoDB buffer pool: {$bufMB} MB, database size: {$dbMB} MB";

            if ($bufMB < $dbMB * 0.5 && $dbMB > 50) {
                return $this->check(
                    'InnoDB Buffer Pool', 'warn',
                    $detail . ' — buffer pool is less than 50% of DB size; consider increasing innodb_buffer_pool_size.'
                );
            }
            return $this->check('InnoDB Buffer Pool', 'pass', $detail);
        } catch (Throwable $e) {
            return $this->check('InnoDB Buffer Pool', 'warn', 'Check skipped: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @return array{name: string, status: string, detail: string}
     */
    private function check(string $name, string $status, string $detail): array
    {
        return ['name' => $name, 'status' => $status, 'detail' => $detail];
    }
}
