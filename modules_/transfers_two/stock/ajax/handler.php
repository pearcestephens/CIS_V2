<?php
/**
 * modules/transfers/stock/ajax/handler.php
 * Unified AJAX router (renamed path from stock-transfers)
 */

declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$reqId = bin2hex(random_bytes(8));
function jresp($ok, $payload = [], $code = 200){
  global $reqId;
  http_response_code($code);
  $body=['success'=>(bool)$ok,'request_id'=>$reqId];
  if($ok){$body['data']=$payload;} else {$body['error']=(string)$payload;}
  // Attempt to log the action envelope (non-fatal on errors)
  if (function_exists('stx_log_action_envelope')) {
    stx_log_action_envelope((bool)$ok, $payload, (int)$code);
  }
  echo json_encode($body, JSON_UNESCAPED_SLASHES);
  exit;
}

require_once __DIR__ . '/tools.php';
// Load carrier wrappers so create_label_* actions can call module-local functions
$__carrierWrap = realpath(__DIR__ . '/../core/Carrier/wrappers.php');
if ($__carrierWrap && is_file($__carrierWrap)) { require_once $__carrierWrap; }
// mark start time for processing latency
$__stx_start_ts = microtime(true);
// --- Internal token auth (DEV/STAGE only) ---
$env = '';
if (defined('APP_ENV')) { $env = strtolower((string)APP_ENV); }
elseif (defined('ENV')) { $env = strtolower((string)ENV); }
elseif (!empty($_ENV['APP_ENV'])) { $env = strtolower((string)$_ENV['APP_ENV']); }
$isNonProd = !in_array($env, ['prod','production','live'], true);
$internalToken = $_SERVER['HTTP_X_INTERNAL_TOKEN'] ?? '';
$expectedToken = (string)($_ENV['INTERNAL_API_TOKEN'] ?? getenv('INTERNAL_API_TOKEN') ?: '');
$usingInternalAuth = $isNonProd && $internalToken !== '' && $expectedToken !== '' && hash_equals($expectedToken, (string)$internalToken);

if ($usingInternalAuth) {
  $uid = (int)($_SERVER['HTTP_X_ACTOR_ID'] ?? 0);
  if ($uid <= 0) { $uid = (int)($_ENV['INTERNAL_ACTOR_ID'] ?? 1); }
} else {
  // If a token is present in non-prod but not accepted, give a clearer error
  if ($isNonProd && $internalToken !== '') {
    if ($expectedToken === '') { jresp(false, 'Internal token not configured', 401); }
    jresp(false, 'Invalid internal token', 401);
  }
  // Standard session-based auth
  try { $userRow = requireLoggedInUser(); } catch (Throwable $e) { jresp(false, 'Not logged in', 401); }
  $uid = (int)($_SESSION['userID'] ?? 0); if ($uid<=0) jresp(false,'Unauthorized',403);
}

// CSRF: bypass only when using internal token in non-production
if (!$usingInternalAuth) {
  $csrf = $_POST['csrf'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  $validCsrf = false;
  if (function_exists('verifyCSRFToken')) { $validCsrf = verifyCSRFToken($csrf); }
  elseif (!empty($_SESSION['csrf_token'])) { $validCsrf = hash_equals((string)$_SESSION['csrf_token'], (string)$csrf); }
  if (!$validCsrf) jresp(false,'Invalid CSRF', 400);
}

$action = $_POST['ajax_action'] ?? $_GET['ajax_action'] ?? '';
// Single consolidated action map
$map = [
  // Diagnostics (non-prod): returns internal auth status
  'auth_check'            => 'auth_check.php',
  // New unified workflow endpoints
  'create_draft'          => 'create_draft.php',
  'add_items'             => 'add_items.php',
  'finalize_pack'         => 'finalize_pack.php',
  'receive_partial'       => 'receive_partial.php',
  'receive_final'         => 'receive_final.php',
  'cancel_transfer'       => 'cancel_transfer.php',
  'delete_transfer'       => 'delete_transfer.php',
  'get_status'            => 'get_status.php',
  // Dashboard/data
  'get_dashboard_stats'   => 'get_dashboard_stats.php',
  'list_outlets'          => 'list_outlets.php',
  'list_transfers'        => 'list_transfers.php',
  'get_activity'          => 'get_activity.php',
  'get_transfer_header'   => 'get_transfer_header.php',
  'get_shipping_catalog'  => 'get_shipping_catalog.php',
  'list_items'            => 'list_items.php',
  'save_progress'         => 'save_progress.php',
  'get_printers_config'   => 'get_printers_config.php',
  'set_status'            => 'set_status.php',
  'search_products'       => 'search_products.php',
  'get_product_weights'   => 'get_product_weights.php',
  'get_product_attributes'=> 'get_product_attributes.php',
  // Shipping/Widgets
  'get_shipping_summary'  => 'get_shipping_summary.php',
  'get_freight_widgets'   => 'get_freight_widgets.php',
  // Locks (single editor)
  'acquire_lock'          => 'acquire_lock.php',
  'heartbeat_lock'        => 'heartbeat_lock.php',
  'release_lock'          => 'release_lock.php',
  'request_lock'          => 'request_lock.php',
  'respond_lock_request'  => 'respond_lock_request.php',
  'poll_lock'             => 'poll_lock.php',
  // Comments/Notes
  'list_comments'         => 'list_comments.php',
  'add_comment'           => 'add_comment.php',
  'notes_list'            => 'notes_list.php',
  'notes_add'             => 'notes_add.php',
  // Costs
  'get_unit_costs'        => 'get_unit_costs.php',
  // Existing/compat
  'add_products'          => 'add_products.php',
  'create_label_nzpost'   => 'create_label_nzpost.php',
  'create_label_gss'      => 'create_label_gss.php',
  'record_shipment'       => 'record_shipment.php',
  'save_manual_tracking'  => 'save_manual_tracking.php',
  'mark_ready'            => 'mark_ready.php',
  'merge_transfer'        => 'merge_transfer.php',
  'pack_goods'            => 'pack_goods.php',
  'send_transfer'         => 'send_transfer.php',
  'receive_goods'         => 'receive_goods.php',
];
if (!isset($map[$action])) jresp(false,'Unknown action', 400);
$path = __DIR__ . '/actions/' . $map[$action];
if (!is_file($path)) jresp(false,'Action not implemented', 501);

$simulate = isset($_POST['simulate']) ? (int)$_POST['simulate'] : 0;
$__ajax_context = ['uid'=>$uid,'request_id'=>$reqId,'simulate'=>$simulate,'internal'=>$usingInternalAuth,'env'=>$env];
require $path;
