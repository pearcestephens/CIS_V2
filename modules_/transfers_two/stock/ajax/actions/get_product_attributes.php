<?php
declare(strict_types=1);

// Returns product attributes used for packaging constraints: { id => { type, is_battery, fragile, hazmat } }
// Inputs: transfer_id OR product_ids[]

try {
  if (!function_exists('cis_pdo')) { jresp(false, 'DB unavailable', 500); }
  $pdo = cis_pdo();

  $tid = (int)($_POST['transfer_id'] ?? $_GET['transfer_id'] ?? 0);
  $ids = [];
  // Optional explicit list
  if (!empty($_POST['product_ids'])) {
    $arr = $_POST['product_ids'];
    if (is_string($arr)) { $arr = json_decode($arr, true); }
    if (is_array($arr)) { foreach ($arr as $v) { $v = (string)$v; if ($v!=='') $ids[$v]=true; } }
  }

  if ($tid > 0 && empty($ids)) {
    // Collect product ids for transfer
    $collect = function(array $rows) use (&$ids){ foreach ($rows as $r){ $id=(string)($r['pid'] ?? $r['product_id'] ?? ''); if($id!=='') $ids[$id]=true; } };
    try { $st=$pdo->prepare('SELECT COALESCE(product_id, vend_product_id, sku, product_sku, barcode) AS pid FROM transfer_items WHERE transfer_id=:tid OR stock_transfer_id=:tid OR parent_transfer_id=:tid'); $st->execute([':tid'=>$tid]); $collect($st->fetchAll(PDO::FETCH_ASSOC)); } catch (Throwable $e) {}
    if (empty($ids)){
      try { $st=$pdo->prepare('SELECT COALESCE(product_id, vend_product_id, sku, product_sku, barcode) AS pid FROM stock_transfer_lines WHERE transfer_id=:tid OR stock_transfer_id=:tid OR parent_transfer_id=:tid'); $st->execute([':tid'=>$tid]); $collect($st->fetchAll(PDO::FETCH_ASSOC)); } catch (Throwable $e) {}
    }
  }

  $ids = array_keys($ids);
  if (empty($ids)) jresp(true, ['attributes'=>[]]);

  $attrs = [];

  // Prefer product_attributes table if present
  try {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT product_id, LOWER(COALESCE(type, category, '')) AS type,
                                (CASE WHEN is_battery=1 OR battery=1 OR LOWER(COALESCE(tags,'')) LIKE '%battery%' THEN 1 ELSE 0 END) AS is_battery,
                                (CASE WHEN fragile=1 OR LOWER(COALESCE(tags,'')) LIKE '%fragile%' THEN 1 ELSE 0 END) AS fragile,
                                (CASE WHEN hazmat=1 OR LOWER(COALESCE(tags,'')) LIKE '%haz%' THEN 1 ELSE 0 END) AS hazmat
                           FROM product_attributes WHERE product_id IN ($in)");
    $st->execute($ids);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)){
      $pid = (string)$row['product_id'];
      $attrs[$pid] = [
        'type' => (string)($row['type'] ?? ''),
        'is_battery' => (int)($row['is_battery'] ?? 0),
        'fragile' => (int)($row['fragile'] ?? 0),
        'hazmat' => (int)($row['hazmat'] ?? 0),
      ];
    }
  } catch (Throwable $e) { /* ignore */ }

  // Augment from vend_products tags or handle if product_attributes is missing
  if (empty($attrs) || count($attrs) < count($ids)){
    $missing = array_diff($ids, array_keys($attrs));
    if (!empty($missing)){
      try {
        $in = implode(',', array_fill(0, count($missing), '?'));
        $st = $pdo->prepare("SELECT id, LOWER(COALESCE(type,'')) AS type, LOWER(COALESCE(tags,'')) AS tags, LOWER(COALESCE(handle,'')) AS handle
                               FROM vend_products WHERE id IN ($in)");
        $st->execute(array_values($missing));
        while ($row = $st->fetch(PDO::FETCH_ASSOC)){
          $pid = (string)$row['id']; $tags = (string)($row['tags'] ?? ''); $handle=(string)($row['handle'] ?? ''); $type=(string)($row['type'] ?? '');
          $isBattery = (strpos($tags,'battery')!==false) || (strpos($type,'battery')!==false) || (strpos($handle,'battery')!==false);
          $attrs[$pid] = $attrs[$pid] ?? [ 'type' => $type, 'is_battery' => $isBattery?1:0, 'fragile'=>0, 'hazmat'=>0 ];
        }
      } catch (Throwable $e) { /* ignore */ }
    }
  }

  jresp(true, ['attributes'=>$attrs]);
} catch (Throwable $e) {
  jresp(false, 'Server error', 500);
}
