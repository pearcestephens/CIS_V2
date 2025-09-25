<?php
declare(strict_types=1);
/**
 * tools.php — Shared helpers for __MODULE_NAME__ (modules/__MODULE_SLUG__)
 * Purpose: Bootstrap JSON responses, CSRF/auth checks, and PDO access.
 * Author: CIS Dev Bot
 * Last Modified: 2025-09-21
 * Dependencies: https://staff.vapeshed.co.nz/app.php
 */

// Always bootstrap the application (sessions, config, autoloaders)
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

// If DB_* constants aren't defined by app.php, attempt fallback include
if (!defined('DB_HOST')) {
    $fallback = $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/config.php';
    if (file_exists($fallback)) {
        require_once $fallback;
    }
}

header('Content-Type: application/json; charset=utf-8');

/**
 * Generate a request ID for tracing
 */
function mt_request_id(): string {
    return bin2hex(random_bytes(12));
}

/**
 * Standard JSON envelope
 * @param bool $success
 * @param mixed $payload
 * @param array $meta
 */
function mt_json(bool $success, $payload = null, array $meta = []): void {
    $rid = $meta['request_id'] ?? ($_SERVER['HTTP_X_REQUEST_ID'] ?? mt_request_id());
    $resp = [
        'success' => $success,
        $success ? 'data' : 'error' => $payload,
        'request_id' => $rid,
    ];
    if (!headers_sent()) {
        http_response_code($success ? 200 : 400);
    }
    echo json_encode($resp, JSON_UNESCAPED_SLASHES);
}

/** Verify user is logged in via app.php's session */
function mt_require_login(): void {
    if (empty($_SESSION['user_id'])) {
        mt_json(false, ['code' => 'AUTH_REQUIRED', 'message' => 'Login required']);
        exit;
    }
}

/** CSRF verification — expects X-CSRF-Token header to match session token */
function mt_verify_csrf(): void {
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $token = $_SESSION['csrf_token'] ?? '';
    if (!$token || !$header || !hash_equals($token, $header)) {
        mt_json(false, ['code' => 'CSRF_FAIL', 'message' => 'Invalid CSRF token']);
        exit;
    }
}

/** Create PDO instance using DB_* constants from app.php/config.php */
function mt_pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
        throw new RuntimeException('Database constants not defined. Ensure app.php or config.php is loaded.');
    }
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='STRICT_ALL_TABLES'",
    ]);
    $pdo->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");
    return $pdo;
}

/** Simple table existence check */
function mt_table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $stmt->execute([DB_NAME, $table]);
    return (int)$stmt->fetchColumn() > 0;
}

/** Retry wrapper for deadlocks/timeouts */
function mt_retry(callable $fn, int $attempts = 3, int $sleepMs = 150): mixed {
    $tries = 0;
    start:
    try {
        return $fn();
    } catch (Throwable $e) {
        $tries++;
        if ($tries >= $attempts) {
            throw $e;
        }
        usleep($sleepMs * 1000);
        goto start;
    }
}
