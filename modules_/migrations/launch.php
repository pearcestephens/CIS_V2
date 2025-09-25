<?php
declare(strict_types=1);
/**
 * https://staff.vapeshed.co.nz/modules/migrations/launch.php
 * Helper that prints current schema snapshot, then invokes run.php to apply pending migrations.
 * Uses the same admin/internal token gating as run.php. Outputs plain text.
 */

if (php_sapi_name() === 'cli' && empty($_SERVER['DOCUMENT_ROOT'])) {
	$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../') ?: __DIR__ . '/../../';
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
header('Content-Type: text/plain; charset=utf-8');

if (!isset($_SESSION)) { session_start(); }
$env = strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? ''));
$isNonProd = !in_array($env, ['prod','production','live'], true);
$internalToken = $_SERVER['HTTP_X_INTERNAL_TOKEN'] ?? '';
$expectedToken = (string)($_ENV['INTERNAL_API_TOKEN'] ?? getenv('INTERNAL_API_TOKEN') ?: '');
$internalOK = $isNonProd && $internalToken && $expectedToken && hash_equals($expectedToken, (string)$internalToken);
$internalCLI = (php_sapi_name() === 'cli') && ( $isNonProd || ( $expectedToken && hash_equals($expectedToken, (string)(getenv('INTERNAL_API_TOKEN') ?: '')) ) );
$isAdmin = (!empty($_SESSION['userID']) && $_SESSION['userID'] == 1);
if (!$internalOK && !$isAdmin && !$internalCLI) { http_response_code(403); echo "Forbidden\n"; exit; }

// Helper: snapshot printer (no external include to avoid redeclare)
/**
 * Execute a COUNT(*) style existence query safely.
 *
 * @param PDO $pdo Active PDO connection
 * @param string $sql Parameterized SQL expected to return a single COUNT(*)
 * @param array<int,mixed> $params Parameters to bind
 * @return bool True if COUNT(*) > 0, false on zero or any error
 */
function mig_exists(PDO $pdo, string $sql, array $params): bool {
	try { $st=$pdo->prepare($sql); $st->execute($params); return (int)$st->fetchColumn() > 0; } catch (Throwable $e) { return false; }
}
/**
 * Print a concise schema snapshot of tables, key columns, and indexes.
 *
 * @param PDO $pdo Active PDO connection
 * @return void
 */
function mig_snapshot(PDO $pdo): void {
	try { $ver = $pdo->query('SELECT VERSION()')->fetchColumn(); } catch (Throwable $e) { $ver='(unknown)'; }
	echo "=== SCHEMA SNAPSHOT ===\n";
	echo "DB VERSION: $ver\n\n";
	$tables = [
		'schema_migrations','idempotency_keys','transfer_receipts','transfer_receipt_items',
		'outlet_courier_tokens','transfer_carrier_orders','print_jobs','container_rules',
		'transfers','transfer_items','transfer_logs','transfer_audit_log','transfer_parcels','transfer_discrepancies',
	];
	echo "TABLES (present=✓ / missing=×)\n";
	foreach ($tables as $t) {
		$ok = mig_exists($pdo,'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$t]);
		echo '  '.($ok?'✓':'×').' '.$t."\n";
	}
	echo "\nCOLUMNS\n";
	$ok = mig_exists($pdo,'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',["transfer_parcels","weight_kg"]);
	echo '  '.($ok?'✓':'×').' transfer_parcels.weight_kg' . "\n\n";
	echo "INDEXES\n";
	$idx = [
		['transfer_logs','idx_logs_transfer_created'],
		['transfer_logs','idx_logs_event_created'],
		['transfer_audit_log','idx_audit_transfer_created'],
		['transfer_audit_log','idx_audit_action_created'],
		['transfer_discrepancies','idx_td_transfer_created'],
		['transfer_discrepancies','idx_td_status_created'],
	];
	foreach ($idx as [$t,$i]) {
		$ok = mig_exists($pdo,'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?',[$t,$i]);
		echo '  '.($ok?'✓':'×')." $t.$i\n";
	}
	echo "\nDone.\n";
}

// 1) Show a snapshot first
echo "=== SCHEMA SNAPSHOT (pre) ===\n";
if (!function_exists('cis_pdo')) { echo "cis_pdo() not available\n"; exit; }
$pdoSnap = cis_pdo();
mig_snapshot($pdoSnap);

// 2) Execute migrations (inline runner to support CLI token path)
echo "\n=== APPLY MIGRATIONS ===\n";
$pdo = cis_pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
	try { $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true); } catch (Throwable $e) { /* ignore */ }
}

// Ensure schema_migrations exists
$schemaSql = __DIR__ . '/2025-09-22_schema_migrations.sql';
if (is_file($schemaSql)) { $pdo->exec((string)file_get_contents($schemaSql)); }

// Gather seen
$seen = [];
try {
	$stmt = $pdo->query('SELECT filename, checksum_sha256 FROM schema_migrations');
	foreach ($stmt as $row) { $seen[$row['filename'].'|'.$row['checksum_sha256']] = true; }
} catch (Throwable $e) { /* ignore */ }

// Files
$files = glob(__DIR__ . '/*.sql'); sort($files, SORT_STRING);
$actor = (string)($_SESSION['userID'] ?? ($internalOK || $internalCLI ? 'internal' : 'unknown'));
$ran=0; $skipped=0; $failed=0;
foreach ($files as $f) {
	$bn = basename($f);
	if ($bn === '2025-09-22_schema_migrations.sql') { $skipped++; continue; }
	$sum = hash('sha256', (string)file_get_contents($f));
	$key = $bn.'|'.$sum;
	if (isset($seen[$key])) { echo "Skip (seen): $bn\n"; $skipped++; continue; }
	try {
		$sql = (string)file_get_contents($f);
		// Execute each statement separately to accommodate PREPARE/EXECUTE blocks and DDL auto-commits
	$statements = array_filter(array_map('trim', preg_split('/;\s*/', $sql)));
		foreach ($statements as $stmtSql) {
			if ($stmtSql === '' || strpos($stmtSql,'--') === 0) continue;
			$pdo->exec($stmtSql);
		}
		$ins = $pdo->prepare('INSERT INTO schema_migrations (filename, checksum_sha256, executed_by, success) VALUES (?,?,?,1)');
		$ins->execute([$bn,$sum,$actor]);
		echo "Ran: $bn\n"; $ran++;
	} catch (Throwable $e) {
		try {
			$ins = $pdo->prepare('INSERT INTO schema_migrations (filename, checksum_sha256, executed_by, success, error_message) VALUES (?,?,?,?,?)');
			$ins->execute([$bn,$sum,$actor,0,$e->getMessage()]);
		} catch (Throwable $e2) { /* ignore */ }
		echo "FAILED: $bn => ".$e->getMessage()."\n"; $failed++;
	}
}
echo "\nSummary: ran=$ran, skipped=$skipped, failed=$failed\n";

// 3) Post snapshot (best-effort)
echo "\n=== SCHEMA SNAPSHOT (post) ===\n";
$pdoPost = cis_pdo();
mig_snapshot($pdoPost);
