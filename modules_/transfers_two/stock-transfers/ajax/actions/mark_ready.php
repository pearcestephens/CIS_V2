<?php
// mark_ready.php (unified)
$tid = (int)($_POST['transfer_id'] ?? 0);
if ($tid<=0) jresp(false,'Missing transfer_id',400);
try {
  $ok = markTransferReady_wrapped($tid, $__ajax_context['uid'], (int)$__ajax_context['simulate'], $__ajax_context['request_id']);
  if (!($ok['success'] ?? false)) jresp(false, $ok['error']['message'] ?? ($ok['error'] ?? 'Failed to mark ready'));
  $data = (array)($ok['data'] ?? []); $data['request_id'] = $__ajax_context['request_id'];
  jresp(true, $data);
} catch (Throwable $e) { error_log('[transfers.stock-transfers.mark_ready]['.$__ajax_context['request_id'].'] '.$e->getMessage()); jresp(false,'Server error',500);} 
