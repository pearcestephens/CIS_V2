<?php
declare(strict_types=1);
/**
 * Demo seeder (guarded): /modules/transfers/stock/tools/seed_demo.php?apply=1
 * Creates a tiny transfer and 2 items so Pack+Label can be exercised immediately.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if ((int)($_GET['apply'] ?? 0) !== 1) {
  echo json_encode(['ok' => false, 'note' => 'Add ?apply=1 to run.'], JSON_UNESCAPED_SLASHES);
  exit;
}

$pdo = db();
$pdo->beginTransaction();
try {
  // Ensure required tables exist
  $need = ['transfers','transfer_items'];
  foreach ($need as $t) {
    $q = $pdo->prepare('SHOW TABLES LIKE ?'); $q->execute([$t]);
    if (!$q->fetchColumn()) throw new RuntimeException("Missing table: $t");
  }

  // Create a transfer (type stock, status open)
  $pdo->prepare("
    INSERT INTO transfers(type,status,outlet_from,outlet_to,created_by,created_at,updated_at)
    VALUES('stock','open','OF-DEMO','OT-DEMO',0,NOW(),NOW())
  ")->execute();
  $tid = (int)$pdo->lastInsertId();

  // Pick two real products if possible, else invent numeric placeholders
  $ids = [];
  try {
    $ids = $pdo->query("SELECT id FROM vend_products ORDER BY updated_at DESC LIMIT 2")->fetchAll(PDO::FETCH_COLUMN) ?: [];
  } catch (Throwable $e) {}

  $p1 = (string)($ids[0] ?? '1001');
  $p2 = (string)($ids[1] ?? '1002');

  $ins = $pdo->prepare("INSERT INTO transfer_items(transfer_id,product_id,request_qty,created_at,updated_at)
                         VALUES(:t,:p,:q,NOW(),NOW())");

  $ins->execute([':t' => $tid, ':p' => $p1, ':q' => 6]);
  $ins->execute([':t' => $tid, ':p' => $p2, ':q' => 12]);

  $pdo->commit();
  echo json_encode(['ok' => true, 'transfer_id' => $tid], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
}
