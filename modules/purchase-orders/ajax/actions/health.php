<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$rid = $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8));
header('X-Request-ID: '.$rid);
session_start();
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
echo json_encode([
  'ok'=>true,
  'csrf'=>'header_or_tools.php',
  'time'=>gmdate('c'),
  'request_id'=>$rid
], JSON_UNESCAPED_SLASHES);
