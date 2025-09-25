<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/TransferService.php';
require_once __DIR__ . '/../tools.php';
$svc = new TransferService();
$tid = (int)($_POST['transfer_id'] ?? 0);
if ($tid<=0) jresp(false, 'transfer_id required', 400);
try {
  $before = method_exists($svc, 'snapshot') ? $svc->snapshot($tid) : null;
  $res = $svc->cancel($tid, (int)$__ajax_context['uid']);
  $after = method_exists($svc, 'snapshot') ? $svc->snapshot($tid) : null;
  if (function_exists('stx_set_audit_snapshots')) { stx_set_audit_snapshots($before, $after); }
  jresp(true, $res, 200);
} catch (Throwable $e) {
  jresp(false, $e->getMessage(), 400);
}
