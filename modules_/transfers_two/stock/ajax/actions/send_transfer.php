<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/TransferService.php';
$svc = new TransferService();
$tid = (int)($_POST['transfer_id'] ?? 0);
$shipment = isset($_POST['shipment']) && is_array($_POST['shipment']) ? $_POST['shipment'] : [];
if ($tid<=0) jresp(false, 'transfer_id required', 400);
try {
  $res = $svc->send($tid, (int)$__ajax_context['uid'], $shipment);
  jresp(true, $res, 200);
} catch (Throwable $e) {
  jresp(false, $e->getMessage(), 400);
}