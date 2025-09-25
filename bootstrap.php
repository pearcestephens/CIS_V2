<?php
declare(strict_types=1);

/**
 * cisv2/bootstrap.php
 * - Loads .env (optional), sets error modes, starts DB + session.
 * - Shares DB + session table with legacy.
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Pacific/Auckland');

define('CISV2_ROOT', __DIR__);
define('CISV2_ENV', getenv('APP_ENV') ?: 'prod');

/** Basic .env loader (no composer needed) */
$envFile = CISV2_ROOT.'/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"'");
        putenv("$k=$v");
        $_ENV[$k] = $v;
    }
}

/** DB connect (shared with legacy) */
$dsn  = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_NAME'));
$user = getenv('DB_USER'); $pass = getenv('DB_PASS');

try {
    $pdo = new \PDO($dsn, $user, $pass, [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE'",
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('DB connect failed: '.$e->getMessage());
    exit('Database connection failed.');
}

/** Sessions: DB-backed, same table + cookie as legacy */
require_once CISV2_ROOT.'/core/session/DbSessionHandler.php';
require_once CISV2_ROOT.'/core/lightspeed.php';

$sessionHandler = new \CISV2\Session\DbSessionHandler($pdo, [
    'table'     => getenv('SESSION_TABLE') ?: 'Session',
    'gc_maxlifetime' => (int)(getenv('SESSION_LIFETIME') ?: 86400),
]);

session_set_save_handler($sessionHandler, true);
session_name('CISSESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

/** Minimal DI container-ish registry */
$GLOBALS['cisv2'] = [
    'pdo'      => $pdo,
    'env'      => $_ENV,
    'start_ts' => microtime(true),
];

if (!function_exists('cisv2_lightspeed')) {
    /**
     * Retrieve a shared LightspeedClient instance.
     */
    function cisv2_lightspeed(): LightspeedClient
    {
        static $client;
        if ($client instanceof LightspeedClient) {
            return $client;
        }

        $base = getenv('LS_BASE_URL') ?: '';
        $token = getenv('LS_TOKEN') ?: '';
        if ($base === '' || $token === '') {
            throw new \RuntimeException('Lightspeed credentials missing (LS_BASE_URL / LS_TOKEN).');
        }

        $client = new LightspeedClient($base, $token);
        return $client;
    }
}

if (!function_exists('cisv2_queue_health')) {
    /**
     * Retrieve queue service health JSON.
     */
    function cisv2_queue_health(): array
    {
        static $cached;
        if ($cached !== null) {
            return $cached;
        }

        $path = '/assets/services/queue/public/health.php';
        $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_ENV['APP_URL_HOST'] ?? 'localhost');
        $url = $scheme . '://' . $host . $path;

        try {
            $context = stream_context_create([
                'http' => [
                    'method'        => 'GET',
                    'timeout'       => 3,
                    'ignore_errors' => true,
                    'header'        => "Accept: application/json\r\nUser-Agent: CISV2-QueueHealth/1.0\r\n",
                ],
            ]);
            $raw = @file_get_contents($url, false, $context);
            if ($raw === false) {
                throw new \RuntimeException('Queue health fetch failed');
            }
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                throw new \RuntimeException('Queue health response invalid');
            }
            $cached = $data;
        } catch (\Throwable $e) {
            error_log('Queue health error: ' . $e->getMessage());
            $cached = ['ok' => false, 'error' => 'unreachable'];
        }

        return $cached;
    }
}

if (!function_exists('cisv2_queue_enqueue')) {
    /**
     * Insert a queue job with idempotency protection.
     */
    function cisv2_queue_enqueue(string $type, array $payload, array $options = []): bool
    {
        $pdo = $GLOBALS['cisv2']['pdo'] ?? null;
        if (!$pdo instanceof \PDO) {
            return false;
        }

        $table = $options['table'] ?? (getenv('QUEUE_TABLE') ?: 'queue_jobs');
        $idKey = hash('sha256', $type . '|' . json_encode($payload));

        try {
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO `{$table}` (idempotency_key, job_type, payload_json, status, created_at)
                 VALUES (:key, :type, :payload, 'queued', NOW())"
            );

            return $stmt->execute([
                ':key'     => $idKey,
                ':type'    => $type,
                ':payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            ]);
        } catch (\Throwable $e) {
            error_log('Queue enqueue error: ' . $e->getMessage());
            return false;
        }
    }
}

require_once CISV2_ROOT.'/core/layout.php';
