<?php
/**
 * File: bootstrap.php
 * Purpose: Initialise CIS v2 runtime with environment loading, database connection, and session handler.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: PDO extension, core/session/DbSessionHandler.php, core/src classes
 */
declare(strict_types=1);

use CIS\Core\Session\DbSessionHandler;

// Load environment configuration from .env if present.
$envPath = __DIR__ . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

$dsn = getenv('DB_DSN');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');

if ($dsn === false || $dbUser === false || $dbPass === false) {
    throw new RuntimeException('Database credentials are missing from environment.');
}

$pdo = new \PDO(
    $dsn,
    $dbUser,
    $dbPass,
    [
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    ]
);

// --- Sessions: mirror legacy, no surprises ---
$sessionName = getenv('SESSION_NAME') ?: 'CISSESSID';  // must match legacy
session_name($sessionName);

// cookie scope — keep simple; domain usually not needed unless you use subdomains
ini_set('session.cookie_path', '/');

// keep secure flags (adjust SameSite only if you need cross-site iframes)
$sameSite = getenv('SESSION_SAMESITE') ?: 'Lax';  // 'Lax' is safe; use 'None' only if needed
ini_set('session.cookie_samesite', $sameSite);
ini_set('session.cookie_secure', '1');            // HTTPS sites should keep this

// TTL controls server-side expiration; not critical to the cookie name
$ttlMin = (int) (getenv('SESSION_TTL') ?: 1440);
ini_set('session.gc_maxlifetime', (string) ($ttlMin * 60));

require_once __DIR__ . '/core/session/DbSessionHandler.php';
require_once __DIR__ . '/core/src/Response.php';
require_once __DIR__ . '/core/src/Csrf.php';

// If you use your DB session handler, register it here (same code as legacy).
$handler = new DbSessionHandler($pdo, $ttlMin * 60);
session_set_save_handler($handler, true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

define('CIS_BASE', __DIR__);
define('CIS_CORE_PATH', CIS_BASE . '/core');
define('CIS_MODULES_PATH', CIS_BASE . '/modules');
define('CIS_TEMPLATES_PATH', CIS_BASE . '/assets/templates');
define('CIS_TEMPLATE_ACTIVE', getenv('CIS_TEMPLATE_ACTIVE') ?: 'cisv2');
