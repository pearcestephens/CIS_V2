<?php
/**
 * https://staff.vapeshed.co.nz/modules/_shared/telemetry_beacon.php
 * Client telemetry beacon endpoint (consent-gated)
 * Purpose: Accepts low-volume UI telemetry (clicks, scroll depth, nav timing) and forwards to security service or local log.
 * Author: GitHub Copilot (AI), for Ecigdis Ltd
 * Last Modified: 2025-09-22
 * Dependencies: app.php (sessions, CSRF), optional assets/services/security/client_beacon.php
 */
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
header('Content-Type: application/json; charset=utf-8');

function tb_resp($ok, $payload = [], $code = 200){ http_response_code($code); echo json_encode(['success'=>$ok,'data'=>$ok?$payload:null,'error'=>$ok?null:$payload], JSON_UNESCAPED_SLASHES); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') tb_resp(false, 'method_not_allowed', 405);

// Parse JSON body (events batch) early so CSRF can be provided in JSON
$raw = file_get_contents('php://input');
if (!$raw || strlen($raw) > 512*1024) tb_resp(false, 'payload_too_large', 413);
$data = json_decode($raw, true);
if (!is_array($data)) tb_resp(false, 'bad_json', 400);

// CSRF check (allow header or JSON body field)
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['csrf'] ?? ($_POST['csrf'] ?? ''));
$validCsrf = false;
if (function_exists('verifyCSRFToken')) { $validCsrf = verifyCSRFToken((string)$csrf); }
elseif (!empty($_SESSION['csrf_token'])) { $validCsrf = hash_equals((string)$_SESSION['csrf_token'], (string)$csrf); }
if (!$validCsrf) tb_resp(false, 'invalid_csrf', 400);

// Auth (must be logged in)
if (!isset($_SESSION)) session_start();
$uid = (int)($_SESSION['userID'] ?? 0);
if ($uid <= 0) tb_resp(false, 'unauthorized', 401);

// Consent gate (client should send consent:true when user opts in)
$consent = !empty($data['consent']);
if (!$consent) tb_resp(true, ['accepted'=>false]);

// Forward to security service if present
$forwarded = false;
$secFile = $_SERVER['DOCUMENT_ROOT'] . '/assets/services/security/client_beacon.php';
if (is_file($secFile)) {
  require_once $secFile;
  if (function_exists('sec_client_beacon')) {
    try { sec_client_beacon($data); $forwarded = true; } catch (Throwable $e) { error_log('[telemetry_beacon.sec] '.$e->getMessage()); }
  }
}

if (!$forwarded) {
  // Append to local log (rotated by ops), redact large fields
  $logDir = __DIR__ . '/logs';
  if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
  $out = $data;
  // Safety: cap event payload sizes
  if (!empty($out['events']) && is_array($out['events'])) {
    foreach ($out['events'] as &$ev) {
      foreach ($ev as $k => &$v) {
        if (is_string($v) && strlen($v) > 2048) { $v = substr($v,0,2048).'…'; }
      }
    }
  }
  $out['uid'] = $uid;
  $line = json_encode($out, JSON_UNESCAPED_SLASHES) . "\n";
  @file_put_contents($logDir . '/client_beacon.log', $line, FILE_APPEND | LOCK_EX);
}

tb_resp(true, ['accepted'=>true]);
