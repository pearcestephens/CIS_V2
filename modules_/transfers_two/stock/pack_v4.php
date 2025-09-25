<?php
declare(strict_types=1);

// Accept multiple parameter names for transfer id
$__tid_keys = ['transfer','transfer_id','id','tid','t'];
$transferIdParam = 0; foreach ($__tid_keys as $__k) { if (isset($_GET[$__k]) && (int)$_GET[$__k] > 0) { $transferIdParam = (int)$_GET[$__k]; break; } }
if ($transferIdParam <= 0) {
  $isAjax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
  ) || (
    isset($_SERVER['HTTP_ACCEPT']) && stripos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false
  );
  if ($isAjax) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
    echo json_encode([
      'success' => false,
      'error' => [ 'code' => 'missing_transfer_id', 'message' => 'Transfer ID is required' ],
      'request_id' => $requestId,
    ]);
    exit;
  }
}

// Render via CIS template
$_GET['module'] = 'transfers/stock';
$_GET['view']   = 'pack_v4';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/module.php';
