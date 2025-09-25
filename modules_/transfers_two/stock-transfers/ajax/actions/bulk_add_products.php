<?php
/**
 * bulk_add_products.php
 * Input:
 *  - transfer_ids: CSV string or array of ints
 *  - lines: JSON array of { product_id, qty }
 * Behavior:
 *  - Invokes addProductsToTransfer_wrapped for each transfer id
 *  - Returns per-transfer results and a summary
 */

declare(strict_types=1);

$rawIds = $_POST['transfer_ids'] ?? '';
$ids = [];
if (is_string($rawIds)) {
  foreach (explode(',', $rawIds) as $v) { $n = (int)trim($v); if ($n>0) $ids[] = $n; }
} elseif (is_array($rawIds)) {
  foreach ($rawIds as $v) { $n = (int)$v; if ($n>0) $ids[] = $n; }
}
$ids = array_values(array_unique($ids));
if (!$ids) { jresp(false, 'No transfer IDs provided', 400); }

$lines = [];
if (isset($_POST['lines'])) {
  $tmp = json_decode((string)$_POST['lines'], true);
  if (is_array($tmp)) $lines = $tmp;
}
if (!$lines) jresp(false, 'No product lines provided', 400);

// Use unified ajax context if needed (currently not used directly here)
//$simulate = (int)($__ajax_context['simulate'] ?? 0);

$results = [];
$okCount = 0; $failCount = 0;
foreach ($ids as $tid) {
  try {
    $res = addProductsToTransfer_wrapped((int)$tid, $lines, $__ajax_context['uid'], (int)$__ajax_context['simulate'], null, $__ajax_context['request_id']);
    $success = (bool)($res['success'] ?? false);
    $results[] = [ 'transfer_id' => (int)$tid, 'success' => $success, 'data' => $res['data'] ?? null, 'error' => $res['error'] ?? null ];
    if ($success) $okCount++; else $failCount++;
  } catch (Throwable $e) {
    $results[] = [ 'transfer_id' => (int)$tid, 'success' => false, 'error' => 'Server error' ];
    $failCount++;
  }
}

jresp(true, [
  'results' => $results,
  'summary' => [ 'requested' => count($ids), 'succeeded' => $okCount, 'failed' => $failCount ],
  'request_id' => $__ajax_context['request_id']
]);
