<?php
declare(strict_types=1);

$ctx  = $GLOBALS['__po_ctx'] ?? ['uid' => 0];
$poId = (int)($_POST['po_id'] ?? 0);
if ($poId <= 0) po_jresp(false, ['code'=>'invalid_po_id','message'=>'po_id required'], 422);

try {
  $pdo = po_pdo();

  // header
  $hdrSql = "SELECT po.purchase_order_id AS po_id,
                    po.status,
                    po.supplier_id,
                    COALESCE(vs.name,po.supplier_name_cache,'Supplier') AS supplier_name,
                    po.outlet_id,
                    vo.name AS outlet_name,
                    po.partial_delivery AS partial,
                    po.completed_timestamp
             FROM purchase_orders po
             LEFT JOIN vend_suppliers vs ON vs.id = po.supplier_id
             LEFT JOIN vend_outlets  vo ON vo.id = po.outlet_id
             WHERE po.purchase_order_id = ? LIMIT 1";
  $h = $pdo->prepare($hdrSql);
  $h->execute([$poId]);
  $headerRow = $h->fetch();
  if (!$headerRow) po_jresp(false, ['code'=>'not_found','message'=>'PO not found'], 404);

  // line columns (compat)
  $orderQtyCol = po_has_column($pdo,'purchase_order_line_items','order_qty') ? 'order_qty'
               : (po_has_column($pdo,'purchase_order_line_items','qty_ordered') ? 'qty_ordered' : 'order_qty');
  $qtyArrCol   = po_has_column($pdo,'purchase_order_line_items','qty_arrived') ? 'qty_arrived' : 'qty_received';
  $receivedAt  = po_has_column($pdo,'purchase_order_line_items','received_at') ? 'received_at' : null;

  $hasVP = po_table_exists($pdo,'vend_products');
  $hasVI = po_table_exists($pdo,'vend_inventory');

  $sel = [
    "poli.product_id",
    "poli.{$orderQtyCol} AS expected",
    "COALESCE(poli.{$qtyArrCol},0) AS received"
  ];
  if ($receivedAt) $sel[] = "poli.{$receivedAt} AS received_at";
  if ($hasVP) { $sel[] = 'vp.name AS product_name'; $sel[] = 'vp.image_url'; $sel[] = 'vp.sku'; }
  if ($hasVI) { $sel[] = 'vi.inventory_level AS current_stock'; }

  $sql = "SELECT " . implode(',', $sel) . "
          FROM purchase_order_line_items poli
          " . ($hasVP ? "LEFT JOIN vend_products vp ON vp.id = poli.product_id " : "") . "
          " . ($hasVI ? "LEFT JOIN vend_inventory vi ON vi.product_id = poli.product_id AND vi.outlet_id = ? " : "") . "
          WHERE poli.purchase_order_id = ?
          ORDER BY " . ($hasVP ? "COALESCE(vp.name,poli.product_id)" : "poli.product_id");

  $stmt = $pdo->prepare($sql);
  if ($hasVI) $stmt->execute([(string)$headerRow['outlet_id'], $poId]);
  else        $stmt->execute([$poId]);

  $items = [];
  $totExpected = 0; $totReceived = 0;
  while ($r = $stmt->fetch()) {
    $expected = (int)$r['expected'];
    $received = (int)$r['received'];
    $items[] = [
      'line_id'   => $r['product_id'],
      'product_id'=> (string)$r['product_id'],
      'name'      => (string)($r['product_name'] ?? $r['product_id']),
      'image'     => (string)($r['image_url'] ?? ''),
      'expected'  => $expected,
      'received'  => $received,
      'status'    => $received >= $expected ? 'Complete' : ($received > 0 ? 'Partial' : 'Pending'),
      'sku'       => (string)($r['sku'] ?? ''),
      'current_stock' => isset($r['current_stock']) ? (int)$r['current_stock'] : null,
    ];
    $totExpected += $expected;
    $totReceived += $received;
  }

  $header = [
    'po_id'      => (int)$headerRow['po_id'],
    'status'     => ((int)($headerRow['status'] ?? 0) === 1 ? 'COMPLETED' : 'OPEN'),
    'supplier'   => ['id' => $headerRow['supplier_id'] ?? null, 'name' => (string)$headerRow['supplier_name']],
    'outlet'     => ['id' => $headerRow['outlet_id'] ?? null, 'name' => (string)($headerRow['outlet_name'] ?? '')],
    'partial'    => (bool)($headerRow['partial'] ?? false),
    'completed_at' => $headerRow['completed_timestamp'] ?? null,
    'totals'     => ['expected_items' => $totExpected, 'received_items' => $totReceived],
  ];

  po_jresp(true, ['header' => $header, 'items' => $items]);
} catch (Throwable $e) {
  po_jresp(false, ['code'=>'internal_error','message'=>$e->getMessage()], 500);
}
