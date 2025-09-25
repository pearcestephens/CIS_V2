<?php
/**
 * modules/transfers/stock-transfers/ajax/actions/create_order_nzpost.php
 * Create/Update a Starshipit/eShip order for NZ Post using transfers + vend_outlets.
 * Expects: transfer_id, service_code, packages (JSON), signature, saturday, instructions, attention, destination_override (JSON optional)
 */
$tid = (int)($_POST['transfer_id'] ?? 0);
$packages = isset($_POST['packages']) ? (string)$_POST['packages'] : '[]';
$service = (string)($_POST['service'] ?? ($_POST['service_code'] ?? ''));
$signature = (int)($_POST['signature'] ?? ($_POST['signature_required'] ?? 1));
$saturday = (int)($_POST['saturday'] ?? 0);
$instructions = (string)($_POST['instructions'] ?? '');
$attention = (string)($_POST['attention'] ?? '');
$destOverrideJson = isset($_POST['destination_override']) ? (string)$_POST['destination_override'] : '';
if ($tid<=0) jresp(false,'Missing transfer_id',400);
try {
  $pkgs = json_decode($packages,true); if(!is_array($pkgs)) $pkgs = [];
  $destOverride = $destOverrideJson ? json_decode($destOverrideJson, true) : null;
  if ($destOverride !== null && !is_array($destOverride)) { $destOverride = null; }
  $meta = ['signature'=> (bool)$signature, 'saturday'=> (bool)$saturday, 'instructions'=>$instructions, 'attention'=>$attention];
  if ($destOverride) { $meta['destination_override'] = $destOverride; }
  $out = nzpostCreateOrder_wrapped($tid, $service, $pkgs, $__ajax_context, $meta);
  if (!($out['success'] ?? false)) jresp(false, $out['error'] ?? 'Failed to create NZPost order');
  $data = $out; unset($data['success']); $data['request_id'] = $__ajax_context['request_id'];
  jresp(true, $data);
} catch (Throwable $e) { error_log('[transfers.stock-transfers.nzpost.order]['.$__ajax_context['request_id'].'] '.$e->getMessage()); jresp(false,'Server error',500);} 
