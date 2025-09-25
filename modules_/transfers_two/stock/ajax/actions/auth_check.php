<?php
/**
 * File: modules/transfers/stock/ajax/actions/auth_check.php
 * Purpose: Diagnostic endpoint to validate internal token auth path in DEV/STAGE.
 * Author: GitHub Copilot (AI), Ecigdis Ltd
 * Last Modified: 2025-09-21
 * Dependencies: Included by ajax/handler.php which bootstraps app.php and defines jresp().
 */
declare(strict_types=1);

// Returns basic context so callers can confirm headers/env are wired correctly
$payload = [
  'env' => $GLOBALS['__ajax_context']['env'] ?? '',
  'internal' => (bool)($GLOBALS['__ajax_context']['internal'] ?? false),
  'uid' => (int)($GLOBALS['__ajax_context']['uid'] ?? 0),
];
if (($payload['env'] ?? '') === '' || !$payload['internal']) {
  jresp(false, 'Auth: session required or invalid internal token', 401);
}
jresp(true, $payload, 200);
