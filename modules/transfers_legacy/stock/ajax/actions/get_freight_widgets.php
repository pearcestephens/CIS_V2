<?php
declare(strict_types=1);
/**
 * Action: get_freight_widgets
 * Purpose: Dashboard widgets for freight/weight and boxes across transfers.
 * Inputs (optional): state_group (default 'open'), limit (default 50)
 * Output: {
 *   totals: { transfers:int, units:int, grams:int, kg:float, est_boxes:int },
 *   by_carrier: [ { carrier_id:int, code:string, name:string, count:int, grams:int, kg:float, est_boxes:int, est_cost:float|null } ],
 *   top_heaviest: [ { transfer_id:int, grams:int, kg:float } ],
 *   updated_at: ISO8601
 * }
 */

$group = trim((string)($_POST['state_group'] ?? 'open'));
$limit = max(1, min(200, (int)($_POST['limit'] ?? 50)));

try {
  $pdo = stx_db();

  // Collect recent transfers for the group
  $rows = [];
  try {
    $where = '';
    if ($group === 'open') { $where = "WHERE status IN ('draft','packing','ready_to_send')"; }
    elseif ($group === 'in_motion') { $where = "WHERE status IN ('sent','in_transit')"; }
    elseif ($group === 'arriving') { $where = "WHERE status IN ('receiving','partial')"; }
    $sql = "SELECT id AS transfer_id, outlet_from FROM transfers $where ORDER BY id DESC LIMIT :lim";
    $st = $pdo->prepare($sql);
    $st->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    // Fallback legacy table
    try {
      $sql = "SELECT transfer_id AS transfer_id, outlet_from FROM stock_transfers ORDER BY transfer_id DESC LIMIT :lim";
      $st = $pdo->prepare($sql);
      $st->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
      $st->execute();
      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e2) { $rows = []; }
  }

  if (empty($rows)) { jresp(true, [
    'totals' => ['transfers'=>0,'units'=>0,'grams'=>0,'kg'=>0.0,'est_boxes'=>0],
    'by_carrier' => [], 'top_heaviest'=>[], 'updated_at'=>date('c')
  ]); }

  $transferIds = array_values(array_unique(array_map(fn($r)=> (int)($r['transfer_id'] ?? 0), $rows)));
  $originByTid = [];
  foreach ($rows as $r) { $originByTid[(int)$r['transfer_id']] = (string)($r['outlet_from'] ?? ''); }

  // Collect line items per transfer (best-effort; tallies only weights; unit counts not yet in DB - set 0 for now)
  $qLines = null;
  try {
    $pdo->query('SELECT 1 FROM transfer_items LIMIT 1');
    $qLines = $pdo->prepare('SELECT ti.transfer_id AS tid, COALESCE(ti.qty_to_transfer, ti.qty_requested, ti.qty_planned, ti.request_qty, ti.quantity, ti.qty, 0) AS qty, COALESCE(ti.product_id, ti.vend_product_id, ti.sku, ti.product_sku, ti.barcode) AS pid FROM transfer_items ti WHERE ti.transfer_id = ?');
  } catch (Throwable $e) {
    try {
      $pdo->query('SELECT 1 FROM stock_transfer_lines LIMIT 1');
      $qLines = $pdo->prepare('SELECT l.transfer_id AS tid, COALESCE(l.qty_to_transfer, l.qty_requested, l.qty_planned, l.request_qty, l.quantity, l.qty, 0) AS qty, COALESCE(l.product_id, l.vend_product_id, l.sku, l.product_sku, l.barcode) AS pid FROM stock_transfer_lines l WHERE l.transfer_id = ?');
    } catch (Throwable $e2) { $qLines = null; }
  }

  $unitWeightStmt = $pdo->prepare(
    'SELECT COALESCE(vp.avg_weight_grams, cw.avg_weight_grams, 100)
       FROM vend_products vp
       LEFT JOIN product_classification_unified pcu ON pcu.product_id = vp.id
       LEFT JOIN category_weights cw ON cw.category_id = pcu.category_id
      WHERE vp.id = ?'
  );

  $totTransfers = 0; $totUnits = 0; $totGrams = 0; $totBoxes = 0;
  $carrierAgg = [
    1 => ['carrier_id'=>1,'code'=>'NZPOST','name'=>'NZ Post','count'=>0,'grams'=>0,'kg'=>0.0,'est_boxes'=>0,'est_cost'=>null],
    2 => ['carrier_id'=>2,'code'=>'GSS','name'=>'NZ Couriers (GSS)','count'=>0,'grams'=>0,'kg'=>0.0,'est_boxes'=>0,'est_cost'=>null],
  ];
  $heaviest = [];

  foreach ($transferIds as $tid) {
    $totTransfers++;
    $from = $originByTid[$tid] ?? '';
    // Determine available carriers for this origin
    $hasNz=false; $hasGss=false;
    if ($from !== '') {
      try { $st = $pdo->prepare('SELECT nz_post_api_key, nz_post_subscription_key, gss_token FROM vend_outlets WHERE id = ?'); $st->execute([$from]); $row = $st->fetch(PDO::FETCH_ASSOC) ?: null; if ($row){ $hasNz = !empty($row['nz_post_api_key']) || !empty($row['nz_post_subscription_key']); $hasGss = !empty($row['gss_token']); } } catch (Throwable $e) {}
    }

    $grams = 0; $units = 0;
    if ($qLines) {
      try {
        $qLines->execute([$tid]);
        while ($ln = $qLines->fetch(PDO::FETCH_ASSOC)){
          $qty = (int)($ln['qty'] ?? 0); if ($qty <= 0) continue;
          $pid = (string)($ln['pid'] ?? ''); if ($pid === '') continue;
          $units += $qty;
          try { $unitWeightStmt->execute([$pid]); $ug = (int)($unitWeightStmt->fetchColumn() ?: 100); } catch (Throwable $e) { $ug = 100; }
          $grams += max(0, $qty) * max(1, $ug);
        }
      } catch (Throwable $e) { /* ignore errors per transfer */ }
    }
    $totUnits += $units; $totGrams += $grams; $heaviest[] = ['transfer_id'=>$tid,'grams'=>$grams,'kg'=>round($grams/1000,3)];

    // Estimate boxes from default setting (max_box_weight_kg) where available
    $maxKg = 15;
    try { $cfg = $pdo->prepare('SELECT default_label_paper FROM vend_outlets WHERE id = ?'); $cfg->execute([$from]); $val = $cfg->fetchColumn(); if (is_numeric($val)) { $maxKg = max(1, (int)$val); } } catch (Throwable $e) {}
    $boxes = ($grams > 0) ? max(1, (int)ceil(($grams/1000) / max(1, $maxKg))) : 0;
    $totBoxes += $boxes;

    // Attribute grams to the cheapest available carrier for aggregate trend (fast: use pick_container_cost)
    $bestCarrierId = null; $bestCost = null;
    try {
      if ($hasNz) {
        $st = $pdo->prepare('SELECT pick_container_cost(1, NULL, NULL, NULL, ?)'); $st->execute([$grams]); $c = $st->fetchColumn(); if ($c !== false) { $bestCarrierId = 1; $bestCost = (float)$c; }
      }
      if ($hasGss) {
        $st = $pdo->prepare('SELECT pick_container_cost(2, NULL, NULL, NULL, ?)'); $st->execute([$grams]); $c = $st->fetchColumn(); if ($c !== false && ($bestCost===null || (float)$c < $bestCost)) { $bestCarrierId = 2; $bestCost = (float)$c; }
      }
    } catch (Throwable $e) { /* ignore pricing failure */ }

    if ($bestCarrierId && isset($carrierAgg[$bestCarrierId])) {
      $agg = &$carrierAgg[$bestCarrierId];
      $agg['count'] += 1;
      $agg['grams'] += $grams;
      $agg['kg'] = round($agg['grams']/1000, 3);
      $agg['est_boxes'] += $boxes;
      $agg['est_cost'] = ($agg['est_cost'] === null) ? ($bestCost ?? null) : ($agg['est_cost'] + ($bestCost ?? 0.0));
      unset($agg);
    }
  }

  // Sort heaviest list
  usort($heaviest, function($a,$b){ return $b['grams'] <=> $a['grams']; });
  $byCarrier = array_values(array_filter(array_map(function($x){
    $x['est_cost'] = $x['est_cost'] === null ? null : round((float)$x['est_cost'], 2);
    return $x;
  }, array_values($carrierAgg)), function($x){ return $x['count']>0; }));

  jresp(true, [
    'totals' => [
      'transfers' => $totTransfers,
      'units' => $totUnits,
      'grams' => $totGrams,
      'kg' => round($totGrams/1000, 3),
      'est_boxes' => $totBoxes,
    ],
    'by_carrier' => $byCarrier,
    'top_heaviest' => array_slice($heaviest, 0, 10),
    'updated_at' => date('c'),
  ], 200);
} catch (Throwable $e) {
  jresp(false, 'Server error', 500);
}
