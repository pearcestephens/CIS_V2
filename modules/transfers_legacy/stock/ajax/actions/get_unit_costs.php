<?php
declare(strict_types=1);
/**
 * get_unit_costs.php
 * Returns unit costs from vend_products.supply_price for items in a transfer.
 * Policy: keep 0.00 when supply_price is null/0 — no fallback. GST excluded; NZD.
 */

if (!function_exists('jresp')) { http_response_code(500); echo json_encode(['success'=>false,'error'=>'Router missing']); exit; }

$transferId = (int)($_POST['transfer_id'] ?? 0);
$map = [];
try {
  if (function_exists('cis_pdo') && $transferId > 0) {
    $pdo = cis_pdo();
    $sql = "SELECT ti.product_id, COALESCE(vp.supply_price, 0) AS supply_price
            FROM transfer_items ti
            LEFT JOIN vend_products vp ON vp.id = ti.product_id
            WHERE ti.transfer_id = :tid";
    $st = $pdo->prepare($sql);
    $st->execute([':tid' => $transferId]);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $pid = (string)$row['product_id'];
      $map[$pid] = (float)$row['supply_price'];
    }
  }
} catch (Throwable $e) {
  // keep empty map on failure
}

jresp(true, ['unit_costs' => $map]);
