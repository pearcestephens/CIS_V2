<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/DevState.php';

$q = trim((string)($_POST['q'] ?? ''));
$state = trim((string)($_POST['state'] ?? ''));
$stateGroup = trim((string)($_POST['state_group'] ?? ''));
$outletFrom = trim((string)($_POST['outlet_from'] ?? ''));
$outletTo = trim((string)($_POST['outlet_to'] ?? ''));
$historic = (int)($_POST['historic'] ?? 0) === 1;
// Pagination
$page = max(1, (int)($_POST['page'] ?? 1));
$pageSize = max(1, min(100, (int)($_POST['page_size'] ?? 20)));

$rows = [];

// Helper to detect probable ID-like strings (UUID or long identifier)
$isLikelyId = function(string $s): bool {
  return ($s !== '' && (strpos($s, '-') !== false || strlen($s) >= 8));
};

// Try DB-backed source first (canonical transfers table), with safe fallback to DevState
$usedDb = false;
try {
  if (function_exists('cis_pdo')) {
    $pdo = cis_pdo();
    // table existence probes
    $hasTransfers = false; $hasStockTransfers = false;
    try { $pdo->query('SELECT 1 FROM transfers LIMIT 1'); $hasTransfers = true; } catch (Throwable $e) { $hasTransfers = false; }
    try { $pdo->query('SELECT 1 FROM stock_transfers LIMIT 1'); $hasStockTransfers = true; } catch (Throwable $e) { $hasStockTransfers = false; }
    if (!$hasTransfers && !$hasStockTransfers) { throw new RuntimeException('No transfer tables found'); }
    $usedDb = true;

    // Build WHERE clauses
    $where = [];
    $params = [];
    // State filter
    if ($state !== '') { $where[] = 'status = :state'; $params[':state'] = $state; }
    // Group filter
    if ($stateGroup !== '') {
      $groups = [
        'open' => ['draft','packing','ready_to_send'],
        'in_motion' => ['sent','in_transit'],
        'arriving' => ['receiving','partial'],
      ];
      if (isset($groups[$stateGroup])) {
        $in = $groups[$stateGroup];
        $phs = [];
        foreach ($in as $i => $st) { $k = ":g{$i}"; $phs[] = $k; $params[$k] = $st; }
        $where[] = 'status IN ('.implode(',', $phs).')';
      }
    }
    // outlet filters (ID-only in SQL; name filters handled later)
    if ($outletFrom !== '' && $isLikelyId($outletFrom)) { $where[] = 'outlet_from = :ofrom'; $params[':ofrom'] = $outletFrom; }
    if ($outletTo !== '' && $isLikelyId($outletTo)) { $where[] = 'outlet_to = :oto'; $params[':oto'] = $outletTo; }
    // id search for q when numeric/ID-like
    $appliedQInSql = false;
    if ($q !== '') {
      if (ctype_digit($q)) { $where[] = 'id = :qid'; $params[':qid'] = (int)$q; $appliedQInSql = true; }
    }

    $whereSql = count($where) ? ('WHERE '.implode(' AND ', $where)) : '';

    // If we need to support name filtering or state grouping across mixed schemas,
    // fetch a capped recent set and filter in-PHP to preserve behavior.
    $needsPostFilter = (!$appliedQInSql) && (
      ($q !== '') || ($outletFrom !== '' && !$isLikelyId($outletFrom)) || ($outletTo !== '' && !$isLikelyId($outletTo)) || ($stateGroup !== '') || ($hasStockTransfers && $hasTransfers)
    );

    if ($needsPostFilter) {
      $cap = $historic ? 5000 : 1000;
      $rows = [];
      // Pull from canonical transfers if available
      if ($hasTransfers) {
        // Rebuild WHERE for fetch without any status/group constraints; only apply ID-based filters
        $whereFetch = [];
        $paramsFetch = [];
        if ($outletFrom !== '' && $isLikelyId($outletFrom)) { $whereFetch[] = 'outlet_from = :ofrom'; $paramsFetch[':ofrom'] = $outletFrom; }
        if ($outletTo !== '' && $isLikelyId($outletTo)) { $whereFetch[] = 'outlet_to = :oto'; $paramsFetch[':oto'] = $outletTo; }
        if ($q !== '' && ctype_digit($q)) { $whereFetch[] = 'id = :qid'; $paramsFetch[':qid'] = (int)$q; }
        $whereSqlFetch = count($whereFetch) ? ('WHERE '.implode(' AND ', $whereFetch)) : '';
        $sql = "SELECT id AS transfer_id, status AS state, outlet_from AS `from`, outlet_to AS `to`, created_at, updated_at
                FROM transfers $whereSqlFetch ORDER BY id DESC LIMIT :cap";
        $stmt = $pdo->prepare($sql);
        foreach ($paramsFetch as $k=>$v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':cap', (int)$cap, PDO::PARAM_INT);
        $stmt->execute();
        $rows = array_merge($rows, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
      }
      // Pull from legacy stock_transfers (apply only outlet filters by ID; avoid state filter due to schema drift)
      if ($hasStockTransfers) {
        try {
          $where2 = [];
          $params2 = [];
          if ($outletFrom !== '' && $isLikelyId($outletFrom)) { $where2[] = 'outlet_from = :ofrom'; $params2[':ofrom'] = $outletFrom; }
          if ($outletTo !== '' && $isLikelyId($outletTo)) { $where2[] = 'outlet_to = :oto'; $params2[':oto'] = $outletTo; }
          if ($q !== '' && ctype_digit($q)) { $where2[] = 'transfer_id = :qid'; $params2[':qid'] = (int)$q; }
          $whereSql2 = count($where2) ? ('WHERE '.implode(' AND ', $where2)) : '';
          $sql2 = "SELECT transfer_id AS transfer_id, status AS state, outlet_from AS `from`, outlet_to AS `to`, created_at, updated_at
                   FROM stock_transfers $whereSql2 ORDER BY transfer_id DESC LIMIT :cap";
          $st2 = $pdo->prepare($sql2);
          foreach ($params2 as $k=>$v) { $st2->bindValue($k, $v); }
          $st2->bindValue(':cap', (int)$cap, PDO::PARAM_INT);
          $st2->execute();
          $rows = array_merge($rows, $st2->fetchAll(PDO::FETCH_ASSOC) ?: []);
        } catch (Throwable $e) { /* tolerate legacy schema variance */ }
      }
      // De-duplicate by transfer_id (prefer canonical order already added first)
      if (!empty($rows)) {
        $seen = [];
        $uniq = [];
        foreach ($rows as $r) {
          $tid = (string)($r['transfer_id'] ?? $r['id'] ?? '');
          if ($tid === '') continue;
          if (isset($seen[$tid])) continue;
          $seen[$tid] = true;
          $uniq[] = $r;
        }
        $rows = $uniq;
      }
    } else {
      // Count for pagination
      $cnt = $pdo->prepare("SELECT COUNT(*) AS c FROM transfers $whereSql");
      $cnt->execute($params);
      $total = (int)($cnt->fetchColumn() ?: 0);
      $totalPages = max(1, (int)ceil($total / $pageSize));
      if ($page > $totalPages) { $page = $totalPages; }
      $offset = ($page - 1) * $pageSize;
      $sql = "SELECT id AS transfer_id, status AS state, outlet_from AS `from`, outlet_to AS `to`, created_at, updated_at
              FROM transfers $whereSql ORDER BY id DESC LIMIT :lim OFFSET :off";
      $stmt = $pdo->prepare($sql);
      foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
      $stmt->bindValue(':lim', (int)$pageSize, PDO::PARAM_INT);
      $stmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
      $stmt->execute();
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // Normalize legacy status values to canonical set for consistent UI/grouping
    $normalizeState = function($s){
      if ($s === null) return '';
      // numeric mapping from legacy 0..3
      if (is_numeric($s)) {
        $n = (int)$s;
        if ($n === 1) return 'ready_to_send';
        if ($n === 2) return 'sent';
        if ($n === 3) return 'received';
        return 'draft'; // 0 or any other
      }
      // Normalize formatting: lowercase, replace spaces/dashes with underscore, collapse multiple underscores
      $u = strtolower((string)$s);
      $u = preg_replace('/[^a-z0-9]+/i', '_', $u ?? '');
      $u = trim(preg_replace('/_+/', '_', (string)$u), '_');
      // Common synonyms mapping
      $map = [
        'open' => 'draft', 'draft' => 'draft',
        'packing' => 'packing', 'packed' => 'ready_to_send', 'pack_ready' => 'ready_to_send',
        'ready' => 'ready_to_send', 'ready_to_send' => 'ready_to_send', 'ready_to_deliver' => 'ready_to_send', 'ready_for_delivery' => 'ready_to_send', 'ready_to_ship' => 'ready_to_send', 'ready_for_shipment' => 'ready_to_send',
        'sent' => 'sent', 'shipped' => 'sent', 'dispatched' => 'sent',
        'in_transit' => 'in_transit', 'intransit' => 'in_transit',
        'receiving' => 'receiving', 'receival' => 'receiving',
        'partial' => 'partial', 'partially_received' => 'partial', 'partial_receiving' => 'partial',
        'received' => 'received', 'complete' => 'received', 'completed' => 'received', 'done' => 'received',
        'cancelled' => 'cancelled', 'canceled' => 'cancelled'
      ];
      return $map[$u] ?? $u;
    };
    foreach ($rows as &$__r) { $__r['state'] = $normalizeState($__r['state'] ?? ''); } unset($__r);

    // Enrich outlet names
    try {
      if (!empty($rows)) {
        $ids = [];
        foreach ($rows as $r) { if (!empty($r['from'])) $ids[$r['from']] = true; if (!empty($r['to'])) $ids[$r['to']] = true; }
        $idList = array_keys($ids);
        if (count($idList) > 0) {
          $chunk = array_slice($idList, 0, 500);
          $placeholders = implode(',', array_fill(0, count($chunk), '?'));
          $stmt = $pdo->prepare("SELECT id, name FROM vend_outlets WHERE id IN ($placeholders)");
          $stmt->execute($chunk);
          $map = [];
          while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $map[(string)$row['id']] = (string)($row['name'] ?? ''); }
          foreach ($rows as &$r) {
            $r['from_name'] = $map[(string)($r['from'] ?? '')] ?? '';
            $r['to_name'] = $map[(string)($r['to'] ?? '')] ?? '';
          }
          unset($r);
        }
      }
    } catch (Throwable $e) { /* ignore name enrichment errors */ }

    // Apply post-filters for name queries and general q (when not applied in SQL)
    if ($needsPostFilter) {
      $qq = strtolower($q);
      $rows = array_values(array_filter($rows, function($r) use($qq,$outletFrom,$outletTo,$isLikelyId,$state,$stateGroup){
        // q search against id and outlet names/ids
        $okQ = ($qq==='') ? true : (
          (strpos((string)$r['transfer_id'], $qq) !== false) ||
          (stripos((string)($r['from'] ?? ''), $qq) !== false) ||
          (stripos((string)($r['to'] ?? ''), $qq) !== false) ||
          (stripos((string)($r['from_name'] ?? ''), $qq) !== false) ||
          (stripos((string)($r['to_name'] ?? ''), $qq) !== false)
        );
        // exact state filter when provided
        $okState = true;
        if ($state !== '') { $okState = (strcasecmp((string)($r['state'] ?? ''), $state) === 0); }
        // group filter applied over normalized states
        $okGroup = true;
        if ($stateGroup !== '') {
          $grp = [ 'open' => ['draft','packing','ready_to_send'], 'in_motion' => ['sent','in_transit'], 'arriving' => ['receiving','partial'] ];
          $st = strtolower((string)($r['state'] ?? ''));
          $okGroup = isset($grp[$stateGroup]) ? in_array($st, $grp[$stateGroup], true) : true;
        }
        // outlet from name filter
        $okFrom = true;
        if ($outletFrom !== '' && !$isLikelyId($outletFrom)) {
          $ff = strtolower($outletFrom);
          $okFrom = (stripos((string)($r['from_name'] ?? ''), $ff) !== false);
        }
        // outlet to name filter
        $okTo = true;
        if ($outletTo !== '' && !$isLikelyId($outletTo)) {
          $tt = strtolower($outletTo);
          $okTo = (stripos((string)($r['to_name'] ?? ''), $tt) !== false);
        }
        return $okQ && $okFrom && $okTo && $okState && $okGroup;
      }));
      // Sort newest first, then paginate in-memory
      usort($rows, function($a,$b){ return ($b['transfer_id'] <=> $a['transfer_id']); });
      // Historic/current cap before pagination
      if (!$historic && count($rows) > 500) { $rows = array_slice($rows, 0, 500); }
      if ($historic && count($rows) > 5000) { $rows = array_slice($rows, 0, 5000); }
      $total = count($rows);
      $totalPages = max(1, (int)ceil($total / $pageSize));
      if ($page > $totalPages) { $page = $totalPages; }
      $offset = ($page - 1) * $pageSize;
      $rows = array_slice($rows, $offset, $pageSize);
      jresp(true, [
        'rows' => $rows,
        'pagination' => [
          'total' => $total,
          'page' => $page,
          'page_size' => $pageSize,
          'total_pages' => $totalPages,
        ],
      ], 200);
      return;
    }

    // If we got here, pagination already applied
    jresp(true, [
      'rows' => $rows,
      'pagination' => [
        'total' => $total,
        'page' => $page,
        'page_size' => $pageSize,
        'total_pages' => $totalPages,
      ],
    ], 200);
    return;
  }
} catch (Throwable $e) {
  // Fall back to DevState below
}

// Fallback: DevState (legacy in-memory store)
$all = DevState::loadAll();
foreach ($all as $tid => $row) {
  $id = (int)$tid;
  $st = (string)($row['state'] ?? '');
  $from = (string)($row['outlet_from'] ?? '');
  $to = (string)($row['outlet_to'] ?? '');
  if ($state !== '' && strcasecmp($state, $st) !== 0) continue;
  if ($stateGroup !== '') {
    $grp = [ 'open' => ['draft','packing','ready_to_send'], 'in_motion' => ['sent','in_transit'], 'arriving' => ['receiving','partial'] ];
    if (!isset($grp[$stateGroup]) || !in_array($st, $grp[$stateGroup], true)) continue;
  }
  $rows[] = [
    'transfer_id' => $id,
    'state' => $st,
    'from' => $from,
    'to' => $to,
    'created_at' => (string)($row['created_at'] ?? ''),
    'updated_at' => (string)($row['updated_at'] ?? ''),
    'last_opened_at' => (string)($row['last_opened_at'] ?? ''),
    'last_edited_at' => (string)($row['last_edited_at'] ?? ''),
    'last_touched_at' => (string)($row['last_touched_at'] ?? ''),
    'inaccuracies' => (int)(is_array($row['inaccuracies'] ?? null) ? count($row['inaccuracies']) : (int)($row['inaccuracies'] ?? 0)),
  ];
}
usort($rows, function($a,$b){ return $b['transfer_id'] <=> $a['transfer_id']; });
// Enrich names if possible
try {
  if (function_exists('cis_pdo') && !empty($rows)) {
    $ids = [];
    foreach ($rows as $r) { if (!empty($r['from'])) $ids[$r['from']] = true; if (!empty($r['to'])) $ids[$r['to']] = true; }
    $idList = array_keys($ids);
    if (count($idList) > 0) {
      $chunk = array_slice($idList, 0, 500);
      $placeholders = implode(',', array_fill(0, count($chunk), '?'));
      $pdo = cis_pdo();
      $stmt = $pdo->prepare("SELECT id, name FROM vend_outlets WHERE id IN ($placeholders)");
      $stmt->execute($chunk);
      $map = [];
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $map[(string)$row['id']] = (string)($row['name'] ?? ''); }
      foreach ($rows as &$r) {
        $r['from_name'] = isset($map[(string)($r['from'] ?? '')]) ? $map[(string)$r['from']] : '';
        $r['to_name'] = isset($map[(string)($r['to'] ?? '')]) ? $map[(string)$r['to']] : '';
      }
      unset($r);
    }
  }
} catch (Throwable $e) { /* soft-fail */ }

// Text filters (fallback mode)
if ($q !== '') {
  $qq = strtolower($q);
  $rows = array_values(array_filter($rows, function($r) use ($qq){
    return (strpos((string)$r['transfer_id'], $qq) !== false)
      || (stripos((string)($r['from'] ?? ''), $qq) !== false)
      || (stripos((string)($r['to'] ?? ''), $qq) !== false)
      || (stripos((string)($r['from_name'] ?? ''), $qq) !== false)
      || (stripos((string)($r['to_name'] ?? ''), $qq) !== false);
  }));
}
if ($outletFrom !== '') {
  $f = $outletFrom; $ff = strtolower($f); $byId = $isLikelyId($f);
  $rows = array_values(array_filter($rows, function($r) use ($ff,$f,$byId){
    if ($byId) { return strcasecmp((string)($r['from'] ?? ''), $f) === 0 || stripos((string)($r['from_name'] ?? ''), $ff) !== false; }
    return stripos((string)($r['from_name'] ?? ''), $ff) !== false;
  }));
}
if ($outletTo !== '') {
  $t = $outletTo; $tt = strtolower($t); $byId = $isLikelyId($t);
  $rows = array_values(array_filter($rows, function($r) use ($tt,$t,$byId){
    if ($byId) { return strcasecmp((string)($r['to'] ?? ''), $t) === 0 || stripos((string)($r['to_name'] ?? ''), $tt) !== false; }
    return stripos((string)($r['to_name'] ?? ''), $tt) !== false;
  }));
}
if (!$historic && count($rows) > 500) { $rows = array_slice($rows, 0, 500); }
if ($historic && count($rows) > 5000) { $rows = array_slice($rows, 0, 5000); }

$total = count($rows);
$totalPages = max(1, (int)ceil($total / $pageSize));
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $pageSize;
$rows = array_slice($rows, $offset, $pageSize);

jresp(true, [
  'rows' => $rows,
  'pagination' => [
    'total' => $total,
    'page' => $page,
    'page_size' => $pageSize,
    'total_pages' => $totalPages,
  ],
], 200);
