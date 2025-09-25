<?php declare(strict_types=1);
require_once dirname(__DIR__, 6).'/bootstrap.php';
header('Content-Type: application/json');

try {
  cis_require_login();
  $in  = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
  $tid = (int)($in['transfer_id'] ?? 0);
  if ($tid<=0) throw new InvalidArgumentException('Bad transfer id');

  $pdo = db_ro();

  // Pull items with guessed weights (vend_products.weight_grams if you have it)
  $stmt = $pdo->prepare("
    SELECT ti.id item_id, ti.product_id, ti.qty_sent_total AS qty,
           COALESCE(vp.weight_grams, 0) AS w
    FROM transfer_items ti
    LEFT JOIN vend_products vp ON vp.id = ti.product_id
    WHERE ti.transfer_id = :tid
  ");
  $stmt->execute([':tid'=>$tid]);
  $items = $stmt->fetchAll();

  // Greedy box pack (cap 18kg per box = 18000g)
  $MAX_G = 18000; $boxes=[]; $box=1; $used=0;
  foreach ($items as $it) {
    $count = max(0, (int)$it['qty']);
    $wg    = max(0, (int)$it['w']);
    for ($i=0; $i<$count; $i++) {
      if (($used + $wg) > $MAX_G && $used>0) { $boxes[]=['box_number'=>$box++,'weight_grams'=>$used]; $used=0; }
      $used += $wg;
    }
  }
  if ($used>0 || !$boxes) $boxes[] = ['box_number'=>$box, 'weight_grams'=>$used];

  // Choose carrier/service (very simple heuristic; your rules engine can fill these)
  foreach ($boxes as &$b) {
    $b['carrier'] = $b['weight_grams'] >= 10000 ? 'GSS' : 'NZ_POST';
    $b['service'] = $b['carrier']==='GSS' ? 'OVERNIGHT' : 'COURIER';
  }

  echo json_encode(['ok'=>true,'boxes'=>$boxes], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
