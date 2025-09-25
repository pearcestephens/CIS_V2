<?php
declare(strict_types=1);

$status = isset($_POST['status']) ? (string)$_POST['status'] : '';
$outlet = isset($_POST['outlet_id']) ? (string)$_POST['outlet_id'] : '';
$page   = max(1, (int)($_POST['page'] ?? 1));
$size   = min(200, max(10, (int)($_POST['size'] ?? 50)));
$off    = ($page - 1) * $size;

try {
  $pdo = po_pdo();
  if (!po_table_exists($pdo,'inventory_adjust_requests')) {
    po_jresp(true, ['rows'=>[], 'total'=>0, 'page'=>$page, 'size'=>$size]);
  }

  $where = []; $args = [];
  if ($status !== '') { $where[] = 'status = ?';     $args[] = $status; }
  if ($outlet !== '') { $where[] = 'outlet_id = ?';  $args[] = $outlet; }
  $ws = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

  $sql = "SELECT request_id, outlet_id, product_id, delta, reason, source,
                 status, idempotency_key, requested_by, requested_at,
                 processed_at, error_msg
          FROM inventory_adjust_requests
          $ws
          ORDER BY request_id DESC
          LIMIT $size OFFSET $off";
  $rows = $pdo->prepare($sql);
  $rows->execute($args);
  $data = $rows->fetchAll();

  $cnt = $pdo->prepare("SELECT COUNT(*) FROM inventory_adjust_requests $ws");
  $cnt->execute($args);
  $total = (int)$cnt->fetchColumn();

  po_jresp(true, ['rows'=>$data, 'total'=>$total, 'page'=>$page, 'size'=>$size]);
} catch (Throwable $e) {
  po_jresp(false, ['code'=>'internal_error','message'=>'Failed to list inventory requests'], 500);
}
