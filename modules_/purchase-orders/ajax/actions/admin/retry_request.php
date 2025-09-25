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

  $st = $pdo->prepare("UPDATE inventory_adjust_requests
                       SET status = 'pending', error_msg = NULL, processed_at = NULL
                       WHERE request_id = ?");
  $st->execute([$id]);

  po_insert_event($pdo, 0, 'queue.retry', ['request_id'=>$id], (int)($GLOBALS['__po_ctx']['uid'] ?? 0));
  po_jresp(true, ['request_id'=>$id, 'status'=>'pending']);
} catch (Throwable $e) {
  po_jresp(false, ['code'=>'internal_error','message'=>'Failed to retry request'], 500);
}
