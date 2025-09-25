<?php
// save_manual_tracking.php (unified)
$tid = (int)($_POST['transfer_id'] ?? 0);
// Allow a full URL or raw tracking number; strip to a reasonable token (letters/numbers/hyphen)
$raw = (string)($_POST['tracking_number'] ?? '');
if ($raw && filter_var($raw, FILTER_VALIDATE_URL)) {
  // Extract the last path segment or query param value that looks like a tracking id
  $parts = parse_url($raw);
  $candidate = '';
  if (!empty($parts['path'])) {
    $segments = array_values(array_filter(explode('/', $parts['path'])));
    $candidate = end($segments) ?: '';
  }
  if (!$candidate && !empty($parts['query'])) {
    parse_str($parts['query'], $q);
    foreach ($q as $v) { if (is_string($v) && preg_match('/[A-Za-z0-9]/', $v)) { $candidate = $v; break; } }
  }
  $raw = $candidate ?: $raw;
}
$tracking = preg_replace('/[^A-Za-z0-9\-]/','', $raw);
$notes = trim(strip_tags((string)($_POST['notes'] ?? '')));
// Optional carrier hint
$carrier = (string)($_POST['carrier'] ?? 'MANUAL');
if ($tid<=0) jresp(false,'Missing transfer_id',400);
if ($tracking==='') jresp(false,'Missing tracking_number',400);
try {
  $ok = saveManualTracking_wrapped($tid, $tracking, $notes, $__ajax_context['uid'], (int)$__ajax_context['simulate'], $carrier ?: 'MANUAL', '', $__ajax_context['request_id']);
  if (!($ok['success'] ?? false)) jresp(false, $ok['error']['message'] ?? ($ok['error'] ?? 'Failed to save tracking'));
  $data = (array)($ok['data'] ?? []); $data['request_id'] = $__ajax_context['request_id'];
  jresp(true, $data);
} catch (Throwable $e) { error_log('[transfers.stock-transfers.manual_tracking]['.$__ajax_context['request_id'].'] '.$e->getMessage()); jresp(false,'Server error',500);} 
