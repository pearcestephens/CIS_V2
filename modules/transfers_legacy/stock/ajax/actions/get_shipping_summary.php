<?php
declare(strict_types=1);
/**
 * Action: get_shipping_summary
 * Purpose: Compute total shipment weight and pick the most cost-effective container per available carrier,
 *          using the Freight & Categorisation Bible DB functions.
 * Input: transfer_id (required)
 * Output: {
 *   total_grams:int, total_kg:float,
 *   carriers: [
 *     { carrier_id:int, carrier_code:string, carrier_name:string,
 *       best: { container_id:int, container_code:string, kind:string, cost:float, max_weight_grams:int|null },
 *       alternatives: [ ... up to 3 ... ]
 *     }
 *   ],
 *   recommended: { carrier_id:int, carrier_code:string, container_code:string, cost:float } | null
 * }
 */

$tid = (int)($_POST['transfer_id'] ?? $_GET['transfer_id'] ?? 0);
if ($tid <= 0) { jresp(false, 'transfer_id required', 400); }

try {
  $pdo = stx_db();

  // Locate origin store to determine carrier availability strictly from vend_outlets tokens
  $fromOutlet = null;
  try {
    $stmt = $pdo->prepare('SELECT outlet_from FROM transfers WHERE id = ?');
    $stmt->execute([$tid]);
    $fromOutlet = $stmt->fetchColumn();
    if (!$fromOutlet) {
      $stmt = $pdo->prepare('SELECT outlet_from FROM stock_transfers WHERE transfer_id = ?');
      $stmt->execute([$tid]);
      $fromOutlet = $stmt->fetchColumn();
    }
  } catch (Throwable $e) { /* optional */ }
  $fromOutlet = $fromOutlet ? (string)$fromOutlet : '';

  $hasNz = false; $hasGss = false; $storeName = '';
  if ($fromOutlet !== '') {
    try {
      $s2 = $pdo->prepare('SELECT name, nz_post_api_key, nz_post_subscription_key, gss_token FROM vend_outlets WHERE id = ?');
      $s2->execute([$fromOutlet]);
      if ($row = $s2->fetch(PDO::FETCH_ASSOC)) {
        $storeName = (string)($row['name'] ?? '');
        $hasNz  = !empty($row['nz_post_api_key']) || !empty($row['nz_post_subscription_key']);
        $hasGss = !empty($row['gss_token']);
      }
    } catch (Throwable $e) { /* ignore */ }
  }

  // Gather items for this transfer (prefer canonical transfer_items, fallback to stock_transfer_lines, last to snapshot)
  $items = [];
  $outletFrom = $fromOutlet;
  try {
    // Try canonical transfer_items with flexible id column
    $pdo->query('SELECT 1 FROM transfer_items LIMIT 1');
    $tidCols = ['ti.transfer_id','ti.stock_transfer_id','ti.parent_transfer_id','ti.transfer','ti.transferId','ti.id_transfer'];
    $sqlTpl = 'SELECT COALESCE(ti.product_id, ti.vend_product_id, ti.sku, ti.product_sku, ti.barcode) AS product_id,
                      COALESCE(ti.qty_to_transfer, ti.qty_requested, ti.qty_planned, ti.request_qty, ti.quantity, ti.qty, 0) AS qty
                 FROM transfer_items ti
                WHERE {{TID_COL}} = :tid';
    foreach ($tidCols as $col) {
      $sql = str_replace('{{TID_COL}}', $col, $sqlTpl);
      try { $st = $pdo->prepare($sql); $st->execute([':tid' => $tid]); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($rows && count($rows) > 0) { $items = $rows; break; }
      } catch (Throwable $e) { /* try next */ }
    }
  } catch (Throwable $e) { /* table not present, fallback next */ }

  if (empty($items)) {
    try {
      $pdo->query('SELECT 1 FROM stock_transfer_lines LIMIT 1');
      $tidCols = ['transfer_id','stock_transfer_id','parent_transfer_id','transfer','transferId','id_transfer'];
      $sqlTpl = 'SELECT COALESCE(l.product_id, l.vend_product_id, l.sku, l.product_sku, l.barcode) AS product_id,
                        COALESCE(l.qty_to_transfer, l.qty_requested, l.qty_planned, l.request_qty, l.quantity, l.qty, 0) AS qty
                   FROM stock_transfer_lines l
                  WHERE {{TID_COL}} = :tid';
      foreach ($tidCols as $col) {
        $sql = str_replace('{{TID_COL}}', $col, $sqlTpl);
        try { $st = $pdo->prepare($sql); $st->execute([':tid' => $tid]); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
          if ($rows && count($rows) > 0) { $items = $rows; break; }
        } catch (Throwable $e) { /* try next */ }
      }
    } catch (Throwable $e) { /* table not present */ }
  }

  if (empty($items)) {
    // Fallback: use TransferService snapshot via list_items action for DEV
    require_once __DIR__ . '/../../core/TransferService.php';
    $svc = new TransferService();
    $snap = $svc->snapshot($tid) ?: [];
    $it = $snap['items'] ?? [];
    if (is_array($it)) {
      foreach ($it as $r) {
        $pid = (string)($r['product_id'] ?? '');
        $q   = (int)($r['qty'] ?? $r['qty_requested'] ?? 0);
        if ($pid !== '' && $q > 0) { $items[] = ['product_id'=>$pid, 'qty'=>$q]; }
      }
    }
  }

  if (empty($items)) { jresp(true, [
    'total_grams' => 0,
    'total_kg' => 0.0,
    'carriers' => [],
    'recommended' => null,
    'store' => ['id'=>$fromOutlet, 'name'=>$storeName],
  ]); }

  // Compute total grams using product avg_weight_grams -> category_weights fallback -> default 100g
  $totalGrams = 0;
  $qWeight = $pdo->prepare(
    'SELECT COALESCE(vp.avg_weight_grams, cw.avg_weight_grams, 100) AS unit_g
       FROM vend_products vp
       LEFT JOIN product_classification_unified pcu ON pcu.product_id = vp.id
       LEFT JOIN category_weights cw ON cw.category_id = pcu.category_id
      WHERE vp.id = ?'
  );
  foreach ($items as $row) {
    $pid = (string)$row['product_id']; $qty = (int)($row['qty'] ?? 0);
    if ($qty <= 0 || $pid === '') continue;
    try { $qWeight->execute([$pid]); $unit = (int)($qWeight->fetchColumn() ?: 100); } catch (Throwable $e) { $unit = 100; }
    $totalGrams += max(0, $qty) * max(1, (int)$unit);
  }

  // Candidate carriers strictly by tokens
  $carriers = [];
  if ($hasNz)  { $carriers[] = ['carrier_id'=>1, 'code'=>'NZPOST', 'name'=>'NZ Post']; }
  if ($hasGss) { $carriers[] = ['carrier_id'=>2, 'code'=>'GSS', 'name'=>'NZ Couriers (GSS)']; }
  if (empty($carriers)) {
    // Manual only: return basic weight summary
    jresp(true, [
      'total_grams' => (int)$totalGrams,
      'total_kg' => round($totalGrams/1000, 3),
      'carriers' => [],
      'recommended' => null,
      'store' => ['id'=>$fromOutlet, 'name'=>$storeName],
    ]);
  }

  // For each carrier, ask DB to pick cheapest fitting container; also build alternatives via explain JSON.
  $pickCost = $pdo->prepare('SELECT pick_container_cost(?, NULL, NULL, NULL, ?)');
  $pickJson = $pdo->prepare('SELECT pick_container_json(?, NULL, NULL, NULL, ?)');
  $explain  = $pdo->prepare('SELECT pick_container_explain_json(?, NULL, NULL, NULL, ?)');

  $respCarriers = [];
  foreach ($carriers as $c) {
    $cid = (int)$c['carrier_id'];
    $best = null; $alts = [];
    try {
      $pickJson->execute([$cid, $totalGrams]);
      $row = $pickJson->fetch(PDO::FETCH_NUM);
      $json = $row ? ($row[0] ?? null) : null;
      if ($json) { $best = json_decode((string)$json, true); }
    } catch (Throwable $e) { /* ignore */ }

    try {
      $explain->execute([$cid, $totalGrams]);
      $row = $explain->fetch(PDO::FETCH_NUM);
      $json = $row ? ($row[0] ?? null) : null;
      if ($json) {
        $eobj = json_decode((string)$json, true);
        if (is_array($eobj) && isset($eobj['candidates']) && is_array($eobj['candidates'])) {
          foreach ($eobj['candidates'] as $cand) { $alts[] = $cand; if (count($alts) >= 3) break; }
        }
      }
    } catch (Throwable $e) { /* ignore */ }

    $respCarriers[] = [
      'carrier_id' => $cid,
      'carrier_code' => $c['code'],
      'carrier_name' => $c['name'],
      'best' => $best,
      'alternatives' => $alts,
    ];
  }

  // Choose recommendation = absolute cheapest best.cost among available carriers
  $recommended = null; $bestCost = null;
  foreach ($respCarriers as $rc) {
    $b = $rc['best'] ?? null; if (!$b || !isset($b['cost'])) continue;
    $cost = (float)$b['cost'];
    if ($bestCost === null || $cost < $bestCost) { $bestCost = $cost; $recommended = [
      'carrier_id' => $rc['carrier_id'],
      'carrier_code' => $rc['carrier_code'],
      'container_code' => (string)($b['code'] ?? ''),
      'kind' => (string)($b['kind'] ?? ''),
      'cost' => $cost,
    ]; }
  }

  jresp(true, [
    'total_grams' => (int)$totalGrams,
    'total_kg' => round($totalGrams/1000, 3),
    'carriers' => $respCarriers,
    'recommended' => $recommended,
    'store' => ['id'=>$fromOutlet, 'name'=>$storeName],
  ]);
} catch (Throwable $e) {
  jresp(false, 'Server error', 500);
}
