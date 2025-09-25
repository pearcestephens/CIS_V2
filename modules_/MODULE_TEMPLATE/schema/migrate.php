<?php
declare(strict_types=1);
/**
 * migrate.php — __MODULE_NAME__ schema migrator (idempotent)
 * URL: https://staff.vapeshed.co.nz/modules/__MODULE_SLUG__/schema/migrate.php
 */

header('Content-Type: text/plain; charset=utf-8');
require_once dirname(__DIR__, 2) . '/_shared/bootstrap.php';

if (!cis_has_db()) {
    http_response_code(500);
    echo "DB config not found or unreachable. See /modules/_shared/diagnostics.php.\n";
    exit(1);
}

$pdo = null; $mysqli = null;
try { $pdo = cis_pdo(); } catch (Throwable $e) { $mysqli = cis_mysqli(); }

$files = [ '001_core.sql' ];
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) { echo "Skip missing: $file\n"; continue; }
    try {
        cis_exec_sql_file($path, $pdo, $mysqli);
        echo "Applied: $file\n";
    } catch (Throwable $e) {
        echo "Failed: $file => " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
