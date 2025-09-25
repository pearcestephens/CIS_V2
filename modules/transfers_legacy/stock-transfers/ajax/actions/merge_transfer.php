<?php
// merge_transfer.php (unified)
$tid = (int)($_POST['transfer_id'] ?? 0);
$from = (int)($_POST['merge_from_id'] ?? 0);
if ($tid<=0 || $from<=0) jresp(false,'Missing ids',400);
if ($tid === $from) jresp(false,'Cannot merge same transfer',400);
try {
  $ok = mergeTransfers_wrapped($tid, $from, $__ajax_context['uid'], (int)$__ajax_context['simulate'], $__ajax_context['request_id']);
  if (!($ok['success'] ?? false)) jresp(false, $ok['error']['message'] ?? ($ok['error'] ?? 'Failed to merge'));
  $data = (array)($ok['data'] ?? []); $data['request_id'] = $__ajax_context['request_id'];
  jresp(true, $data);
} catch (Throwable $e) { error_log('[transfers.stock-transfers.merge]['.$__ajax_context['request_id'].'] '.$e->getMessage()); jresp(false,'Server error',500);} 
