<?php
/**
 * modules/transfers/stock-transfers/ajax/handler.php
 * Unified AJAX router for Stock Transfers (Outgoing/Pack/Receive).
 * - Auth + CSRF validation
 * - Routes ajax_action to actions/* files
 * - JSON envelope: { success, data|error, request_id }
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$reqId = bin2hex(random_bytes(8));
function jresp($ok, $payload = [], $code = 200) {
  global $reqId; http_response_code($code);
  $body = ['success'=>(bool)$ok,'request_id'=>$reqId];
  if ($ok) { $body['data'] = $payload; } else { $body['error'] = (string)$payload; }
  echo json_encode($body, JSON_UNESCAPED_SLASHES);
  exit;
}

// Bring in tools
require_once __DIR__ . '/tools.php';

try { $userRow = requireLoggedInUser(); } catch (Throwable $e) { jresp(false, 'Not logged in', 401); }
$uid = (int)($_SESSION['userID'] ?? 0); if ($uid<=0) jresp(false,'Unauthorized',403);

// CSRF token
$csrf = $_POST['csrf'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$validCsrf = false;
if (function_exists('verifyCSRFToken')) { $validCsrf = verifyCSRFToken($csrf); }
elseif (!empty($_SESSION['csrf_token'])) { $validCsrf = hash_equals((string)$_SESSION['csrf_token'], (string)$csrf); }
if (!$validCsrf) jresp(false,'Invalid CSRF', 400);

$action = $_POST['ajax_action'] ?? $_GET['ajax_action'] ?? '';
$map = [
  'add_products'        => 'add_products.php',
  'create_label_nzpost' => 'create_label_nzpost.php',
  'create_order_nzpost' => 'create_order_nzpost.php',
  'create_label_gss'    => 'create_label_gss.php',
  'save_manual_tracking'=> 'save_manual_tracking.php',
  'get_shipping_catalog' => 'get_shipping_catalog.php',
  'get_popular_services' => 'get_popular_services.php',
  'sync_shipment'       => 'sync_shipment.php',
  'mark_ready'          => 'mark_ready.php',
  'merge_transfer'      => 'merge_transfer.php',
  'pack_goods'          => 'pack_goods.php',
  'send_transfer'       => 'send_transfer.php',
  'receive_goods'       => 'receive_goods.php',
  'search_products'     => 'search_products.php',
  'bulk_add_products'   => 'bulk_add_products.php',
  'validate_transfers'  => 'validate_transfers.php',
  'list_transfers'      => 'list_transfers.php',
];

if (!isset($map[$action])) jresp(false,'Unknown action', 400);
$path = __DIR__ . '/actions/' . $map[$action];
if (!is_file($path)) jresp(false,'Action not implemented', 501);

$simulate = isset($_POST['simulate']) ? (int)$_POST['simulate'] : 0;
$__ajax_context = ['uid'=>$uid,'request_id'=>$reqId,'simulate'=>$simulate];
require $path;
