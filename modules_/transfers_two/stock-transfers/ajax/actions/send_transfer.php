<?php
// send_transfer.php (unified)
// Input: transfer_id, force(optional)
$tid = (int)($_POST['transfer_id'] ?? 0);
if ($tid<=0) jresp(false,'Missing transfer_id',400);
$force = isset($_POST['force']) ? (bool)$_POST['force'] : false;
try {
  $ok = sendTransfer_wrapped($tid, $__ajax_context['uid'], (int)$__ajax_context['simulate'], $__ajax_context['request_id'], $force);
  if (!($ok['success'] ?? false)) jresp(false, $ok['error']['message'] ?? ($ok['error'] ?? 'Failed to send transfer'));
  $data = (array)($ok['data'] ?? []); $data['request_id'] = $__ajax_context['request_id'];
  jresp(true, $data);
} catch (Throwable $e) { error_log('[transfers.stock-transfers.send_transfer]['.$__ajax_context['request_id'].'] '.$e->getMessage()); jresp(false,'Server error',500);} 
