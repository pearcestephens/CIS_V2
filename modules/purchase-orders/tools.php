<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Pacific/Auckland');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function req_id(): string {
    $hdr = $_SERVER['HTTP_X_REQUEST_ID'] ?? ('req-' . bin2hex(random_bytes(8)));
    header('X-Request-ID:' . $hdr);
    return $hdr;
}

$action = $_GET['action'] ?? '';

if ($action === 'csrf') {
    $token = bin2hex(random_bytes(16));
    $_SESSION['csrf'] = $token;
    $_SESSION['csrf_token'] = $token;

    header('X-CSRF-Token: ' . $token);
    header('Content-Type:application/json;charset=utf-8');
    echo json_encode([
        'success'    => true,
        'data'       => ['csrf' => $token],
        'request_id' => req_id(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

http_response_code(404);
header('Content-Type:application/json;charset=utf-8');
echo json_encode([
    'success' => false,
    'error'   => ['code' => 'not_found', 'message' => 'Unknown action'],
    'request_id' => req_id(),
], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
