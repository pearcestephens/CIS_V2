<?php
declare(strict_types=1);

$tid = (int)($_POST['transfer_id'] ?? $_GET['transfer_id'] ?? 0);
if ($tid <= 0) jresp(false, 'transfer_id required', 400);

try {
  if (!function_exists('cis_pdo')) { jresp(false, 'DB unavailable', 500); }
  $pdo = cis_pdo();

  $fromId = null; $toId = null; $createdAt = null;
  // Prefer canonical transfers table
  try {
    $st = $pdo->prepare('SELECT outlet_from, outlet_to, created_at FROM transfers WHERE id = ?');
    $st->execute([$tid]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $fromId = (string)($row['outlet_from'] ?? '');
      $toId   = (string)($row['outlet_to'] ?? '');
      $createdAt = (string)($row['created_at'] ?? '');
    }
  } catch (Throwable $e) { /* ignore */ }

  if (!$fromId || !$toId) {
    // Fallback to legacy stock_transfers
    try {
      $st = $pdo->prepare('SELECT outlet_from, outlet_to, created_at FROM stock_transfers WHERE transfer_id = ?');
      $st->execute([$tid]);
      if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $fromId = (string)($row['outlet_from'] ?? '');
        $toId   = (string)($row['outlet_to'] ?? '');
        $createdAt = (string)($row['created_at'] ?? '');
      }
    } catch (Throwable $e) { /* ignore */ }
  }

  $resp = [
    'transfer_id' => $tid,
    'created_at' => $createdAt,
    'from' => null,
    'to'   => null,
  ];

  $ids = [];
  if ($fromId) $ids[] = $fromId; if ($toId) $ids[] = $toId;
  if ($ids) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $sql = 'SELECT id, name, email, physical_phone_number, physical_street_number, physical_street, physical_address_1, physical_address_2, physical_suburb, physical_city, physical_postcode, physical_state, physical_country_id FROM vend_outlets WHERE id IN ('.$ph.')';
    $stm = $pdo->prepare($sql);
    $stm->execute($ids);
    $map = [];
    while ($r = $stm->fetch(PDO::FETCH_ASSOC)) { $map[(string)$r['id']] = $r; }
    $fmtAddr = function(array $r): string {
      $parts = [];
      foreach (['physical_street_number','physical_street','physical_address_1','physical_address_2','physical_suburb','physical_city','physical_postcode','physical_state','physical_country_id'] as $k) {
        $v = trim((string)($r[$k] ?? '')); if ($v !== '') $parts[] = $v;
      }
      return implode(', ', $parts);
    };
    if ($fromId && isset($map[$fromId])) {
      $r = $map[$fromId];
      $resp['from'] = [
        'id' => (string)$fromId,
        'name' => (string)($r['name'] ?? ''),
        'email' => (string)($r['email'] ?? ''),
        'phone' => (string)($r['physical_phone_number'] ?? ''),
        'address' => $fmtAddr($r),
      ];
    }
    if ($toId && isset($map[$toId])) {
      $r = $map[$toId];
      $resp['to'] = [
        'id' => (string)$toId,
        'name' => (string)($r['name'] ?? ''),
        'email' => (string)($r['email'] ?? ''),
        'phone' => (string)($r['physical_phone_number'] ?? ''),
        'address' => $fmtAddr($r),
      ];
    }
  }

  // Friendly display code (non-persisted) to match requested format inspiration
  $ym = $createdAt ? date('Ym', strtotime($createdAt)) : date('Ym');
  $resp['display_code'] = sprintf('TR-STO-%s-%06d', $ym, $tid);

  jresp(true, $resp, 200);
} catch (Throwable $e) {
  error_log('[transfers.stock.get_transfer_header] '.$e->getMessage());
  jresp(false, 'Server error', 500);
}
<?php
declare(strict_types=1);
/**
 * Return header details for a transfer: code, from_name, to_name, state.
 */

$tid = (int)($_POST['transfer_id'] ?? $_GET['transfer_id'] ?? 0);
if ($tid <= 0) jresp(false, 'transfer_id required', 400);

$out = [
  'transfer_id' => $tid,
  'code' => 'TR-' . $tid,
  'from' => null,
  'to' => null,
  'from_name' => '',
  'to_name' => '',
  'state' => '',
  'created_at' => null,
];

try {
  if (function_exists('cis_pdo')) {
    $pdo = cis_pdo();
    // Try canonical transfers first
    $row = null;
    try {
      $st = $pdo->prepare('SELECT id, outlet_from AS `from`, outlet_to AS `to`, status AS state, created_at FROM transfers WHERE id = ?');
      $st->execute([$tid]);
      $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) { /* ignore */ }
    // Fallback to legacy stock_transfers
    if (!$row) {
      try {
        $st = $pdo->prepare('SELECT transfer_id AS id, outlet_from AS `from`, outlet_to AS `to`, status AS state, created_at FROM stock_transfers WHERE transfer_id = ?');
        $st->execute([$tid]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
      } catch (Throwable $e) { /* ignore */ }
    }
    if ($row) {
      $out['from'] = (string)($row['from'] ?? '');
      $out['to'] = (string)($row['to'] ?? '');
      $out['state'] = (string)($row['state'] ?? '');
      $out['created_at'] = (string)($row['created_at'] ?? '');
      // Code format: TR-STO-YYYYMM-<id>
      $ym = '000000';
      if (!empty($out['created_at'])) {
        $ts = strtotime($out['created_at']);
        if ($ts) { $ym = date('Ym', $ts); }
      }
      $out['code'] = 'TR-STO-' . $ym . '-' . $tid;
      // Enrich names
      $names = [];
      $ids = array_filter([$out['from'], $out['to']], function($v){ return !empty($v); });
      if (!empty($ids)) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st2 = $pdo->prepare("SELECT id, name FROM vend_outlets WHERE id IN ($ph)");
        $st2->execute(array_values($ids));
        while ($r = $st2->fetch(PDO::FETCH_ASSOC)) { $names[(string)$r['id']] = (string)($r['name'] ?? ''); }
      }
      $out['from_name'] = $names[$out['from'] ?? ''] ?? '';
      $out['to_name']   = $names[$out['to']   ?? ''] ?? '';
    } else {
      // DEV/testing fallback: use TransferService snapshot when DB row missing
      require_once __DIR__ . '/../../core/TransferService.php';
      $svc = new TransferService();
      $snap = $svc->snapshot($tid) ?: [];
      $out['state'] = (string)($snap['state'] ?? '');
      $out['code'] = 'TR-STO-' . date('Ym') . '-' . $tid;
    }
    jresp(true, $out, 200);
  } else {
    jresp(true, $out, 200);
  }
} catch (Throwable $e) {
  jresp(true, $out, 200);
}
