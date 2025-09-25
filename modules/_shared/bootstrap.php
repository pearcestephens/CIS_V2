<?php
declare(strict_types=1);
/**
 * modules/_shared/bootstrap.php — Robust bootstrap for modules
 * - Locates app.php and legacy config in multiple paths
 * - Exposes cis_pdo() and cis_mysqli() helpers
 * - Provides cis_exec_sql_file() for PDO or mysqli
 */

// Try to include app.php via common paths
(function(){
    $tried = [];
    $paths = [];
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $paths[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/app.php';
        $paths[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/assets/functions/config.php';
        $paths[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/config.php';
    }
    // derive from this file: /public_html/modules/_shared/bootstrap.php
    $here = __FILE__;
    $paths[] = dirname($here, 3) . '/app.php'; // up to public_html
    $paths[] = dirname($here, 3) . '/assets/functions/config.php';
    $paths[] = dirname($here, 3) . '/config.php';

    foreach ($paths as $p) {
        if ($p && is_file($p)) {
            $tried[] = $p;
            @require_once $p;
        }
    }

    // Prefer canonical PDO provider if present to avoid duplicate declarations later
    if (!function_exists('cis_pdo')) {
        $root = !empty($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') : dirname($here, 3);
        $candidates = [
            $root . '/assets/functions/pdo.php',
            $root . '/assets/services/queue/cis/pdo_adapter.php',
        ];
        foreach ($candidates as $c) {
            if (is_file($c)) { @require_once $c; }
            if (function_exists('cis_pdo')) break;
        }
    }
})();

/** Return PDO if possible, else throw (defined only if no canonical provider loaded) */
if (!function_exists('cis_pdo')) {
    function cis_pdo(): PDO {
        static $pdo = null;
        if ($pdo instanceof PDO) return $pdo;
        // Env fallback
        $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: null);
        $name = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: null);
        $user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: null);
        $pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: null);
        if (!$host || !$name || !$user) {
            throw new RuntimeException('DB constants not defined and env missing');
        }
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);
        $pdo = new PDO($dsn, $user, $pass ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");
        return $pdo;
    }
}

/** Return mysqli if available ($con from legacy or create new if constants exist) */
if (!function_exists('cis_mysqli')) {
    function cis_mysqli(): ?mysqli {
        if (isset($GLOBALS['con']) && $GLOBALS['con'] instanceof mysqli) {
            return $GLOBALS['con'];
        }
        $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: null);
        $name = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: null);
        $user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: null);
        $pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: null);
        if (!$host || !$name || !$user) return null;
        $m = @new mysqli($host, $user, $pass ?? '', $name);
        if ($m && !$m->connect_errno) return $m;
        return null;
    }
}

/** Execute a .sql file with either PDO (preferred) or mysqli (fallback) */
function cis_exec_sql_file(string $file, ?PDO $pdo = null, ?mysqli $m = null): void {
    if (!is_file($file)) throw new RuntimeException('SQL file not found: ' . $file);
    $sql = file_get_contents($file);
    if ($pdo instanceof PDO) {
        $pdo->exec($sql);
        return;
    }
    if ($m instanceof mysqli) {
        if (!$m->multi_query($sql)) {
            throw new RuntimeException('MySQLi error: ' . $m->error);
        }
        // flush remaining result sets
        while ($m->more_results() && $m->next_result()) { /* drain */ }
        return;
    }
    throw new RuntimeException('No DB driver available');
}

function cis_has_db(): bool {
    try {
        cis_pdo(); return true;
    } catch (Throwable $e) {
        return cis_mysqli() instanceof mysqli;
    }
}
