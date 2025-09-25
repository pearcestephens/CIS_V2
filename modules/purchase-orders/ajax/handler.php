<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/bootstrap.php';   // request_id + headers (if present)

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

// Lightweight health probe BEFORE loading tools.php (to avoid app.php login redirect)
$earlyAction = (string)($_GET['ajax_action'] ?? '');
if ($earlyAction === 'health') {
  $rid = function_exists('cis_request_id') ? cis_request_id() : (function(){ try { return bin2hex(random_bytes(16)); } catch (\Throwable $e) { return substr(bin2hex(uniqid('', true)), 0, 32); } })();
  if (!headers_sent()) {
    header('X-Request-ID: ' . $rid, true);
  }
  http_response_code(200);
  echo json_encode([
    'success'    => true,
    'data'       => [
      'module' => 'purchase-orders',
      'status' => 'healthy',
      'time'   => gmdate('c'),
    ],
    'request_id' => $rid,
  ], JSON_UNESCAPED_SLASHES);
  exit;
}

// Load tools after health so we don't trigger any app.php redirects for unauthenticated probes
require_once __DIR__ . '/tools.php';                              // responder, pdo, guards

$uid = po_require_login();

$action = (string)($_POST['ajax_action'] ?? $_GET['ajax_action'] ?? '');
$reqId  = $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8));

$map = [
  'health'         => 'actions/health.php', // optional JSON health (will not be included; early exit above handles GET)
  'get_po'          => 'actions/get_po.php',
  'save_progress'   => 'actions/save_progress.php',
  'submit_partial'  => 'actions/submit_partial.php',
  'submit_final'    => 'actions/submit_final.php',
  'upload_evidence' => 'actions/upload_evidence.php',
  'list_evidence'   => 'actions/list_evidence.php',

  // admin helpers
  'admin.list_receipts'          => 'actions/admin/list_receipts.php',
  'admin.list_events'            => 'actions/admin/list_events.php',
  'admin.list_inventory_requests'=> 'actions/admin/list_inventory_requests.php',
  'admin.retry_request'          => 'actions/admin/retry_request.php',
  'admin.force_resend'           => 'actions/admin/force_resend.php',
];

if ($action === '') {
  po_jresp(false, ['code' => 'no_action', 'message' => 'Missing ajax_action'], 400);
}

if (!isset($map[$action])) {
  po_jresp(false, ['code' => 'unknown_action', 'message' => 'Unknown action'], 404);
}

$GLOBALS['__po_ctx'] = [
  'uid'        => $uid,
  'request_id' => $reqId,
];

try {
  // CSRF on mutating actions
  $needsCsrf = ['save_progress','submit_partial','submit_final','upload_evidence',
                'admin.retry_request','admin.force_resend'];
  if (in_array($action, $needsCsrf, true)) {
    po_verify_csrf();
  }

  require __DIR__ . '/' . $map[$action];
} catch (Throwable $e) {
  po_jresp(false, ['code' => 'exception', 'message' => $e->getMessage()], 500);
}
