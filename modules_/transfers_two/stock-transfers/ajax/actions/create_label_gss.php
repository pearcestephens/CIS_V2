<?php
// create_label_gss.php (stock-transfers)
// Use the real GSS API wrapper if available (createGssLabel_wrapped), passing multi-box packages.
$tid = (int)($_POST['transfer_id'] ?? 0);
if ($tid<=0) jresp(false,'Missing transfer_id',400);
$service = (string)($_POST['service'] ?? ($_POST['service_code'] ?? ''));
$reference = (string)($_POST['reference'] ?? '');
$signature = isset($_POST['signature']) ? (int)$_POST['signature'] : 0;
$saturday  = isset($_POST['saturday']) ? (int)$_POST['saturday'] : 0;
$packagesJson = isset($_POST['packages']) ? (string)$_POST['packages'] : '[]';
$packagesArr = [];
if ($packagesJson !== '') {
  $packagesArr = json_decode($packagesJson, true);
  if (json_last_error() !== JSON_ERROR_NONE) { jresp(false, 'Invalid packages JSON', 400); }
  if (!is_array($packagesArr)) $packagesArr = [];
}
$parcelsCount = is_array($packagesArr) ? count($packagesArr) : 0;
try {
  // Prefer the shared GSS wrapper used by View Web Outlet for real API calls
  if (function_exists('createGssLabel_wrapped')) {
    $extras = ['signature'=>$signature,'saturday'=>$saturday,'packages'=>$packagesArr];
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
    if (!($ok['success'] ?? false)) jresp(false, $ok['error']['message'] ?? ($ok['error'] ?? 'Failed to create GSS label'));
    $data=(array)($ok['data'] ?? []); $data['request_id']=$__ajax_context['request_id']; jresp(true,$data);
  }

  // Fallback to module wrapper (may simulate if no client available)
  if (!function_exists('gssCreateShipment_wrapped')) require_once __DIR__ . '/../tools.php';
  if (function_exists('gssCreateShipment_wrapped')) {
    $ok = gssCreateShipment_wrapped($tid, $packagesArr, $service, $__ajax_context);
    if (!($ok['success'] ?? false)) jresp(false, $ok['error']['message'] ?? ($ok['error'] ?? 'Failed to create GSS label'));
    $data = $ok; unset($data['success']); $data['request_id'] = $__ajax_context['request_id']; jresp(true, $data);
  }
  jresp(false,'Label function missing',501);
} catch (Throwable $e) { error_log('[transfers.stock-transfers.gss]['.$__ajax_context['request_id'].'] '.$e->getMessage()); jresp(false,'Server error',500);} 
