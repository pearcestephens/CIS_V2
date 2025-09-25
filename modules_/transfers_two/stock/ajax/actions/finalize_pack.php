<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/TransferService.php';
require_once __DIR__ . '/../tools.php';
$svc = new TransferService();
$tid = (int)($_POST['transfer_id'] ?? 0);
if ($tid<=0) jresp(false, 'transfer_id required', 400);

// Pack-Only mode server-side guard: block submissions when enabled
$__packonly = (int)($_ENV['TRANSFERS_STOCK_PACKONLY'] ?? getenv('TRANSFERS_STOCK_PACKONLY') ?: 0) === 1;
if ($__packonly) {
  jresp(false, 'Pack-Only Mode: submission is disabled. Do not send or do anything with this transfer until confirmed.', 403);
}
try {
  $before = method_exists($svc, 'snapshot') ? $svc->snapshot($tid) : null;
  $res = $svc->markReady($tid, (int)$__ajax_context['uid']);
  $after = method_exists($svc, 'snapshot') ? $svc->snapshot($tid) : null;
  if (function_exists('stx_set_audit_snapshots')) { stx_set_audit_snapshots($before, $after); }
  jresp(true, $res, 200);
} catch (Throwable $e) {
  jresp(false, $e->getMessage(), 400);
}
