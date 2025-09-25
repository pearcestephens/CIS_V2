<?php
declare(strict_types=1);
/**
 * Returns current items on a transfer. DEV/testing uses TransferService snapshot until DB wiring.
 */
$tid = (int)($_POST['transfer_id'] ?? $_GET['transfer_id'] ?? 0);
if ($tid <= 0) jresp(false, 'transfer_id required', 400);

try {
  // If a DB-backed implementation exists, place it here. For now, use snapshot.
  require_once __DIR__ . '/../../core/TransferService.php';
  $svc = new TransferService();
  $snap = $svc->snapshot($tid) ?: [];
  $items = $snap['items'] ?? [];
  if (!is_array($items)) { $items = []; }
  jresp(true, ['items' => array_values($items)], 200);
} catch (Throwable $e) {
  jresp(false, 'Server error', 500);
}
