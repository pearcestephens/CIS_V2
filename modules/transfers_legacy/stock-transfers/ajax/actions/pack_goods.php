<?php
// pack_goods.php (unified)
// Input: transfer_id, items (JSON of {product_id, qty_picked})
$tid = (int)($_POST['transfer_id'] ?? 0);
if ($tid<=0) jresp(false,'Missing transfer_id',400);
$items = [];
if (isset($_POST['items'])) { $tmp = json_decode((string)$_POST['items'], true); if (is_array($tmp)) $items = $tmp; }
if (!$items) jresp(false,'No items',400);
try {
  $ok = packGoods_wrapped($tid, $items, $__ajax_context['uid'], (int)$__ajax_context['simulate'], $__ajax_context['request_id']);
  if (!($ok['success'] ?? false)) jresp(false, $ok['error']['message'] ?? ($ok['error'] ?? 'Failed to pack goods'));
  $data = (array)($ok['data'] ?? []); $data['request_id'] = $__ajax_context['request_id'];
  jresp(true, $data);
} catch (Throwable $e) { error_log('[transfers.stock-transfers.pack_goods]['.$__ajax_context['request_id'].'] '.$e->getMessage()); jresp(false,'Server error',500);} 
