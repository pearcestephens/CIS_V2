<?php
declare(strict_types=1);
/**
 * https://staff.vapeshed.co.nz/modules/migrations/inspect_schema.php
 * Read-only schema inspector to list existing tables, columns, and key indexes
 * relevant to the current migration set. Requires admin session or internal token (non-prod).
 */

// Ensure DOCUMENT_ROOT for CLI
if (php_sapi_name() === 'cli' && empty($_SERVER['DOCUMENT_ROOT'])) {
  $_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../') ?: __DIR__ . '/../../';
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
header('Content-Type: text/plain; charset=utf-8');

// Safety: only allow admins or internal token in non-prod
if (!isset($_SESSION)) { session_start(); }
$env = strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? ''));
$isNonProd = !in_array($env, ['prod','production','live'], true);
$expectedToken = (string)($_ENV['INTERNAL_API_TOKEN'] ?? getenv('INTERNAL_API_TOKEN') ?: '');
$isAdmin = (!empty($_SESSION['userID']) && $_SESSION['userID'] == 1);

// Allow:
// - HTTP non-prod with matching X-Internal-Token header
// - HTTP admin session
// - CLI with matching INTERNAL_API_TOKEN env (even in prod)
$internalHeader = $_SERVER['HTTP_X_INTERNAL_TOKEN'] ?? '';
$internalHTTP = $isNonProd && $internalHeader && $expectedToken && hash_equals($expectedToken, (string)$internalHeader);
$internalCLI = (php_sapi_name() === 'cli') && $expectedToken && hash_equals($expectedToken, (string)($internalHeader ?: getenv('INTERNAL_API_TOKEN') ?: ''));

if (!$internalHTTP && !$isAdmin && !$internalCLI) {
  http_response_code(403);
  $uid = $_SESSION['userID'] ?? '';
  $role = $_SESSION['role'] ?? ($_SESSION['userRole'] ?? '');
  $method = php_sapi_name() === 'cli' ? 'CLI' : 'HTTP';
  echo "Forbidden — need admin (admin/owner/director) OR X-Internal-Token (non-prod) OR CLI with INTERNAL_API_TOKEN\n";
  echo "Detected: method=$method env=$env admin=" . ($isAdmin?'yes':'no') . " role=" . ($role!==''?$role:'(none)') . " userID=" . ($uid!==''?$uid:'(none)') . "\n";
  exit;
}

if (!function_exists('cis_pdo')) { echo "cis_pdo() not available\n"; exit; }
$pdo = cis_pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function existsTable(PDO $pdo, string $table): bool {
  try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
  } catch (Throwable $e) { return false; }
}

function existsColumn(PDO $pdo, string $table, string $column): bool {
  try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
  } catch (Throwable $e) { return false; }
}

function existsIndex(PDO $pdo, string $table, string $index): bool {
  try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
  } catch (Throwable $e) { return false; }
}

// DB version info
try {
  $ver = $pdo->query('SELECT VERSION() AS v')->fetch(PDO::FETCH_ASSOC)['v'] ?? '';
  echo "DB VERSION: ".$ver."\n\n";
} catch (Throwable $e) { echo "DB VERSION: (unknown)\n\n"; }

// Snapshot core tables affected/created by the migration set
$tables = [
  'schema_migrations',
  'idempotency_keys',
  'transfer_receipts',
  'transfer_receipt_items',
  'outlet_courier_tokens',
  'transfer_carrier_orders',
  'print_jobs',
  'container_rules',
  // existing domain tables we touch or read
  'transfers',
  'transfer_items',
  'transfer_logs',
  'transfer_audit_log',
  'transfer_parcels',
];

echo "TABLES (present=✓ / missing=×)\n";
foreach ($tables as $t) {
  $ok = existsTable($pdo, $t);
  echo sprintf("  %s %s\n", $ok ? '✓' : '×', $t);
}

// Column checks of interest
$colChecks = [
  ['transfer_parcels','weight_kg'],
];
echo "\nCOLUMNS\n";
foreach ($colChecks as [$t,$c]) {
  $ok = existsColumn($pdo, $t, $c);
  echo sprintf("  %s %s.%s\n", $ok ? '✓' : '×', $t, $c);
}

// Index checks we plan to create
$idxChecks = [
  ['transfer_logs',       'idx_logs_transfer_created'],
  ['transfer_logs',       'idx_logs_event_created'],
  ['transfer_audit_log',  'idx_audit_transfer_created'],
  ['transfer_audit_log',  'idx_audit_action_created'],
];
echo "\nINDEXES\n";
foreach ($idxChecks as [$t,$i]) {
  $ok = existsIndex($pdo, $t, $i);
  echo sprintf("  %s %s.%s\n", $ok ? '✓' : '×', $t, $i);
}

echo "\nDone.\n";
