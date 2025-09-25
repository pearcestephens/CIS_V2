<?php
// add_products.php (unified)
// Input: transfer_id, lines (JSON) OR product_id[] + qty[]
$tid = (int)($_POST['transfer_id'] ?? 0);
if ($tid<=0) jresp(false,'Missing transfer_id',400);

$lines = [];
if (isset($_POST['lines'])) {
  $tmp = json_decode((string)$_POST['lines'], true);
  if (is_array($tmp)) $lines = $tmp;
} else if (isset($_POST['product_id']) && isset($_POST['qty'])) {
  $p = (array)$_POST['product_id']; $q = (array)$_POST['qty'];
  $n = min(count($p), count($q));
  for ($i=0;$i<$n;$i++){ $pid = (string)$p[$i]; $qty = (int)$q[$i]; if ($pid!=='' && $qty>0) $lines[] = ['product_id'=>$pid,'qty'=>$qty]; }
}
if (!$lines) jresp(false,'No lines',400);

try {
  $ok = addProductsToTransfer_wrapped($tid, $lines, $__ajax_context['uid'], (int)$__ajax_context['simulate'], null, $__ajax_context['request_id']);
  if (!($ok['success'] ?? false)) jresp(false, $ok['error']['message'] ?? ($ok['error'] ?? 'Failed to add products'));
  $data = (array)($ok['data'] ?? []); $data['request_id'] = $__ajax_context['request_id'];
  jresp(true, $data);
} catch (Throwable $e) { error_log('[transfers.stock-transfers.add_products]['.$__ajax_context['request_id'].'] '.$e->getMessage()); jresp(false,'Server error',500);} 
