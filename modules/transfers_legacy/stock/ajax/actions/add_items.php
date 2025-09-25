<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/TransferService.php';
$svc = new TransferService();
$tid = (int)($_POST['transfer_id'] ?? 0);
$items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];
if ($tid<=0 || empty($items)) jresp(false, 'transfer_id and items[] required', 400);
try {
  $res = $svc->addItems($tid, $items, (int)$__ajax_context['uid']);
  jresp(true, $res, 200);
} catch (Throwable $e) {
  jresp(false, $e->getMessage(), 400);
}
