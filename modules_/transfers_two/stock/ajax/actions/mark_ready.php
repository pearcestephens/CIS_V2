<?php
$tid = (int)($_POST['transfer_id'] ?? 0);
if ($tid<=0) jresp(false,'Missing transfer_id',400);

// Pack-Only mode server-side guard: block submissions when enabled
$__packonly = (int)($_ENV['TRANSFERS_STOCK_PACKONLY'] ?? getenv('TRANSFERS_STOCK_PACKONLY') ?: 0) === 1;
if ($__packonly) {
	jresp(false, 'Pack-Only Mode: submission is disabled. Do not send or do anything with this transfer until confirmed.', 403);
}

try {
	$ok = markTransferReady_wrapped($tid, $__ajax_context['uid'], (int)$__ajax_context['simulate'], $__ajax_context['request_id']);
	if (!($ok['success'] ?? false)) jresp(false, $ok['error']['message'] ?? ($ok['error'] ?? 'Failed to mark ready'));
	$data=(array)($ok['data'] ?? []);
	$data['request_id']=$__ajax_context['request_id'];
	jresp(true,$data);
} catch(Throwable $e){
	error_log('[transfers.stock.mark_ready]['.$__ajax_context['request_id'].'] '.$e->getMessage());
	jresp(false,'Server error',500);
}