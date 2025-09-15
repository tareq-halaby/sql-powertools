<?php
/**
 * MySQL Database Sampler — single file PHP tool
 * ------------------------------------------------
 * - Choose a source database, then pick tables.
 * - Copy X rows per selected table into a target database.
 * - Pretty UI with Tailwind (via CDN) and helpful output.
 *
 * Usage:
 *   - Put this file on a PHP-enabled server (PHP 8+ recommended).
 *   - Open in browser.
 *
 * Security note: This is an admin tool. Protect the file (basic auth, IP allowlist, etc.).
 */

// Session hardening
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
session_start();
$csrfError = null;
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}
function csrf_check(): bool {
    $token = $_POST['_csrf'] ?? '';
    return is_string($token) && hash_equals($_SESSION['_csrf'] ?? '', $token);
}
// Load .env (simple parser) so SAMPLER_PASSWORD can be read from a local .env file
function load_env_file(string $path): void {
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if ($line === '' || str_starts_with(trim($line), '#')) continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        $value = trim($value, "\"' ");
        if ($key !== '') {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            // putenv so getenv() works
            @putenv($key.'='.$value);
        }
    }
}
load_env_file(__DIR__.'/.env');

// Global security headers
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
// Relaxed CSP to allow Tailwind CDN and inline scripts (can be tightened later)
header("Content-Security-Policy: default-src 'self' https://cdn.tailwindcss.com; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com");

// Simple auth configuration
$REQUIRE_AUTH = true; // set to false to disable auth
$CONFIG_PASSWORD = getenv('SAMPLER_PASSWORD') ?: ($_ENV['SAMPLER_PASSWORD'] ?? ($_SERVER['SAMPLER_PASSWORD'] ?? 'admin123'));
$READ_ONLY = filter_var(getenv('READ_ONLY') ?: ($_ENV['READ_ONLY'] ?? 'false'), FILTER_VALIDATE_BOOLEAN);
$DIAGRAM_ENABLED = filter_var(getenv('DIAGRAM_ENABLED') ?: ($_ENV['DIAGRAM_ENABLED'] ?? 'true'), FILTER_VALIDATE_BOOLEAN);
// IP allowlist: comma-separated list in ALLOW_IPS
$ALLOW_IPS = array_filter(array_map('trim', explode(',', (string)(getenv('ALLOW_IPS') ?: ($_ENV['ALLOW_IPS'] ?? '')))));
if (!empty($ALLOW_IPS)) {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($clientIp && !in_array($clientIp, $ALLOW_IPS, true)) {
        http_response_code(403);
        if (!class_exists('League\\Plates\\Engine')) {
            require_once __DIR__ . '/vendor/autoload.php';
        }
        $templates = new League\Plates\Engine(__DIR__ . '/views');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8" />'
            . '<meta name="viewport" content="width=device-width, initial-scale=1" />'
            . '<title>403 · Access Restricted</title>'
            . '<script>window.tailwind=window.tailwind||{};tailwind.config={darkMode:"class"};</script>'
            . '<script src="https://cdn.tailwindcss.com"></script>'
            . '<script>(function(){try{if(window.matchMedia&&(window.matchMedia("(prefers-color-scheme: dark)").matches)){document.documentElement.classList.add("dark");}}catch(e){}})();</script>'
            . '</head><body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-900 dark:text-slate-100 grid place-items-center p-6">';
        echo $templates->render('error', [
            'title' => '403 · Access Restricted',
            'heading' => 'Access restricted by ALLOW_IPS',
            'clientIp' => (string)$clientIp,
            // omit allowed IPs from display
            'retryUrl' => (string)($_SERVER['REQUEST_URI'] ?? 'index.php'),
        ]);
        echo '</body></html>';
        exit;
    }
}
$isAuthed = !empty($_SESSION['sampler_authed']);
$authError = null;

require __DIR__ . '/vendor/autoload.php';
use League\Plates\Engine;

// -----------------------------
// Helpers
// -----------------------------
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function pdo_connect(?string $db = null): PDO {
    $host = $_SESSION['conn']['host'] ?? ($_POST['db_host'] ?? 'localhost');
    $port = $_SESSION['conn']['port'] ?? ($_POST['db_port'] ?? '3306');
    $user = $_SESSION['conn']['user'] ?? ($_POST['db_user'] ?? '');
    $pass = $_SESSION['conn']['pass'] ?? ($_POST['db_pass'] ?? '');

    $dsn = $db
        ? "mysql:host={$host};port={$port};dbname=" . $db . ";charset=utf8mb4"
        : "mysql:host={$host};port={$port};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET sql_mode="NO_ENGINE_SUBSTITUTION"',
    ]);
    return $pdo;
}

function get_databases(PDO $pdo): array {
    $stmt = $pdo->query('SHOW DATABASES');
    $all = $stmt->fetchAll(PDO::FETCH_COLUMN);
    // Exclude system schemas
    return array_values(array_filter($all, fn($d) => !in_array($d, [
        'information_schema','mysql','performance_schema','sys'
    ], true)));
}

function get_tables(PDO $pdo, string $db): array {
    $sql = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$db]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function get_fk_relations(PDO $pdo, string $db): array {
    $sql = "SELECT TABLE_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$db]);
    $rels = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $from = $row['TABLE_NAME'];
        $to = $row['REFERENCED_TABLE_NAME'];
        $rels[$from][] = $to;
    }
    return $rels;
}

function get_table_columns(PDO $pdo, string $db, array $tables): array {
    if (empty($tables)) return [];
    $in = implode(',', array_fill(0, count($tables), '?'));
    $params = array_merge([$db], $tables);
    $sql = "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ($in) ORDER BY ORDINAL_POSITION";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[$row['TABLE_NAME']][] = [
            'name' => $row['COLUMN_NAME'],
            'type' => $row['DATA_TYPE'],
            'pk' => strtoupper((string)$row['COLUMN_KEY']) === 'PRI',
        ];
    }
    return $out;
}

function get_primary_key_column(PDO $pdo, string $db, string $table): ?string {
    $sql = "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_KEY = 'PRI' ORDER BY ORDINAL_POSITION LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$db, $table]);
    $col = $stmt->fetchColumn();
    return $col !== false ? (string)$col : null;
}

/**
 * Detect sensitive columns by heuristic column-name matching.
 * Matches: password, pass, pwd, secret, token (case-insensitive, substring).
 * Returns [tableName => [col1, col2, ...]]
 */
function detect_sensitive_columns(PDO $pdo, string $db, array $tables): array {
    if (empty($tables)) return [];
    $columnsByTable = get_table_columns($pdo, $db, $tables);
    $sensitive = [];
    foreach ($tables as $t) {
        foreach (($columnsByTable[$t] ?? []) as $col) {
            $name = strtolower((string)($col['name'] ?? ''));
            if ($name === '') continue;
            if (
                str_contains($name, 'password') ||
                $name === 'pass' || str_contains($name, 'pass') ||
                str_contains($name, 'pwd') ||
                str_contains($name, 'secret') ||
                str_contains($name, 'token') ||
                str_contains($name, 'api_key') ||
                str_contains($name, 'apikey') ||
                str_contains($name, 'api-secret') || str_contains($name, 'apisecret') || str_contains($name, 'api_secret') ||
                str_contains($name, 'auth') || str_contains($name, 'bearer') || str_contains($name, 'jwt') ||
                str_contains($name, 'access') || str_contains($name, 'refresh') ||
                str_contains($name, 'credit') || str_contains($name, 'card') ||
                $name === 'ssn'
            ) {
                $sensitive[$t][] = $col['name'];
            }
        }
    }
    return $sensitive;
}

function build_mermaid_er_graph(array $tables, array $relations, array $columnsByTable): string {
    $lines = ["erDiagram"]; // Mermaid ER diagram
    // Normalize table names to Mermaid-safe identifiers and ensure uniqueness
    $normalize = function(string $name): string {
        $safe = preg_replace('/[^A-Za-z0-9_]/', '_', $name);
        if ($safe === null) { $safe = $name; }
        if ($safe === '' || ctype_digit($safe[0])) { $safe = 'T_' . $safe; }
        return $safe;
    };
    $map = [];
    $used = [];
    foreach ($tables as $t) {
        $base = $normalize($t);
        $candidate = $base;
        $i = 2;
        while (isset($used[$candidate])) { $candidate = $base . '_' . $i++; }
        $used[$candidate] = true;
        $map[$t] = $candidate;
    }
    // Entities
    foreach ($tables as $t) {
        $lines[] = "  " . $map[$t] . " {";
        $cols = $columnsByTable[$t] ?? [];
        if (empty($cols)) {
            // add a placeholder so Mermaid renders the box
            $lines[] = "    id int";
        } else {
            foreach ($cols as $col) {
                $attr = "    " . $col['name'] . " " . $col['type'];
                if (!empty($col['pk'])) { $attr .= " PK"; }
                $lines[] = $attr;
            }
        }
        $lines[] = "  }";
    }
    // Relations
    foreach ($relations as $from => $tos) {
        $fromId = $map[$from] ?? null;
        if (!$fromId) continue;
        foreach ($tos as $to) {
            $toId = $map[$to] ?? null;
            if (!$toId) continue;
            $lines[] = "  " . $fromId . " }o--|| " . $toId . ": FK"; // crow's foot (many-to-one)
        }
    }
    return implode("\n", $lines);
}

function get_table_row_estimates(PDO $pdo, string $db): array {
    $sql = "SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE='BASE TABLE'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$db]);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    // Cast to int; TABLE_ROWS can be null
    foreach ($rows as $k => $v) {
        $rows[$k] = $v !== null ? (int)$v : 0;
    }
    return $rows;
}

function get_database_summary(PDO $pdo, string $db): array {
    try {
        // Get table count and total rows
        $sql = "SELECT 
                    COUNT(*) as table_count,
                    SUM(TABLE_ROWS) as total_rows,
                    SUM(DATA_LENGTH + INDEX_LENGTH) as total_size_bytes,
                    SUM(DATA_LENGTH) as data_size_bytes,
                    SUM(INDEX_LENGTH) as index_size_bytes
                FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = ? AND TABLE_TYPE='BASE TABLE'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$db]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        
        // Get largest tables
        $sql = "SELECT 
                    TABLE_NAME,
                    TABLE_ROWS,
                    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as size_mb
                FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = ? AND TABLE_TYPE='BASE TABLE'
                ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC 
                LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$db]);
        $largestTables = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // Get backup recommendations
        $recommendations = [];
        $totalSizeMB = ($summary['total_size_bytes'] ?? 0) / 1024 / 1024;
        $totalRows = $summary['total_rows'] ?? 0;
        
        if ($totalSizeMB > 500) {
            $recommendations[] = "Large database (" . number_format($totalSizeMB, 1) . "MB) - consider using gzip compression";
        }
        if ($totalRows > 1000000) {
            $recommendations[] = "High row count (" . number_format($totalRows) . ") - backup may take several minutes";
        }
        if (($summary['table_count'] ?? 0) > 50) {
            $recommendations[] = "Many tables (" . ($summary['table_count']) . ") - consider selective backup";
        }
        if (empty($recommendations)) {
            $recommendations[] = "Database size is manageable for standard backup";
        }
        
        return [
            'table_count' => (int)($summary['table_count'] ?? 0),
            'total_rows' => (int)($summary['total_rows'] ?? 0),
            'total_size_mb' => round($totalSizeMB, 2),
            'data_size_mb' => round(($summary['data_size_bytes'] ?? 0) / 1024 / 1024, 2),
            'index_size_mb' => round(($summary['index_size_bytes'] ?? 0) / 1024 / 1024, 2),
            'largest_tables' => $largestTables,
            'recommendations' => $recommendations,
            'estimated_time' => estimate_backup_time($totalSizeMB, $totalRows)
        ];
    } catch (Throwable $ex) {
        // Return a minimal summary if we can't get the full data
        return [
            'table_count' => 0,
            'total_rows' => 0,
            'total_size_mb' => 0,
            'data_size_mb' => 0,
            'index_size_mb' => 0,
            'largest_tables' => [],
            'recommendations' => ['Unable to analyze database - proceeding with backup'],
            'estimated_time' => 'Unknown'
        ];
    }
}

function estimate_backup_time(float $sizeMB, int $totalRows): string {
    // Rough estimation based on size and row count
    if ($sizeMB < 10) return "Less than 30 seconds";
    if ($sizeMB < 100) return "30 seconds to 2 minutes";
    if ($sizeMB < 500) return "2-5 minutes";
    if ($sizeMB < 1000) return "5-10 minutes";
    if ($sizeMB < 5000) return "10-30 minutes";
    return "30+ minutes (consider using gzip)";
}

function create_target_db(PDO $pdo, string $db): void {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `".$db."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

function clone_structure_and_copy_rows(PDO $rootPdo, string $sourceDB, string $targetDB, array $tables, int $limit, bool $orderByPk = false): array {
    $report = [];

    // Use dedicated PDOs pinned to specific DBs
    $src = pdo_connect($sourceDB);
    $tgt = pdo_connect($targetDB);

    // Disable FK checks for duration
    $tgt->exec('SET FOREIGN_KEY_CHECKS = 0');

    foreach ($tables as $table) {
        $row = ['table' => $table, 'created' => false, 'copied' => 0, 'error' => null];
        try {
            // Drop if exists (fresh)
            $tgt->exec("DROP TABLE IF EXISTS `".$table."`");

            // Get CREATE TABLE
            $stmt = $src->query("SHOW CREATE TABLE `".$table."`");
            $create = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$create || empty($create['Create Table'])) {
                throw new RuntimeException('SHOW CREATE TABLE returned empty.');
            }

            // Recreate in target
            $tgt->exec($create['Create Table']);
            $row['created'] = true;

            // Copy rows
            $orderClause = '';
            if ($orderByPk) {
                $pk = get_primary_key_column($src, $sourceDB, $table);
                if ($pk) { $orderClause = " ORDER BY `".$pk."`"; }
            }
            $insertSql = "INSERT INTO `{$targetDB}`.`{$table}` SELECT * FROM `{$sourceDB}`.`{$table}`" . $orderClause . " LIMIT {$limit}";
            $copied = $tgt->exec($insertSql);
            $row['copied'] = (int)$copied;
        } catch (Throwable $ex) {
            $row['error'] = $ex->getMessage();
        }
        $report[] = $row;
    }

    $tgt->exec('SET FOREIGN_KEY_CHECKS = 1');
    return $report;
}

/**
 * After data is copied to target, mask sensitive columns by overwriting values.
 */
function apply_masking(PDO $pdoTarget, string $targetDB, array $tableToColumns): void {
    if (empty($tableToColumns)) return;
    foreach ($tableToColumns as $table => $cols) {
        $cols = array_values(array_unique(array_filter(array_map('strval', $cols))));
        if (empty($cols)) continue;
        // Build SET clause like: `c1`=REPEAT('*',12), `c2`=REPEAT('*',12)
        $sets = [];
        foreach ($cols as $c) {
            $sets[] = "`".$c."`=REPEAT('*', 12)";
        }
        $sql = "UPDATE `".$targetDB."`.`".$table."` SET ".implode(', ', $sets);
        try {
            $pdoTarget->exec($sql);
        } catch (Throwable $ex) {
            // Ignore masking errors per table/column to avoid failing the whole run
        }
    }
}

/**
 * Create a sanitized copy of sourceDB into tempDB (full data) and mask sensitive columns.
 */
function create_sanitized_copy(PDO $rootPdo, string $sourceDB, string $tempDB): array {
    $report = [];
    $src = pdo_connect($sourceDB);
    $tgt = pdo_connect();
    // Create temp database
    $tgt->exec("CREATE DATABASE IF NOT EXISTS `".$tempDB."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdoTemp = pdo_connect($tempDB);

    // Get tables
    $tables = get_tables($src, $sourceDB);

    foreach ($tables as $table) {
        $row = ['table' => $table, 'created' => false, 'copied' => 0, 'error' => null];
        try {
            // Drop table if exists
            $pdoTemp->exec("DROP TABLE IF EXISTS `".$table."`");
            // Recreate structure
            $stmt = $src->query("SHOW CREATE TABLE `".$table."`");
            $create = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$create || empty($create['Create Table'])) {
                throw new RuntimeException('SHOW CREATE TABLE returned empty.');
            }
            $pdoTemp->exec($create['Create Table']);
            $row['created'] = true;

            // Copy all rows
            $insertSql = "INSERT INTO `".$tempDB."`.`".$table."` SELECT * FROM `".$sourceDB."`.`".$table."`";
            $copied = $pdoTemp->exec($insertSql);
            $row['copied'] = (int)$copied;
        } catch (Throwable $ex) {
            $row['error'] = $ex->getMessage();
        }
        $report[] = $row;
    }
    return $report;
}

// -----------------------------
// Request handling
// -----------------------------
$step = (int)($_POST['step'] ?? 1);
$error = null;
$databases = $tables = $report = [];
$rowEstimates = [];
$mermaid = '';

try {
    // Persist DB connection in session (host, port, user, pass) when provided
    if (isset($_POST['db_host'], $_POST['db_port'], $_POST['db_user'])) {
        $h = (string)($_POST['db_host'] ?? 'localhost');
        $p = (string)($_POST['db_port'] ?? '3306');
        $u = (string)($_POST['db_user'] ?? '');
        $pw = array_key_exists('db_pass', $_POST) && $_POST['db_pass'] !== ''
            ? (string)$_POST['db_pass']
            : (string)($_SESSION['conn']['pass'] ?? '');
        $_SESSION['conn'] = ['host' => $h, 'port' => $p, 'user' => $u, 'pass' => $pw];
    }
    // Auth: logout
    if (($_POST['action'] ?? '') === 'logout') {
        if (!csrf_check()) { http_response_code(400); exit('Bad Request'); }
        unset($_SESSION['sampler_authed']);
        header('Location: ' . ($_SERVER['PHP_SELF'] ?? 'index.php'));
        exit;
    }
    // Auth: login
    if (($_POST['action'] ?? '') === 'login') {
        // Rate limiting: 5 attempts per 5 minutes
        $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? [];
        $_SESSION['login_attempts'] = array_values(array_filter($_SESSION['login_attempts'], fn($t) => $t > time() - 300));
        if (count($_SESSION['login_attempts']) >= 5) {
            $authError = 'Too many attempts. Please wait and try again.';
        } elseif (!csrf_check()) {
            $authError = 'Invalid request.';
        } elseif (!isset($_POST['password'])) {
            $authError = 'Please enter your password.';
        } else {
            $pwd = trim((string)($_POST['password'] ?? ''));
            if (hash_equals($CONFIG_PASSWORD, $pwd)) {
                session_regenerate_id(true);
                $_SESSION['sampler_authed'] = true;
                $isAuthed = true;
                $_SESSION['login_attempts'] = [];
            } else {
                $_SESSION['login_attempts'][] = time();
                $authError = 'Invalid password';
            }
        }
    }

    // If auth required and not logged in, render login view early
    if ($REQUIRE_AUTH && !$isAuthed) {
        $templates = new Engine(__DIR__ . '/views');
        $body = $templates->render('login', [
            'error' => $authError,
            'csrf' => csrf_token(),
        ]);
        echo $templates->render('template', [
            'title' => 'SQL PowerTools · Login',
            'body' => $body,
            'authed' => false,
            'error' => $authError,
        ]);
        return;
    }

    // Download sampled SQL (mysqldump of target DB)
    if (($_POST['action'] ?? '') === 'download_sql') {
        if (!csrf_check()) { http_response_code(400); exit('Bad Request'); }
        $sourceDB = $_POST['source_db'] ?? '';
        $targetDB = trim($_POST['target_db'] ?? ($sourceDB . '_sample'));
        $host = $_SESSION['conn']['host'] ?? ($_POST['db_host'] ?? 'localhost');
        $port = $_SESSION['conn']['port'] ?? ($_POST['db_port'] ?? '3306');
        $user = $_SESSION['conn']['user'] ?? ($_POST['db_user'] ?? '');
        $pass = $_SESSION['conn']['pass'] ?? ($_POST['db_pass'] ?? '');
        $filename = $targetDB . '_' . date('Ymd_His') . '.sql';
        while (ob_get_level()) { @ob_end_clean(); }
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        // Resolve mysqldump path: use env MYSQLDUMP_PATH if provided; on Windows try Wamp default locations
        $mysqldump = getenv('MYSQLDUMP_PATH') ?: 'mysqldump';
        if (DIRECTORY_SEPARATOR === '\\' && $mysqldump === 'mysqldump') {
            // Try auto-discover under wamp64\bin\mysql\mysql*\bin
            $candidates = glob('C:\\wamp64\\bin\\mysql\\mysql*\\bin\\mysqldump.exe');
            if (!empty($candidates)) {
                // Pick the last (usually highest version) path
                natsort($candidates);
                $last = array_values($candidates);
                $mysqldump = end($last) ?: $mysqldump;
            }
        }
        // Use a temporary defaults file to avoid exposing password via process args
        $tmpCnf = tempnam(sys_get_temp_dir(), 'mycnf_');
        $cnf = "[client]\nuser={$user}\npassword={$pass}\nhost={$host}\nport={$port}\n";
        file_put_contents($tmpCnf, $cnf);
        $cmd = $mysqldump . ' --defaults-extra-file=' . escapeshellarg($tmpCnf)
            . ' --databases ' . escapeshellarg($targetDB)
            . ' --compact --no-tablespaces';
        $proc = proc_open($cmd, [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, null, null);
        if (is_resource($proc)) {
            while (!feof($pipes[1])) {
                echo fread($pipes[1], 8192);
                flush();
            }
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            proc_close($proc);
            if ($stderr) {
                echo "\n-- mysqldump error: " . str_replace(["\r","\n"], ' ', $stderr) . "\n";
            }
        } else {
            echo "-- Unable to execute mysqldump. Ensure it's in PATH.\n";
        }
        @unlink($tmpCnf);
        exit;
    }
    // Download full backup of source DB (structure + data)
    if (($_POST['action'] ?? '') === 'download_full_backup') {
        if (!csrf_check()) { http_response_code(400); exit('Bad Request'); }
        $sourceDB = $_POST['source_db'] ?? '';
        if (!$sourceDB) { http_response_code(400); exit('Missing source_db'); }
        $host = $_SESSION['conn']['host'] ?? ($_POST['db_host'] ?? 'localhost');
        $port = $_SESSION['conn']['port'] ?? ($_POST['db_port'] ?? '3306');
        $user = $_SESSION['conn']['user'] ?? ($_POST['db_user'] ?? '');
        $pass = $_SESSION['conn']['pass'] ?? ($_POST['db_pass'] ?? '');
        $wantGzip = ($_POST['gzip'] ?? '') === '1';
        $maskSensitive = (($_POST['mask_sensitive'] ?? '') === '1');
        $orderByPk = (($_POST['order_by_pk'] ?? '') === '1');

        $basename = $sourceDB . ($maskSensitive ? '_sanitized_full_' : '_full_') . date('Ymd_His');
        while (ob_get_level()) { @ob_end_clean(); }
        if ($wantGzip) {
            header('Content-Type: application/gzip');
            header('Content-Disposition: attachment; filename=' . $basename . '.sql.gz');
        } else {
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename=' . $basename . '.sql');
        }
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        $mysqldump = getenv('MYSQLDUMP_PATH') ?: 'mysqldump';
        if (DIRECTORY_SEPARATOR === '\\' && $mysqldump === 'mysqldump') {
            $candidates = glob('C:\\wamp64\\bin\\mysql\\mysql*\\bin\\mysqldump.exe');
            if (!empty($candidates)) { natsort($candidates); $last = array_values($candidates); $mysqldump = end($last) ?: $mysqldump; }
        }
        $dumpDb = $sourceDB;
        $tempDbName = '';
        if ($maskSensitive) {
            // Create sanitized temp copy to dump
            $tempDbName = $sourceDB . '_san_tmp_' . substr(bin2hex(random_bytes(4)), 0, 8);
            try {
                create_sanitized_copy(pdo_connect(), $sourceDB, $tempDbName);
                $dumpDb = $tempDbName;
            } catch (Throwable $ex) {
                // Fallback: if sanitization fails, proceed with original DB
                $dumpDb = $sourceDB;
            }
        }

        $tmpCnf = tempnam(sys_get_temp_dir(), 'mycnf_');
        $cnf = "[client]\nuser={$user}\npassword={$pass}\nhost={$host}\nport={$port}\n";
        file_put_contents($tmpCnf, $cnf);

        // If masking is requested, and we created a temp DB, stream a combined dump:
        // 1) Header + schema (routines/triggers, no data) from source DB (no --databases)
        // 2) Data only from sanitized temp DB (no --databases)
        // Otherwise, fall back to dumping the chosen DB normally.

        $writeRaw = function(string $data) use ($wantGzip, &$gzHandle) {
            if ($wantGzip && function_exists('gzopen')) {
                if (!isset($gzHandle)) { $gzHandle = @gzopen('php://output', 'wb9'); }
                if ($gzHandle) { @gzwrite($gzHandle, $data); } else { echo $data; flush(); }
            } else {
                echo $data; flush();
            }
        };

        $streamCmd = function(string $cmd) use (&$writeRaw) {
            $proc = proc_open($cmd, [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, null, null);
            if (is_resource($proc)) {
                while (!feof($pipes[1])) { $chunk = fread($pipes[1], 8192); if ($chunk !== false) { $writeRaw($chunk); } }
                fclose($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                proc_close($proc);
                if ($stderr) { $writeRaw("\n-- mysqldump note: " . str_replace(["\r","\n"], ' ', $stderr) . "\n"); }
                return true;
            }
            $writeRaw("-- Unable to execute: " . $cmd . "\n");
            return false;
        };

        $gzHandle = null;
        if ($maskSensitive && $tempDbName) {
            // Write header and USE statement
            $writeRaw("-- SQL PowerTools combined sanitized dump\n");
            $writeRaw("CREATE DATABASE IF NOT EXISTS `".$sourceDB."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\nUSE `".$sourceDB."`;\n\n");
            // 1) Schema from source (no data)
            $cmdSchema = $mysqldump . ' --defaults-extra-file=' . escapeshellarg($tmpCnf)
                . ' --single-transaction --quick --skip-lock-tables --routines --triggers --hex-blob'
                . ' --no-data ' . escapeshellarg($sourceDB);
            $streamCmd($cmdSchema);
            // 2) Data-only from sanitized DB
            // If user provided overrides, apply them to temp DB before dumping.
            // If no overrides were provided, do NOT mask anything (explicit opt-in).
            $overrides = [];
            foreach ((array)($_POST['mask_cols'] ?? []) as $tbl => $cols) {
                $tbl = (string)$tbl; if ($tbl === '') continue;
                $overrides[$tbl] = array_values(array_unique(array_filter(array_map('strval', (array)$cols))));
            }
            if (!empty($overrides)) {
                try { apply_masking(pdo_connect($tempDbName), $tempDbName, $overrides); } catch (Throwable $ex) {}
            } else {
                // Auto-detect sensitive columns across all tables (default fallback when none selected)
                $allTables = get_tables(pdo_connect($sourceDB), $sourceDB);
                $detected = detect_sensitive_columns(pdo_connect($sourceDB), $sourceDB, $allTables);
                if (!empty($detected)) {
                    try { apply_masking(pdo_connect($tempDbName), $tempDbName, $detected); } catch (Throwable $ex) {}
                }
            }
            $cmdData = $mysqldump . ' --defaults-extra-file=' . escapeshellarg($tmpCnf)
                . ' --single-transaction --quick --skip-lock-tables --hex-blob --no-create-info --skip-triggers '
                . escapeshellarg($tempDbName);
            $streamCmd($cmdData);
            if ($gzHandle) { @gzclose($gzHandle); }
        } else {
            // Standard single DB dump (structure + data)
            $cmd = $mysqldump . ' --defaults-extra-file=' . escapeshellarg($tmpCnf)
                . ' --single-transaction --quick --skip-lock-tables --routines --triggers --hex-blob '
                . escapeshellarg($dumpDb);
            $proc = proc_open($cmd, [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, null, null);
            if (is_resource($proc)) {
                if ($wantGzip && function_exists('gzopen')) {
                    $gzHandle = @gzopen('php://output', 'wb9');
                    if ($gzHandle) {
                        while (!feof($pipes[1])) { @gzwrite($gzHandle, fread($pipes[1], 8192)); }
                        @gzclose($gzHandle);
                    } else {
                        while (!feof($pipes[1])) { echo fread($pipes[1], 8192); flush(); }
                    }
                } else {
                    while (!feof($pipes[1])) { echo fread($pipes[1], 8192); flush(); }
                }
                fclose($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                proc_close($proc);
                if ($stderr) { echo "\n-- mysqldump error: " . str_replace(["\r","\n"], ' ', $stderr) . "\n"; }
            } else {
                echo "-- Unable to execute mysqldump. Ensure it's in PATH.\n";
            }
        }
        @unlink($tmpCnf);
        // Drop temp sanitized DB if created
        if ($maskSensitive && $tempDbName) {
            try { pdo_connect()->exec("DROP DATABASE IF EXISTS `".$tempDbName."`"); } catch (Throwable $ex) {}
        }
        exit;
    }
    // Download structure-only SQL (no data)
    if (($_POST['action'] ?? '') === 'download_sql_structure') {
        if (!csrf_check()) { http_response_code(400); exit('Bad Request'); }
        $sourceDB = $_POST['source_db'] ?? '';
        $targetDB = trim($_POST['target_db'] ?? ($sourceDB . '_sample'));
        $from = ($_POST['from'] ?? 'target') === 'source' ? 'source' : 'target';
        $host = $_SESSION['conn']['host'] ?? ($_POST['db_host'] ?? 'localhost');
        $port = $_SESSION['conn']['port'] ?? ($_POST['db_port'] ?? '3306');
        $user = $_SESSION['conn']['user'] ?? ($_POST['db_user'] ?? '');
        $pass = $_SESSION['conn']['pass'] ?? ($_POST['db_pass'] ?? '');
        $tables = array_filter(array_map('strval', (array)($_POST['tables'] ?? [])));
        $dbDump = $from === 'source' ? ($sourceDB ?: $targetDB) : $targetDB;
        $filename = $dbDump . '_structure_' . date('Ymd_His') . '.sql';
        while (ob_get_level()) { @ob_end_clean(); }
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        $mysqldump = getenv('MYSQLDUMP_PATH') ?: 'mysqldump';
        if (DIRECTORY_SEPARATOR === '\\' && $mysqldump === 'mysqldump') {
            $candidates = glob('C:\\wamp64\\bin\\mysql\\mysql*\\bin\\mysqldump.exe');
            if (!empty($candidates)) { natsort($candidates); $last = array_values($candidates); $mysqldump = end($last) ?: $mysqldump; }
        }
        $tmpCnf = tempnam(sys_get_temp_dir(), 'mycnf_');
        $cnf = "[client]\nuser={$user}\npassword={$pass}\nhost={$host}\nport={$port}\n";
        file_put_contents($tmpCnf, $cnf);
        $cmd = $mysqldump . ' --defaults-extra-file=' . escapeshellarg($tmpCnf)
            . ' --compact --no-tablespaces --no-data --routines --triggers ';
        if (!empty($tables)) {
            // Dump only selected tables' structure
            $cmd .= escapeshellarg($dbDump);
            foreach ($tables as $t) { $cmd .= ' ' . escapeshellarg($t); }
        } else {
            // Dump whole DB structure
            $cmd .= '--databases ' . escapeshellarg($dbDump);
        }
        $proc = proc_open($cmd, [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, null, null);
        if (is_resource($proc)) {
            while (!feof($pipes[1])) { echo fread($pipes[1], 8192); flush(); }
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            proc_close($proc);
            if ($stderr) { echo "\n-- mysqldump error: " . str_replace(["\r","\n"], ' ', $stderr) . "\n"; }
        } else {
            echo "-- Unable to execute mysqldump. Ensure it's in PATH.\n";
        }
        @unlink($tmpCnf);
        exit;
    }
    if ($step === 1) {
        // just show the connection form
    }

    if ($step >= 2) {
        // Validate connection
        $pdo = pdo_connect();
        $databases = get_databases($pdo);
        if (!$databases) throw new RuntimeException('No databases found (or insufficient privileges).');
    }

    if ($step === 3) {
        $sourceDB = $_POST['source_db'] ?? '';
        if (!$sourceDB) throw new RuntimeException('Please select a source database.');
        $pdoDB = pdo_connect($sourceDB);
        $tables = get_tables($pdoDB, $sourceDB);
        if (!$tables) throw new RuntimeException('No base tables found in source DB.');
        // Approximate counts from information_schema (fast, may be approximate on InnoDB)
        $rowEstimates = get_table_row_estimates($pdo, $sourceDB);
        // Sensitive columns preview per table
        $sensitiveColsByTable = detect_sensitive_columns($pdo, $sourceDB, $tables);
        // Get database summary for backup preflight
        $dbSummary = get_database_summary($pdo, $sourceDB);
        // Build Mermaid ER diagram
        if ($DIAGRAM_ENABLED) {
            $relations = get_fk_relations($pdo, $sourceDB);
            $columnsByTable = get_table_columns($pdo, $sourceDB, $tables);
            $mermaid = build_mermaid_er_graph($tables, $relations, $columnsByTable);
        } else {
            $mermaid = '';
        }
    }

    if ($step === 4) {
        if ($READ_ONLY) { throw new RuntimeException('Read-only mode is enabled. Cloning is disabled on this environment.'); }
        $sourceDB = $_POST['source_db'] ?? '';
        $targetDB = trim($_POST['target_db'] ?? ($sourceDB . '_sample'));
        $rowLimit = max(0, (int)($_POST['row_limit'] ?? 50));
        $selectedTables = $_POST['tables'] ?? [];
        $maskSensitive = (($_POST['mask_sensitive'] ?? '') === '1');
        $orderByPk = (($_POST['order_by_pk'] ?? '') === '1');

        if (!$sourceDB) throw new RuntimeException('Missing source database.');
        if ($rowLimit <= 0) throw new RuntimeException('Row limit must be > 0.');

        $pdo = pdo_connect();
        create_target_db($pdo, $targetDB);

        // If no tables were checked, default to ALL tables
        if (empty($selectedTables)) {
            $selectedTables = get_tables(pdo_connect($sourceDB), $sourceDB);
        }

        $report = clone_structure_and_copy_rows($pdo, $sourceDB, $targetDB, $selectedTables, $rowLimit, $orderByPk);
        if ($maskSensitive) {
            try {
                // Only mask when user explicitly selected columns via overrides
                $overrides = [];
                foreach ((array)($_POST['mask_cols'] ?? []) as $tbl => $cols) {
                    $tbl = (string)$tbl; if ($tbl === '') continue;
                    $overrides[$tbl] = array_values(array_unique(array_filter(array_map('strval', (array)$cols))));
                }
                if (!empty($overrides)) {
                    apply_masking(pdo_connect($targetDB), $targetDB, $overrides);
                } elseif (($_POST['mask_autodetect'] ?? '') === '1' || empty($overrides)) {
                    // Auto-detect sensitive columns for selected tables only (default fallback when none selected)
                    $detected = detect_sensitive_columns(pdo_connect($sourceDB), $sourceDB, $selectedTables);
                    if (!empty($detected)) {
                        apply_masking(pdo_connect($targetDB), $targetDB, $detected);
                    }
                }
            } catch (Throwable $ex) {
                // Continue even if masking fails
            }
        }
        // Also compute approximate totals for display in the report
        $rowEstimates = get_table_row_estimates($pdo, $sourceDB);
        // ER diagram for convenience
        if ($DIAGRAM_ENABLED) {
            $relations = get_fk_relations($pdo, $sourceDB);
            $diagramTables = $selectedTables ?: get_tables(pdo_connect($sourceDB), $sourceDB);
            $columnsByTable = get_table_columns($pdo, $sourceDB, $diagramTables);
            $mermaid = build_mermaid_er_graph($diagramTables, $relations, $columnsByTable);
        } else {
            $mermaid = '';
        }
    }
} catch (Throwable $ex) {
    $error = $ex->getMessage();
}

// -----------------------------
// UI (Plates template)
// -----------------------------
$templates = new Engine(__DIR__ . '/views');
$body = $templates->render('app', [
    'step' => $step,
    'error' => $error,
    'databases' => $databases,
    'report' => $report,
    'post' => $_POST,
    'rowEstimates' => $rowEstimates,
    'sensitiveColsByTable' => $sensitiveColsByTable ?? [],
    'mermaid' => $mermaid,
    'diagramEnabled' => $DIAGRAM_ENABLED,
    'authed' => $isAuthed,
    'dbSummary' => $dbSummary ?? null,
    // Admin convenience defaults (no secrets)
    'defaults' => [
        'db_host' => getenv('DEFAULT_DB_HOST') ?: 'localhost',
        'db_port' => getenv('DEFAULT_DB_PORT') ?: '3306',
        'db_user' => getenv('DEFAULT_DB_USER') ?: '',
    ],
]);
echo $templates->render('template', [
    'title' => 'SQL PowerTools',
    'body' => $body,
    'authed' => $isAuthed,
    'error' => $error,
]);
