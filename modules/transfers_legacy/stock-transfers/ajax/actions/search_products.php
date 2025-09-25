<?php
/**
 * search_products.php
 * Input: q (string, min 2), limit (int, default 20)
 * Output: { results: [ { product_id, name, sku, image_url } ] }
 */
declare(strict_types=1);

$q = trim((string)($_POST['q'] ?? ''));
$limit = (int)($_POST['limit'] ?? 20); if ($limit < 1) $limit = 20; if ($limit > 50) $limit = 50;
if ($q === '' || mb_strlen($q) < 2) { jresp(true, ['results'=>[]]); }

try {
  $pdo = stx_pdo();
  $hasVP = stx_table_exists($pdo, 'vend_products');
  if (!$hasVP) { jresp(true, ['results'=>[]]); }

  // Build weighted search by name/sku/id
  $term = '%' . $q . '%';
  $sql = "SELECT vp.id AS product_id, vp.name AS product_name, vp.sku, vp.image_url,
            CASE
              WHEN vp.name LIKE :q_prefix THEN 10
              WHEN vp.sku LIKE :q_prefix THEN 9
              WHEN vp.name LIKE :q_any THEN 5
              WHEN vp.sku LIKE :q_any THEN 4
              WHEN vp.id = :q_exact THEN 8
              ELSE 0
            END AS score
          FROM vend_products vp
          WHERE (vp.name LIKE :q_any OR vp.sku LIKE :q_any OR vp.id = :q_exact)
          ORDER BY score DESC, vp.name ASC
          LIMIT :lim";
  $stmt = $pdo->prepare($sql);
  $prefix = $q . '%';
  $stmt->bindValue(':q_prefix', $prefix, PDO::PARAM_STR);
  $stmt->bindValue(':q_any', $term, PDO::PARAM_STR);
  // product_id can be varchar; try exact match against provided q
  $stmt->bindValue(':q_exact', $q, PDO::PARAM_STR);
  $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll();
  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      'product_id' => (string)($r['product_id'] ?? ''),
      'name' => (string)($r['product_name'] ?? ($r['product_id'] ?? '')),
      'sku' => (string)($r['sku'] ?? ''),
      'image_url' => (string)($r['image_url'] ?? ''),
    ];
  }
  jresp(true, ['results'=>$out, 'count'=>count($out)]);
} catch (Throwable $e) {
  error_log('[transfers.stock-transfers.search_products]['.$__ajax_context['request_id'].'] '.$e->getMessage());
  jresp(false, 'Server error', 500);
}
