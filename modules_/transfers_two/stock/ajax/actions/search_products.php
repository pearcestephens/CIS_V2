<?php
declare(strict_types=1);

// Inputs
$tid = (int)($_POST['transfer_id'] ?? $_GET['transfer_id'] ?? 0);
$q   = trim((string)($_POST['q'] ?? $_GET['q'] ?? ''));
$limit = (int)($_POST['limit'] ?? $_GET['limit'] ?? 50);
if ($limit <= 0 || $limit > 200) $limit = 50;
if ($tid <= 0) jresp(false, 'transfer_id required', 400);
if (strlen($q) < 2) jresp(true, ['items' => [], 'query' => $q, 'limit' => $limit]);

try {
  // Prefer cis_pdo, fallback to stx_pdo if available; return empty success when DB not available (avoid UI red error)
  $pdo = null;
  if (function_exists('cis_pdo')) { $pdo = cis_pdo(); }
  elseif (function_exists('stx_pdo')) { $pdo = stx_pdo(); }
  if (!$pdo) { jresp(true, ['items'=>[], 'query'=>$q, 'limit'=>$limit]); }
  // Optional: check required tables exist; if not, return empty
  if (function_exists('stx_table_exists')) {
    if (!stx_table_exists($pdo, 'vend_products')) { jresp(true, ['items'=>[], 'query'=>$q, 'limit'=>$limit]); }
  }
  // Determine source outlet (from) from transfers or legacy stock_transfers
  $fromId = null;
  try {
    $st = $pdo->prepare('SELECT outlet_from FROM transfers WHERE id = ?');
    $st->execute([$tid]);
    $fromId = $st->fetchColumn();
  } catch (Throwable $e) { /* ignore */ }
  if (!$fromId) {
    try {
      $st = $pdo->prepare('SELECT outlet_from FROM stock_transfers WHERE transfer_id = ?');
      $st->execute([$tid]);
      $fromId = $st->fetchColumn();
    } catch (Throwable $e) { /* ignore */ }
  }
  $fromId = (string)$fromId;

  // Build search
  $isUuid = (bool)preg_match('/^[a-f0-9-]{8,}$/i', $q);
  $like = '%' . $q . '%';
  $params = [':like' => $like];
  $where = '(vp.name LIKE :like OR vp.sku LIKE :like)';
  if ($isUuid) { $where .= ' OR vp.id = :id'; $params[':id'] = $q; }

  $sql = "SELECT vp.id AS product_id,
                 vp.name AS product_name,
                 vp.sku AS sku,
                 COALESCE(vi.inventory_level, 0) AS stock,
                 COALESCE(vp.retail_price, vp.price, 0) AS price
            FROM vend_products vp
            LEFT JOIN vend_inventory vi ON vi.product_id = vp.id AND vi.outlet_id = :outlet
           WHERE $where
           ORDER BY vp.name ASC
           LIMIT $limit";
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':outlet', $fromId);
  foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  jresp(true, ['items' => $rows, 'query' => $q, 'limit' => $limit]);
} catch (Throwable $e) {
  error_log('[transfers.stock.search_products]['.$__ajax_context['request_id'].'] '.$e->getMessage());
  jresp(false, 'Server error', 500);
}
