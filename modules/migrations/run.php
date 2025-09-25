<?php
declare(strict_types=1);
/**
 * https://staff.vapeshed.co.nz/modules/migrations/run.php
 * Minimal migration runner for CIS.
 * - Requires app bootstrap and cis_pdo()
 * - Runs all .sql files in this folder in lexical order by default
 * - Records runs in schema_migrations with checksum to avoid accidental replays
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
header('Content-Type: text/plain; charset=utf-8');

// Safety: only allow admins or internal token
session_start();
$env = strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? ''));
$isNonProd = !in_array($env, ['prod','production','live'], true);
$internalToken = $_SERVER['HTTP_X_INTERNAL_TOKEN'] ?? '';
$expectedToken = (string)($_ENV['INTERNAL_API_TOKEN'] ?? getenv('INTERNAL_API_TOKEN') ?: '');
$internalOK = $isNonProd && $internalToken && $expectedToken && hash_equals($expectedToken, (string)$internalToken);
$isAdmin = !empty($_SESSION['userID']) && in_array(($_SESSION['role'] ?? $_SESSION['userRole'] ?? ''), ['admin','owner','director'], true);
if (!$internalOK && !$isAdmin) {
  http_response_code(403);
  echo "Forbidden"; exit;
}

if (!function_exists('cis_pdo')) { echo "cis_pdo() not available"; exit; }
$pdo = cis_pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Ensure schema_migrations table exists
$schemaSql = __DIR__ . '/2025-09-22_schema_migrations.sql';
if (is_file($schemaSql)) {
  $pdo->exec(file_get_contents($schemaSql));
}

$dir = __DIR__;
$files = glob($dir . '/*.sql');
sort($files, SORT_STRING);

$actor = (string)($_SESSION['userID'] ?? ($internalOK ? 'internal' : 'unknown'));

function file_sha256(string $path): string { return hash('sha256', (string)file_get_contents($path)); }

// Fetch already recorded migrations
$seen = [];
try {
  $stmt = $pdo->query('SELECT filename, checksum_sha256 FROM schema_migrations');
  foreach ($stmt as $row) { $seen[$row['filename'] . '|' . $row['checksum_sha256']] = true; }
} catch (Throwable $e) { /* table may not exist yet; ignore */ }

$ran = 0; $skipped = 0; $failed = 0;
foreach ($files as $f) {
  $bn = basename($f);
  if ($bn === '2025-09-22_schema_migrations.sql') { $skipped++; continue; }
  $sum = file_sha256($f);
  $key = $bn . '|' . $sum;
  if (isset($seen[$key])) { $skipped++; continue; }
  try {
    $sql = file_get_contents($f);
    if ($sql === false) { throw new RuntimeException('read failed'); }
    $pdo->beginTransaction();
    $pdo->exec($sql);
    $ins = $pdo->prepare('INSERT INTO schema_migrations (filename, checksum_sha256, executed_by, success) VALUES (?,?,?,1)');
    $ins->execute([$bn, $sum, $actor]);
    $pdo->commit();
    echo "Ran: $bn\n"; $ran++;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    try {
      $ins = $pdo->prepare('INSERT INTO schema_migrations (filename, checksum_sha256, executed_by, success, error_message) VALUES (?,?,?,?,?)');
      $ins->execute([$bn, $sum, $actor, 0, $e->getMessage()]);
    } catch (Throwable $e2) { /* ignore */ }
    echo "FAILED: $bn => " . $e->getMessage() . "\n"; $failed++;
  }
}

echo "\nSummary: ran=$ran, skipped=$skipped, failed=$failed\n";
