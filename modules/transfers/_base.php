<?php
declare(strict_types=1);

/**
 * modules/transfers/_base.php
 * Shared helpers used across transfers views.
 */

$__root = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__FILE__, 3);
require_once $__root . '/core/bootstrap.php';

// Optional CSRF/DB helpers from core if present
@require_once $__root . '/core/csrf.php';
@require_once $__root . '/core/db.php';

/** Obtain a PDO instance via core db(), or die gracefully. */
function transfers_pdo() {
  if (function_exists('db')) {
    return db();
  }
  throw new RuntimeException('DB bootstrap not available.');
}

/** Request id for correlating UI actions to backend logs. */
function transfers_reqid(): string {
  if (function_exists('cis_request_id')) {
    return (string)cis_request_id();
  }
  try { return bin2hex(random_bytes(16)); }
  catch (Throwable $e) { return substr(bin2hex(uniqid('', true)), 0, 32); }
}

/** CSRF token helper. */
function transfers_csrf(): string {
  if (function_exists('cis_csrf_token')) {
    return (string)cis_csrf_token();
  }
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
  }
  return (string)$_SESSION['csrf_token'];
}

/**
 * Look up runtime tokens for an outlet (used for shipping integrations).
 * Returns keys if present (or empty strings safely).
 */
function transfers_outlet_tokens(?string $outletId): array {
  $out = [
    'outlet_name'              => '',
    'gss_token'                => '',
    'nz_post_api_key'          => '',
    'nz_post_subscription_key' => '',
  ];
  if (!$outletId) return $out;

  try {
    $pdo = transfers_pdo();
    $q = $pdo->prepare("
      SELECT name, gss_token, nz_post_api_key, nz_post_subscription_key
      FROM vend_outlets WHERE id = :id LIMIT 1
    ");
    $q->execute([':id' => $outletId]);
    $row = $q->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($row) {
      $out['outlet_name']              = (string)($row['name'] ?? '');
      $out['gss_token']                = (string)($row['gss_token'] ?? '');
      $out['nz_post_api_key']          = (string)($row['nz_post_api_key'] ?? '');
      $out['nz_post_subscription_key'] = (string)($row['nz_post_subscription_key'] ?? '');
    }
  } catch (Throwable $e) {
    // swallow; UI remains functional
  }
  return $out;
}

/** Resolve outlet tokens by transfer PK (origin_outlet_id on header). */
function transfers_outlet_tokens_by_transfer(int $transferId): array {
  $pdo = transfers_pdo();
  $outletId = '';
  try {
    $q = $pdo->prepare("SELECT origin_outlet_id FROM transfers WHERE id = :t LIMIT 1");
    $q->execute([':t' => $transferId]);
    $outletId = (string)($q->fetchColumn() ?: '');
  } catch (Throwable $e) { /* noop */ }
  $tokens = transfers_outlet_tokens($outletId);
  $tokens['outlet_id'] = $outletId;
  return $tokens;
}

/** Safely expose a PHP array onto window.{key} as a frozen object. */
function transfers_expose_ctx(string $key, array $ctx): void {
  $safe = json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $key  = preg_replace('/[^A-Za-z0-9_]/', '', $key);
  echo "<script>window.$key = Object.freeze($safe);</script>";
}
