<?php
declare(strict_types=1);

/**
 * CIS Mini Router (whitelisted endpoints)
 * Query-string routing only, no PATH_INFO required.
 *
 * Usage:
 *   /module.php?endpoint=transfers.stock.pack&transfer=123
 *   /module.php?endpoint=transfers.stock.receive&transfer=123
 */

require_once __DIR__.'/assets/functions/config.php'; // shared env (DB/session/helpers)

// Optional: central auth gate (keeps legacy page-level guards working too)
if (!function_exists('requireLoggedInUser')) {
  function requireLoggedInUser(): array {
    if (empty($_SESSION['userID'])) {
      http_response_code(302);
      header('Location: /login.php');
      exit;
    }
    return ['id' => (int)$_SESSION['userID']];
  }
}
requireLoggedInUser();

// Whitelist map (endpoint => file path)
$ROUTES = [
  'transfers.stock.pack'    => __DIR__.'/modules/transfers/stock/pack.php',
  'transfers.stock.receive' => __DIR__.'/modules/transfers/stock/receive.php',
];

// Resolve and dispatch
$endpoint = (string)($_GET['endpoint'] ?? '');
if (!isset($ROUTES[$endpoint])) {
  http_response_code(404);
  echo '<h1>404 Not Found</h1><p>Unknown endpoint.</p>';
  exit;
}

require $ROUTES[$endpoint];
