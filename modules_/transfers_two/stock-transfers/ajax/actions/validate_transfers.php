<?php
/**
 * validate_transfers.php
 * Input: transfer_ids (CSV or array)
 * Output: { found: [{id,status,outlet_from,outlet_to,vend_number}], missing: [id,...] }
 */

declare(strict_types=1);

$raw = $_POST['transfer_ids'] ?? '';
$ids = [];
if (is_string($raw)) { foreach (explode(',', $raw) as $v) { $n=(int)trim($v); if($n>0) $ids[]=$n; } }
elseif (is_array($raw)) { foreach ($raw as $v){ $n=(int)$v; if($n>0) $ids[]=$n; } }
$ids = array_values(array_unique($ids));
if (!$ids) { jresp(true, ['found'=>[], 'missing'=>[]]); }

try {
  $pdo = stx_pdo();
  $found = []; $missing = [];
  if (stx_table_exists($pdo,'transfers')){
    // Build IN clause safely
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, status, outlet_from, outlet_to, vend_number FROM transfers WHERE id IN ($in)");
    $stmt->execute($ids);
    $map = [];
    while ($r = $stmt->fetch()) { $map[(int)$r['id']] = $r; }
    foreach ($ids as $id) {
      if (isset($map[$id])) {
        $r = $map[$id];
        $found[] = [
          'id' => (int)$r['id'],
          'status' => (string)($r['status'] ?? ''),
          'outlet_from' => (string)($r['outlet_from'] ?? ''),
          'outlet_to' => (string)($r['outlet_to'] ?? ''),
          'vend_number' => (string)($r['vend_number'] ?? ''),
        ];
      } else { $missing[] = (int)$id; }
    }
  } else { $missing = $ids; }
  jresp(true, ['found'=>$found, 'missing'=>$missing]);
} catch (Throwable $e) {
  error_log('[transfers.stock-transfers.validate_transfers]['.$__ajax_context['request_id'].'] '.$e->getMessage());
  jresp(false, 'Server error', 500);
}
