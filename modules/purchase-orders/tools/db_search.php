<?php
/**
 * https://staff.vapeshed.co.nz/modules/purchase-orders/tools/db_search.php
 * Purpose: CLI tool to search your MySQL schema (tables, columns, foreign keys) by pattern.
 * Author: CIS Developer Bot
 * Last Modified: 2025-09-21
 * Usage:
 *   php modules/purchase-orders/tools/db_search.php --pattern=vend_%%
 *   php modules/purchase-orders/tools/db_search.php --pattern=product --schema=<DB_NAME>
 * Notes:
 *   - Defaults to current DB from config.php
 *   - Pattern applies to table and column names using SQL LIKE (use % wildcards)
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

@require_once dirname(__FILE__, 4) . '/config.php';

if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
    echo "ERROR: DB config not found. Expected constants DB_HOST, DB_NAME, DB_USER, DB_PASS.\n";
    exit(2);
}

// Parse args
$args = [ 'pattern' => 'vend_%', 'schema' => DB_NAME ];
foreach ($argv as $a) {
    if (strpos($a, '--pattern=') === 0) { $args['pattern'] = substr($a, 10); }
    if (strpos($a, '--schema=') === 0) { $args['schema']  = substr($a, 9); }
}

$pattern = (string)$args['pattern'];
$schema  = (string)$args['schema'];

echo "DB Search Tool\n";
echo "Schema: {$schema}\n";
echo "Pattern: {$pattern}\n";
echo str_repeat('-', 60) . "\n";

try {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    echo "ERROR: Cannot connect: " . $e->getMessage() . "\n"; exit(3);
}

// Helper to run a prepared query
function q(PDO $pdo, string $sql, array $params): array { $st = $pdo->prepare($sql); $st->execute($params); return $st->fetchAll(); }

// Tables matching
$tables = q($pdo, "SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE ? ORDER BY TABLE_NAME", [$schema, $pattern]);
// Columns matching (either table or column name)
$cols = q($pdo, "SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND (TABLE_NAME LIKE ? OR COLUMN_NAME LIKE ?) ORDER BY TABLE_NAME, ORDINAL_POSITION", [$schema, $pattern, $pattern]);
// Foreign keys involving matching tables
$fks = q($pdo, "SELECT TABLE_SCHEMA, TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL AND (TABLE_NAME LIKE ? OR REFERENCED_TABLE_NAME LIKE ?) ORDER BY TABLE_NAME, CONSTRAINT_NAME", [$schema, $pattern, $pattern]);

// Output
echo "[TABLES] (".count($tables).")\n";
foreach ($tables as $t) {
    $cmt = $t['TABLE_COMMENT'] ? (' -- '.$t['TABLE_COMMENT']) : '';
    echo sprintf("- %s.%s%s\n", $t['TABLE_SCHEMA'], $t['TABLE_NAME'], $cmt);
}

echo "\n[COLUMNS] (".count($cols).")\n";
foreach ($cols as $c) {
    $cmt = $c['COLUMN_COMMENT'] ? (' -- '.$c['COLUMN_COMMENT']) : '';
    echo sprintf("- %s.%s.%s : %s%s\n", $c['TABLE_SCHEMA'], $c['TABLE_NAME'], $c['COLUMN_NAME'], $c['COLUMN_TYPE'], $cmt);
}

echo "\n[FOREIGN KEYS] (".count($fks).")\n";
foreach ($fks as $k) {
    echo sprintf("- %s.%s (%s) %s -> %s(%s)\n", $k['TABLE_SCHEMA'], $k['TABLE_NAME'], $k['CONSTRAINT_NAME'], $k['COLUMN_NAME'], $k['REFERENCED_TABLE_NAME'], $k['REFERENCED_COLUMN_NAME']);
}

echo "\nDone.\n";
