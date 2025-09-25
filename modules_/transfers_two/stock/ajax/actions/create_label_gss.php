<?php
$tid = (int)($_POST['transfer_id'] ?? 0);
if ($tid<=0) jresp(false,'Missing transfer_id',400);
$service = (string)($_POST['service'] ?? '');
$reference = (string)($_POST['reference'] ?? '');
$signature = isset($_POST['signature']) ? (int)$_POST['signature'] : 0;
$saturday  = isset($_POST['saturday']) ? (int)$_POST['saturday'] : 0;
$parcelsRaw = $_POST['parcels'] ?? 1;
$packagesArr = [];
if (is_string($parcelsRaw) && strlen($parcelsRaw) > 1 && $parcelsRaw[0] === '[') {
	$packagesArr = json_decode($parcelsRaw, true);
	if (json_last_error() !== JSON_ERROR_NONE) { jresp(false, 'Invalid parcels JSON', 400); }
}
$parcelsCount = $packagesArr && is_array($packagesArr) ? count($packagesArr) : (int)$parcelsRaw;
try {
	$extras = ['signature'=>$signature, 'saturday'=>$saturday, 'packages'=>$packagesArr];
	if (function_exists('createGssLabel_wrapped')) {
		try {
			$rf = new ReflectionFunction('createGssLabel_wrapped');
			$argc = $rf->getNumberOfParameters();
			if ($argc >= 8) {
				$ok = createGssLabel_wrapped($tid, $service, $parcelsCount, $reference, $__ajax_context['uid'], (int)$__ajax_context['simulate'], $__ajax_context['request_id'], $extras);
			} else {
				$ok = createGssLabel_wrapped($tid, $service, $parcelsCount, $reference, $__ajax_context['uid'], (int)$__ajax_context['simulate'], $__ajax_context['request_id']);
			}
		} catch (Throwable $ie) {
			$ok = createGssLabel_wrapped($tid, $service, $parcelsCount, $reference, $__ajax_context['uid'], (int)$__ajax_context['simulate'], $__ajax_context['request_id']);
		}
	} else {
		jresp(false, 'Label function missing', 501);
	}
	if (!($ok['success'] ?? false)) jresp(false, $ok['error']['message'] ?? ($ok['error'] ?? 'Failed to create label'));
	$data=(array)($ok['data'] ?? []); $data['request_id']=$__ajax_context['request_id']; jresp(true,$data);
} catch(Throwable $e){ error_log('[transfers.stock.create_label_gss]['.$__ajax_context['request_id'].'] '.$e->getMessage()); jresp(false,'Server error',500);} 