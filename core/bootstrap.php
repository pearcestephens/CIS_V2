<?php
declare(strict_types=1);

ini_set('display_errors','0');
ini_set('log_errors','1');
error_reporting(E_ALL);
date_default_timezone_set('Pacific/Auckland');

require_once __DIR__ . '/env.php';             // << bring cis_env(), cis_path(), etc.
cis_load_dotenv(cis_path('.env'));   


/**
 * Session: respect existing site settings but ensure we start once.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessName = getenv('SESSION_NAME') ?: ($_ENV['SESSION_NAME'] ?? '');
    if (is_string($sessName) && $sessName !== '') {
        @session_name($sessName);
    }
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    @session_start();
}

/**
 * .env loader (ini style) — non-destructive.
 */
$__envPath = dirname(__DIR__) . '/.env';
if (is_file($__envPath)) {
    $ini = @parse_ini_file($__envPath, false, INI_SCANNER_TYPED) ?: [];
    foreach ($ini as $k => $v) {
        if (!array_key_exists($k, $_ENV)) $_ENV[$k] = (string)$v;
        if (!getenv($k)) putenv($k.'='.$v);
    }
}

if (!function_exists('cis_env')) {
    function cis_env(string $key, ?string $default = null): ?string {
        $v = getenv($key);
        if ($v === false && isset($_ENV[$key])) $v = $_ENV[$key];
        if ($v === false && isset($GLOBALS[$key])) $v = $GLOBALS[$key];
        return ($v === false || $v === null || $v === '') ? $default : (string)$v;
    }
}

function cis_html(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Primary DB handle.
 * NOTE: This mirrors your current version and keeps strict sql_mode.
 */
function db(): \PDO {
    static $pdo = null;
    if ($pdo instanceof \PDO) return $pdo;

    $host = cis_env('DB_HOST', cis_env('MYSQL_HOST','127.0.0.1'));
    $name = cis_env('DB_NAME', cis_env('MYSQL_DATABASE','jcepnzzkmj'));
    $user = cis_env('DB_USER', cis_env('MYSQL_USER','jcepnzzkmj'));
    $pass = cis_env('DB_PASS', cis_env('MYSQL_PASSWORD','wprKh9Jq63'));
    $port = cis_env('DB_PORT', cis_env('MYSQL_PORT','3306'));

    $dsn = "mysql:host={$host}" . ($port ? ";port={$port}" : '') . ";dbname={$name};charset=utf8mb4";
    $pdo = new \PDO($dsn, $user, $pass, [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->query("SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    return $pdo;
}

/**
 * New: cis_request_id() — consistent tracing across new platform.
 */
function cis_request_id(): string {
    static $rid = null;
    if ($rid !== null) return $rid;
    try {
        $rid = bin2hex(random_bytes(16));
    } catch (\Throwable $e) {
        $rid = substr(bin2hex(uniqid('', true)), 0, 32);
    }
    // Prefer inbound request header if provided by upstream (trust only if format matches)
    $in = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
    if (is_string($in) && preg_match('/^[A-Fa-f0-9\-]{16,64}$/', $in)) {
        $rid = str_replace('-', '', $in);
    }
    if (!headers_sent()) {
        header('X-Request-ID: ' . $rid, true);
    }
    return $rid;
}


/**
 * cis_json: unified JSON envelope and exit.
 * { success, data|error, request_id }
 */
function cis_json(bool $success, ?array $data = null, ?array $error = null, int $status = 200): never {
    $rid = function_exists('cis_request_id') ? cis_request_id() : (function(){
        try { return bin2hex(random_bytes(16)); } catch (\Throwable $e) { return substr(bin2hex(uniqid('', true)), 0, 32); }
    })();
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    $payload = ['success' => $success, 'request_id' => $rid];
    if ($success) {
        if ($data !== null) { $payload['data'] = $data; }
    } else {
        $payload['error'] = $error ?: ['code' => 'unknown', 'message' => 'Unknown error'];
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Back-compat wrapper for modules expecting cis_pdo() */
if (!function_exists('cis_pdo')) {
    function cis_pdo(): \PDO { return db(); }
}
