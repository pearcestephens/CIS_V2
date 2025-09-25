<?php
$target = (int)($_POST['transfer_id'] ?? 0);
$source = (int)($_POST['source_transfer_id'] ?? 0);
if ($target<=0 || $source<=0) jresp(false,'Missing transfer ids',400);
try { $ok = mergeTransfer_wrapped($target, $source, $__ajax_context['uid'], (int)$__ajax_context['simulate'], $__ajax_context['request_id']); if (!($ok['success'] ?? false)) jresp(false, $ok['error']['message'] ?? ($ok['error'] ?? 'Failed to merge')); $data=(array)($ok['data'] ?? []); $data['request_id']=$__ajax_context['request_id']; jresp(true,$data);} catch(Throwable $e){ error_log('[transfers.stock.merge_transfer]['.$__ajax_context['request_id'].'] '.$e->getMessage()); jresp(false,'Server error',500);}