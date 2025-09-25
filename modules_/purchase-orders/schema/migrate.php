<?php
/**
 * https://staff.vapeshed.co.nz/modules/purchase-orders/schema/migrate.php
 * Purpose: Apply PO schema SQL files in order. Echoes progress for easy debugging.
 * Author: CIS Developer Bot
 * Last Modified: 2025-09-21
 * Dependencies: requires config.php for DB constants.
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$root = __DIR__;
require_once dirname(__DIR__, 2) . '/_shared/bootstrap.php';

if (!cis_has_db()) {
    echo "ERROR: DB config not found or not reachable (app.php/config.php).\n"; exit(2);
}

// Prefer PDO but fall back to mysqli
$pdo = null; $mysqli = null;
try { $pdo = cis_pdo(); } catch (Throwable $e) { $mysqli = cis_mysqli(); }

$files = [
    '001_po_core.sql',
    '002_inventory_adjust_requests.sql',
    '003_po_events_receipts.sql',
    '004_optional_vend_shims.sql',
];

foreach ($files as $f) {
    $path = $root . '/' . $f;
    if (!file_exists($path)) { echo "WARN: missing $f, skipping\n"; continue; }
    echo "Applying $f ... ";
    try {
        cis_exec_sql_file($path, $pdo, $mysqli);
        echo "OK\n";
    } catch (Throwable $e) {
        echo "FAIL: ".$e->getMessage()."\n";
    }
}

echo "Done.\n";
