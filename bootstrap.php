<?php
declare(strict_types=1);

/**
 * cisv2/bootstrap.php
 * Unified bootstrap for CISV2:
 * - Prefer legacy /app.php bootstrap (DB, session), otherwise .env + PDO.
 * - DB-backed session handler (shared table + cookie).
 * - Consistent db_ro()/db_rw() (PDO).
 * - Lightspeed accessor, queue health probe, idempotent queue enqueue.
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Pacific/Auckland');

define('CISV2_ROOT', __DIR__);
define('CISV2_ENV', getenv('APP_ENV') ?: 'prod');

///////////////////////////////
// 0) Small utilities
///////////////////////////////
if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string {
        $v = $_ENV[$key] ?? getenv($key);
        if ($v === false || $v === null || $v === '') return $default;
        return $v;
    }
}

///////////////////////////////
// 1) Try legacy /app.php first
///////////////////////////////
$DOCROOT = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__), '/');
$legacyApp = $DOCROOT . '/app.php';
$legacyLoaded = false;

if (is_file($legacyApp)) {
    // If your legacy app defines error handlers, leave them; CISV2 is defensive anyway.
    require_once $legacyApp;
    $legacyLoaded = true;
}

///////////////////////////////
// 2) .env loader (always allowed; may augment legacy)
///////////////////////////////
$envFile = CISV2_ROOT.'/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"'");
        putenv("$k=$v");
        $_ENV[$k] = $v;
    }
}

///////////////////////////////
// 3) Database (PDO) – prefer legacy, else build from .env
///////////////////////////////
/** @var PDO|null $__cisv2_pdo */
$__cisv2_pdo = null;

// (a) If legacy exposed a PDO, adopt it
if (isset($pdo) && $pdo instanceof PDO) {
    $__cisv2_pdo = $pdo;
}

// (b) If legacy exposed a mysqli, wrap or rebuild a PDO with same credentials if known
if (!$__cisv2_pdo && isset($mysqli) && $mysqli instanceof mysqli) {
    // Try to infer DSN from environment; otherwise create PDO from env keys
    $host = env('DB_HOST', '127.0.0.1');
    $name = env('DB_NAME', '');
    $user = env('DB_USER_RW') ?? env('DB_USER') ?? env('DB_USER_RO');
    $pass = env('DB_PASS_RW') ?? env('DB_PASS') ?? env('DB_PASS_RO');
    if ($name && $user !== null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);
        try {
            $__cisv2_pdo = new PDO($dsn, $user, (string)$pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE'",
            ]);
        } catch (Throwable $e) {
            error_log('CISV2 PDO wrap-from-mysqli failed: '.$e->getMessage());
        }
    }
}

// (c) If still no PDO, build from .env only
if (!$__cisv2_pdo) {
    $host = env('DB_HOST', '127.0.0.1');
    $name = env('DB_NAME', '');
    $user = env('DB_USER_RW') ?? env('DB_USER') ?? env('DB_USER_RO');
    $pass = env('DB_PASS_RW') ?? env('DB_PASS') ?? env('DB_PASS_RO');

    if (!$name || $user === null) {
        http_response_code(500);
        error_log('DB config missing (DB_HOST/DB_NAME/DB_USER* not set).');
        exit('Database configuration missing.');
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);
    try {
        $__cisv2_pdo = new PDO($dsn, $user, (string)$pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE'",
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        error_log('DB connect failed: '.$e->getMessage());
        exit('Database connection failed.');
    }
}

// Canonical DB accessors (PDO). You can keep using $GLOBALS['cisv2']['pdo'] too.
if (!function_exists('db_ro')) {
    function db_ro(): PDO {
        global $__cisv2_pdo;
        if (!$__cisv2_pdo) throw new RuntimeException('DB not initialized (RO).');
        return $__cisv2_pdo;
    }
}
if (!function_exists('db_rw')) {
    function db_rw(): PDO {
        global $__cisv2_pdo;
        if (!$__cisv2_pdo) throw new RuntimeException('DB not initialized (RW).');
        return $__cisv2_pdo;
    }
}

// Back-compat shorthand used by many legacy helpers
if (!function_exists('db')) {
    function db(): PDO {
        return db_rw();
    }
}

///////////////////////////////
// 4) Sessions – DB-backed, shared with legacy
///////////////////////////////
require_once CISV2_ROOT.'/core/session/DbSessionHandler.php';
$sessionTable   = env('SESSION_TABLE', 'Session');
$sessionMaxLife = (int)env('SESSION_LIFETIME', '86400');

$handler = new \CISV2\Session\DbSessionHandler(db_rw(), [
    'table'          => $sessionTable,
    'gc_maxlifetime' => $sessionMaxLife,
]);

// Only override if legacy didn’t already start and wire a handler
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_save_handler($handler, true);
    if (ini_get('session.name') !== 'CISSESSID') {
        session_name('CISSESSID');
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    @session_start();
}

///////////////////////////////
// 5) Lightspeed (Vend) client accessor
///////////////////////////////
require_once CISV2_ROOT.'/core/lightspeed.php';

if (!function_exists('cisv2_lightspeed')) {
    function cisv2_lightspeed(): LightspeedClient
    {
        static $client;
        if ($client instanceof LightspeedClient) return $client;

        $base  = env('LS_BASE_URL', '');
        $token = env('LS_TOKEN', '');
        if ($base === '' || $token === '') {
            throw new RuntimeException('Lightspeed credentials missing (LS_BASE_URL / LS_TOKEN).');
        }
        $client = new LightspeedClient($base, $token);
        return $client;
    }
}

///////////////////////////////
// 6) Queue health probe (HTTP GET)
///////////////////////////////
if (!function_exists('cisv2_queue_health')) {
    function cisv2_queue_health(): array
    {
        static $cached = null;
        if ($cached !== null) return $cached;

        $path   = env('QUEUE_HEALTH_PATH', '/assets/services/queue/public/health.php');
        $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string)($_SERVER['HTTPS'])) !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? (env('APP_URL_HOST', 'localhost'));
        $url    = $scheme . '://' . $host . $path;

        try {
            $ctx = stream_context_create([
                'http' => [
                    'method'        => 'GET',
                    'timeout'       => 3,
                    'ignore_errors' => true,
                    'header'        => "Accept: application/json\r\nUser-Agent: CISV2-QueueHealth/1.0\r\n",
                ],
            ]);
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) throw new RuntimeException('Queue health fetch failed');
            $data = json_decode($raw, true);
            if (!is_array($data)) throw new RuntimeException('Queue health response invalid');
            $cached = $data;
        } catch (Throwable $e) {
            error_log('Queue health error: '.$e->getMessage());
            $cached = ['ok' => false, 'error' => 'unreachable'];
        }
        return $cached;
    }
}

///////////////////////////////
// 7) Queue enqueue helper (idempotent)
///////////////////////////////
if (!function_exists('cisv2_queue_enqueue')) {
    /**
     * Insert a queue job with idempotency protection.
     * @param string $type     pipeline key, e.g. 'transfer.finalized'
     * @param array  $payload  JSON-serializable payload
     * @param array  $options  ['table'=>'queue_jobs','ref_id'=>string,'priority'=>int,'max_attempts'=>int,'available_at'=>Y-m-d H:i:s]
     */
    function cisv2_queue_enqueue(string $type, array $payload, array $options = []): bool
    {
        $pdo = db_rw();
        $table       = $options['table'] ?? env('QUEUE_TABLE', 'queue_jobs');
        $refId       = $options['ref_id'] ?? null;
        $priority    = (int)($options['priority'] ?? 5);
        $maxAttempts = (int)($options['max_attempts'] ?? 8);
        $availableAt = $options['available_at'] ?? date('Y-m-d H:i:s');

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $idem = hash('sha256', $type.'|'.(string)$refId.'|'.$payloadJson);

        $sql = "INSERT IGNORE INTO `{$table}`
                (idempotency_key, job_type, ref_id, payload_json, status, attempts, max_attempts, priority, available_at, created_at)
                VALUES (:key, :type, :ref, :payload, 'queued', 0, :maxa, :prio, :avail, NOW())";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                ':key'     => $idem,
                ':type'    => $type,
                ':ref'     => $refId,
                ':payload' => $payloadJson,
                ':maxa'    => $maxAttempts,
                ':prio'    => $priority,
                ':avail'   => $availableAt,
            ]);
        } catch (Throwable $e) {
            error_log('Queue enqueue error: '.$e->getMessage());
            return false;
        }
    }
}

///////////////////////////////
// 8) Auth & layout shims (only if not provided by legacy)
///////////////////////////////
if (!function_exists('cis_require_login')) {
    function cis_require_login(): void {
        // Allow read-only GET/HEAD when bot-bypass flags exist (optional)
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' && isset($_GET['bb'])) {
            return;
        }
        if (empty($_SESSION['user_id'])) {
            http_response_code(302);
            header('Location: /login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/'));
            exit;
        }
    }
}

if (!function_exists('cis_render_layout')) {
    function cis_render_layout(array $meta, string $content): void {
        $baseTpl = CISV2_ROOT . '/assets/templates/cisv2';
        if (!is_dir($baseTpl)) {
            $legacyBase = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/assets/templates/cisv2';
            if (is_dir($legacyBase)) {
                $baseTpl = $legacyBase;
            }
        }
        require $baseTpl . '/html-header.php';
        require $baseTpl . '/header.php';
        require $baseTpl . '/sidemenu.php';
        echo $content;
        require $baseTpl . '/footer.php';
        require $baseTpl . '/html-footer.php';
    }
}

///////////////////////////////
// 9) Registry (optional)
///////////////////////////////
$GLOBALS['cisv2'] = [
    'pdo'      => db_ro(),
    'env'      => $_ENV,
    'start_ts' => microtime(true),
];

// Keep at end: ensure layout utilities are loaded by default for controllers
require_once CISV2_ROOT.'/core/layout.php';
