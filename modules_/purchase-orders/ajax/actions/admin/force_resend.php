<?php
declare(strict_types=1);

po_verify_csrf();
$id = (int)($_POST['request_id'] ?? 0);
if ($id <= 0) po_jresp(false, ['code'=>'bad_request','message'=>'request_id required'], 422);

try {
  $pdo = po_pdo();
  if (!po_table_exists($pdo,'inventory_adjust_requests')) {
    po_jresp(false, ['code'=>'not_found','message'=>'Queue table missing'], 404);
  }

  $sel = $pdo->prepare('SELECT * FROM inventory_adjust_requests WHERE request_id = ?');
  $sel->execute([$id]);
  $r = $sel->fetch();
  if (!$r) po_jresp(false, ['code'=>'not_found','message'=>'Request not found'], 404);

  // Duplicate with a new idempotency key to force re-processing
  $idem = (string)$r['idempotency_key'] . '#' . bin2hex(random_bytes(4));
  $ins = $pdo->prepare("INSERT INTO inventory_adjust_requests
      (transfer_id, outlet_id, product_id, delta, reason, source, status, idempotency_key, requested_by, requested_at)
      VALUES (?,?,?,?,?,?, 'pending', ?, ?, NOW())");
  $ins->execute([
    $r['transfer_id'],
    $r['outlet_id'],
    $r['product_id'],
    $r['delta'],
    $r['reason'],
    $r['source'],
    $idem,
    (int)($GLOBALS['__po_ctx']['uid'] ?? 0),
  ]);

  $newId = (int)$pdo->lastInsertId();
  po_insert_event($pdo, 0, 'queue.force_resend', ['from'=>$id,'to'=>$newId], (int)($GLOBALS['__po_ctx']['uid'] ?? 0));

  po_jresp(true, ['from'=>$id,'to'=>$newId]);
} catch (Throwable $e) {
  po_jresp(false, ['code'=>'internal_error','message'=>'Failed to force resend'], 500);
}
