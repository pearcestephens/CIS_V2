<?php
// create_label_nzpost.php (unified)
$tid = (int)($_POST['transfer_id'] ?? 0);
$packages = isset($_POST['packages']) ? (string)$_POST['packages'] : '[]';
$service = (string)($_POST['service'] ?? ($_POST['service_code'] ?? ''));
$senderRef = (string)($_POST['reference'] ?? '');
if ($tid<=0) jresp(false,'Missing transfer_id',400);
try {
  $pkgs = json_decode($packages,true); if(!is_array($pkgs)) $pkgs = [];
  $ok = nzpostCreateShipment_wrapped($tid, $pkgs, $service, $senderRef, $__ajax_context);
  if (!($ok['success'] ?? false)) jresp(false, $ok['error']['message'] ?? ($ok['error'] ?? 'Failed to create NZPost label'));
  $data = $ok; unset($data['success']); $data['request_id'] = $__ajax_context['request_id'];
  jresp(true, $data);
} catch (Throwable $e) { error_log('[transfers.stock-transfers.nzpost]['.$__ajax_context['request_id'].'] '.$e->getMessage()); jresp(false,'Server error',500);} 
