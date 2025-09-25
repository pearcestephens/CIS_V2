<?php
/**
 * list_transfers.php
 * Filters: outlet_from (string), outlet_to (string), status (string), q (id prefix), limit (int 200 max)
 * Output: { items: [{id,status,outlet_from,outlet_to,vend_number}], count }
 */

declare(strict_types=1);

$limit = (int)($_POST['limit'] ?? 50); if ($limit<1) $limit=50; if ($limit>200) $limit=200;
$qId   = trim((string)($_POST['q'] ?? ''));
$of    = trim((string)($_POST['outlet_from'] ?? ''));
$ot    = trim((string)($_POST['outlet_to'] ?? ''));
$st    = trim((string)($_POST['status'] ?? ''));
$own   = (int)($_POST['own'] ?? 0) === 1;

try {
  $pdo = stx_pdo();
  if (!stx_table_exists($pdo,'transfers')) { jresp(true, ['items'=>[], 'count'=>0]); }

  // Resolve "own" outlet (force outlet_from to current store's vend_outlets.id)
  if ($own) {
    try {
      $woid = (int)($_SESSION['website_outlet_id'] ?? 0);
      if ($woid > 0 && stx_table_exists($pdo, 'vend_outlets')) {
        $s = $pdo->prepare('SELECT id FROM vend_outlets WHERE website_outlet_id = ? LIMIT 1');
        $s->execute([$woid]);
        $row = $s->fetch(); if ($row && !empty($row['id'])) { $of = (string)$row['id']; }
      }
    } catch (Throwable $e) { /* no-op */ }
  }

  $where = [];
  $params = [];
  if ($qId !== '') { $where[] = 't.id LIKE ?'; $params[] = $qId.'%'; }
  if ($of !== '') { $where[] = 't.outlet_from = ?'; $params[] = $of; }
  if ($ot !== '') { $where[] = 't.outlet_to = ?'; $params[] = $ot; }
  if ($st !== '') { $where[] = 't.status = ?'; $params[] = $st; }
  $sql = 'SELECT t.id, t.status, t.outlet_from, t.outlet_to, t.vend_number, vo.name AS outlet_to_name FROM transfers t LEFT JOIN vend_outlets vo ON vo.id = t.outlet_to';
  if ($qId !== '') { $where[] = '(t.id LIKE ? OR t.vend_number LIKE ?)'; $params[] = $qId.'%'; $params[] = '%'.$qId.'%'; }
  if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
  $sql .= ' ORDER BY t.id DESC LIMIT ' . (int)$limit;

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll();
  $items = [];
  foreach ($rows as $r) {
    $items[] = [
      'id' => (int)$r['id'],
      'status' => (string)($r['status'] ?? ''),
      'outlet_from' => (string)($r['outlet_from'] ?? ''),
      'outlet_to' => (string)($r['outlet_to'] ?? ''),
      'outlet_to_name' => (string)($r['outlet_to_name'] ?? ''),
      'vend_number' => (string)($r['vend_number'] ?? ''),
    ];
  }
  jresp(true, ['items'=>$items, 'count'=>count($items)]);
} catch (Throwable $e) {
  error_log('[transfers.stock-transfers.list_transfers]['.$__ajax_context['request_id'].'] '.$e->getMessage());
  jresp(false, 'Server error', 500);
}
