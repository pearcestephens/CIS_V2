<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/DevState.php';

$now = time();
$counts = [
  'draft' => 0,
  'packing' => 0,
  'ready_to_send' => 0,
  'sent' => 0,
  'in_transit' => 0,
  'receiving' => 0,
  'partial' => 0,
  'received' => 0,
  'cancelled' => 0,
];

// Prefer DB counts when the canonical table exists; fallback to DevState
try {
  if (function_exists('cis_pdo')) {
    $pdo = cis_pdo();
    $pdo->query('SELECT 1 FROM transfers LIMIT 1');
    $stmt = $pdo->query('SELECT status, COUNT(*) AS c FROM transfers GROUP BY status');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $st = strtolower((string)($row['status'] ?? ''));
      $c = (int)($row['c'] ?? 0);
      if (isset($counts[$st])) { $counts[$st] = $c; }
    }
  } else {
    throw new RuntimeException('PDO not available');
  }
} catch (Throwable $e) {
  // Fallback to DevState snapshot
  $all = DevState::loadAll();
  foreach ($all as $tid => $row) {
    $st = strtolower((string)($row['state'] ?? ''));
    if (isset($counts[$st])) { $counts[$st]++; }
  }
}

$stats = [
  'totals' => $counts,
  'groups' => [
    'open' => (int)(($counts['draft'] ?? 0) + ($counts['packing'] ?? 0) + ($counts['ready_to_send'] ?? 0)),
    'in_motion' => (int)(($counts['sent'] ?? 0) + ($counts['in_transit'] ?? 0)),
    'arriving' => (int)(($counts['receiving'] ?? 0) + ($counts['partial'] ?? 0)),
    'closed' => (int)(($counts['received'] ?? 0) + ($counts['cancelled'] ?? 0)),
  ],
  'updated_at' => date('c', $now),
];

jresp(true, $stats, 200);
