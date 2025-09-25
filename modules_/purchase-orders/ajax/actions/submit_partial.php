<?php
declare(strict_types=1);

$ctx  = $GLOBALS['__po_ctx'] ?? ['uid' => 0];
$poId = (int)($_POST['po_id'] ?? 0);
$live = isset($_POST['live']) ? (bool)filter_var($_POST['live'], FILTER_VALIDATE_BOOLEAN) : true;

if ($poId <= 0) po_jresp(false, ['code'=>'bad_request','message'=>'po_id required'], 422);
po_verify_csrf();

try {
  $pdo = po_pdo();

  // Idempotency: request hash + replay or conflict
  $idemKey = (string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? $_POST['idempotency_key'] ?? '');
  $reqHash = $idemKey !== '' ? po_request_hash($_POST) : '';
  if ($idemKey !== '') {
    $rec = po_idem_get($pdo, $idemKey);
    if ($rec) {
      if ($rec['request_hash'] === $reqHash && is_array($rec['response'])) {
        http_response_code(200);
        echo json_encode($rec['response'], JSON_UNESCAPED_SLASHES);
        exit;
      }
      po_jresp(false, ['code'=>'idem_conflict','message'=>'Idempotency key re-used with different request body'], 409);
    }
  }

  $st = $pdo->prepare('SELECT status, outlet_id FROM purchase_orders WHERE purchase_order_id = ? LIMIT 1');
  $st->execute([$poId]);
  $hdr = $st->fetch();
  if (!$hdr) po_jresp(false, ['code'=>'not_found','message'=>'PO not found'], 404);
  if ((int)($hdr['status'] ?? 0) === 1) po_jresp(false, ['code'=>'readonly','message'=>'Already completed'], 409);
  $outletId = (string)($hdr['outlet_id'] ?? '');

  // columns for lines
  $orderQtyCol = po_has_column($pdo,'purchase_order_line_items','order_qty') ? 'order_qty'
               : (po_has_column($pdo,'purchase_order_line_items','qty_ordered') ? 'qty_ordered' : 'order_qty');
  $qtyArrCol   = po_has_column($pdo,'purchase_order_line_items','qty_arrived') ? 'qty_arrived' : 'qty_received';

  $q = $pdo->prepare("SELECT product_id, {$orderQtyCol} AS expected, COALESCE({$qtyArrCol},0) AS received
                      FROM purchase_order_line_items WHERE purchase_order_id = ?");
  $q->execute([$poId]);
  $items = $q->fetchAll();

  // enqueue inventory adjustments for received>0
  $enqueued = 0;
  foreach ($items as $it) {
    $pid  = (string)$it['product_id'];
    $recv = (int)$it['received'];
    if ($recv <= 0) continue;

    if ($live && po_table_exists($pdo,'inventory_adjust_requests')) {
      $idem = "po:{$poId}:product:{$pid}:qty:{$recv}";
      $j = $pdo->prepare('INSERT INTO inventory_adjust_requests
        (transfer_id, outlet_id, product_id, delta, reason, source, status, idempotency_key, requested_by, requested_at)
        VALUES (NULL,?,?,?,?,\'po-partial\',\'pending\',?,?,NOW())
        ON DUPLICATE KEY UPDATE requested_at = VALUES(requested_at)');
      $j->execute([$outletId, $pid, $recv, 'po-partial-commit', $idem, (int)$ctx['uid']]);
      $enqueued++;
    }
  }

  // create a partial receipt snapshot
  $rid = po_create_receipt($pdo, $poId, $outletId, false, (int)$ctx['uid'], array_map(function($r){
    return [
      'product_id' => (string)$r['product_id'],
      'expected'   => (int)$r['expected'],
      'received'   => (int)$r['received'],
      'line_note'  => '',
    ];
  }, $items));

  // mark PO partial if such column/state exists
  if (po_has_column($pdo, 'purchase_orders', 'status')) {
    $pdo->prepare('UPDATE purchase_orders SET status = 2, updated_at = NOW() WHERE purchase_order_id = ?')->execute([$poId]);
  } elseif (po_has_column($pdo, 'purchase_orders', 'partial_received_at')) {
    $pdo->prepare('UPDATE purchase_orders SET partial_received_at = NOW() WHERE purchase_order_id = ?')->execute([$poId]);
  }

  po_insert_event($pdo, $poId, 'submit.partial', ['receipt_id'=>$rid,'enqueued'=>$enqueued], (int)$ctx['uid']);

  $data = ['po_id'=>$poId, 'status'=>'partial', 'actions'=>['enqueued'=>$enqueued, 'receipt_id'=>$rid]];

  if ($idemKey !== '') {
    global $__PO_REQ_ID;
    $envelope = ['success'=>true,'request_id'=>$__PO_REQ_ID,'data'=>$data];
    po_idem_store($pdo, $idemKey, $reqHash, $envelope);
  }

  po_jresp(true, $data);
} catch (Throwable $e) {
  po_jresp(false, ['code'=>'internal_error','message'=>$e->getMessage()], 500);
}
