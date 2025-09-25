<?php
declare(strict_types=1);

/**
 * Given transfer_id, return a map of product_id => unit_weight_grams (int)
 * Precedence per Freight Bible: vend_products.avg_weight_grams
 * then category_weights via product_classification_unified.category_id, else 100g.
 */

$tid = (int)($_POST['transfer_id'] ?? $_GET['transfer_id'] ?? 0);
if ($tid <= 0) jresp(false, 'transfer_id required', 400);

try {
  $pdo = stx_db();

  // Gather product IDs for this transfer from canonical/legacy lines
  $pids = [];
  $collect = function(array $rows) use (&$pids){ foreach ($rows as $r){ $id = (string)($r['pid'] ?? $r['product_id'] ?? ''); if ($id !== '') $pids[$id] = true; } };

  // transfer_items
  try {
    $st = $pdo->prepare('SELECT COALESCE(product_id, vend_product_id, sku, product_sku, barcode) AS pid FROM transfer_items WHERE transfer_id = :tid OR stock_transfer_id = :tid OR parent_transfer_id = :tid');
    $st->execute([':tid'=>$tid]); $collect($st->fetchAll(PDO::FETCH_ASSOC));
  } catch (Throwable $e) { /* ignore */ }
  // stock_transfer_lines
  if (empty($pids)) {
    try { $st = $pdo->prepare('SELECT COALESCE(product_id, vend_product_id, sku, product_sku, barcode) AS pid FROM stock_transfer_lines WHERE transfer_id = :tid OR stock_transfer_id = :tid OR parent_transfer_id = :tid'); $st->execute([':tid'=>$tid]); $collect($st->fetchAll(PDO::FETCH_ASSOC)); } catch (Throwable $e) { /* ignore */ }
  }
  if (empty($pids)) jresp(true, ['weights'=>[]]);

  $ids = array_keys($pids);

  // Query per-product unit grams with category fallback
  $weights = [];
  try {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT vp.id,
                   COALESCE(vp.avg_weight_grams, cw.avg_weight_grams, 100) AS g
              FROM vend_products vp
              LEFT JOIN product_classification_unified pcu ON pcu.product_id = vp.id
              LEFT JOIN category_weights cw ON cw.category_id = pcu.category_id
             WHERE vp.id IN ($in)";
    $st = $pdo->prepare($sql);
    $st->execute($ids);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)){
      $g = (int)($row['g'] ?? 100);
      $weights[(string)$row['id']] = max(1, $g);
    }
  } catch (Throwable $e) { /* ignore */ }

  jresp(true, ['weights'=>$weights]);
} catch (Throwable $e) {
  jresp(false, 'Server error', 500);
}
