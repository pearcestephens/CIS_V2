<?php
$tid = (int)($_POST['transfer_id'] ?? 0);
if ($tid<=0) jresp(false,'Missing transfer_id',400);
$tracking = (string)($_POST['tracking_number'] ?? '');
$carrier = (string)($_POST['carrier'] ?? 'other');
$notes   = (string)($_POST['notes'] ?? '');
try { $ok = saveManualTracking_wrapped($tid, $tracking, $carrier, $notes, $__ajax_context['uid'], (int)$__ajax_context['simulate'], $__ajax_context['request_id']); if (!($ok['success'] ?? false)) jresp(false, $ok['error']['message'] ?? ($ok['error'] ?? 'Failed to save tracking')); $data=(array)($ok['data'] ?? []); $data['request_id']=$__ajax_context['request_id']; jresp(true,$data);} catch(Throwable $e){ error_log('[transfers.stock.save_manual_tracking]['.$__ajax_context['request_id'].'] '.$e->getMessage()); jresp(false,'Server error',500);}