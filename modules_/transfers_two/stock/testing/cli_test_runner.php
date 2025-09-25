<?php
/**
 * File: modules/transfers/stock/testing/cli_test_runner.php
 * Purpose: CLI smoke tests for transfers stock AJAX endpoints via internal token path.
 * Usage:
 *   php modules/transfers/stock/testing/cli_test_runner.php --token=HEX --actor=18 --tid=12775 [--action=auth|status|finalize|send|receive_partial|receive_final|all] [--sku=SKU123:2,SKU456:1] [--carrier=GSS --tracking=ABC123 --reference=T-12775]
 * Env fallback: INTERNAL_API_TOKEN, INTERNAL_ACTOR_ID, TID
 */
declare(strict_types=1);

// Resolve web root reliably (app.php lives in public_html/)
$WEB_ROOT = realpath(__DIR__ . '/../../../../');
if ($WEB_ROOT === false) { fwrite(STDERR, "Cannot resolve web root.\n"); exit(1); }
// Ensure DOCUMENT_ROOT for includes that depend on it
if (empty($_SERVER['DOCUMENT_ROOT'])) { $_SERVER['DOCUMENT_ROOT'] = $WEB_ROOT; }
require_once $WEB_ROOT . '/app.php';

function out($msg){ echo $msg, "\n"; }

// Emulate minimal handler environment and run an action file directly
function jpost(string $action, array $fields, string $token, int $actor): array {
  $reqId = bin2hex(random_bytes(8));
  $env = 'dev';
  $simulate = 0;

  // Provide the same context that actions expect
  $GLOBALS['__ajax_context'] = [
    'uid' => $actor,
    'request_id' => $reqId,
    'simulate' => $simulate,
    'internal' => true,
    'env' => $env,
  ];
  // Provide a non-exiting jresp compatible with handler signature
  if (!function_exists('jresp')) {
    function jresp($ok, $payload = [], $code = 200){
      $rid = $GLOBALS['__ajax_context']['request_id'] ?? '';
      $resp = ['success'=>(bool)$ok,'request_id'=>$rid];
      if ($ok) { $resp['data'] = $payload; } else { $resp['error'] = is_string($payload) ? $payload : json_encode($payload); }
      $resp['code'] = $code;
      $GLOBALS['__last_response'] = $resp;
      // Do not exit in CLI runner
    }
  }

  // Map actions like the web handler
  $map = [
    'auth_check'         => 'auth_check.php',
    'create_draft'       => 'create_draft.php',
    'add_items'          => 'add_items.php',
    'finalize_pack'      => 'finalize_pack.php',
    'receive_partial'    => 'receive_partial.php',
    'receive_final'      => 'receive_final.php',
    'cancel_transfer'    => 'cancel_transfer.php',
    'get_status'         => 'get_status.php',
    'add_products'        => 'add_products.php',
    'create_label_nzpost' => 'create_label_nzpost.php',
    'create_label_gss'    => 'create_label_gss.php',
    'save_manual_tracking'=> 'save_manual_tracking.php',
    'mark_ready'          => 'mark_ready.php',
    'merge_transfer'      => 'merge_transfer.php',
    'pack_goods'          => 'pack_goods.php',
    'send_transfer'       => 'send_transfer.php',
    'receive_goods'       => 'receive_goods.php',
  ];
  if (!isset($map[$action])) { return ['success'=>false,'error'=>'Unknown action']; }
  $path = __DIR__ . '/../ajax/actions/' . $map[$action];
  if (!is_file($path)) { return ['success'=>false,'error'=>'Action not implemented']; }

  // Populate superglobals as if coming from POST
  $_POST = $fields;
  $_GET = [];
  $_REQUEST = $_POST;

  // Provide $__ajax_context variable in local scope for included actions
  $__ajax_context = $GLOBALS['__ajax_context'];

  // Reset last response container
  unset($GLOBALS['__last_response']);

  // Include the action (it will call jresp())
  try {
    require $path;
  } catch (Throwable $e) {
    return ['success'=>false,'error'=>'Exception: '.$e->getMessage()];
  }
  $res = $GLOBALS['__last_response'] ?? ['success'=>false,'error'=>'No response'];
  return $res;
}

// Parse args
$opts = getopt('', ['token::','actor::','tid::','action::','sku::','carrier::','tracking::','reference::']);
$token = (string)($opts['token'] ?? ($_ENV['INTERNAL_API_TOKEN'] ?? getenv('INTERNAL_API_TOKEN') ?? ''));
$actor = (int)($opts['actor'] ?? ($_ENV['INTERNAL_ACTOR_ID'] ?? getenv('INTERNAL_ACTOR_ID') ?? 0));
$tid   = (int)($opts['tid'] ?? ($_ENV['TID'] ?? getenv('TID') ?? 0));
$action= (string)($opts['action'] ?? 'all');
$skuCsv= (string)($opts['sku'] ?? '');
$carrier = (string)($opts['carrier'] ?? '');
$tracking= (string)($opts['tracking'] ?? '');
$ref     = (string)($opts['reference'] ?? '');

if ($token === '' || $actor <= 0) {
  out('FAIL: token and actor required. Use --token=... --actor=... or set env INTERNAL_API_TOKEN, INTERNAL_ACTOR_ID');
  exit(1);
}

$items = [];
if ($skuCsv !== '') {
  foreach (explode(',', $skuCsv) as $pair) {
    $pair = trim($pair);
    if ($pair === '') continue;
    [$sku,$qty] = array_pad(explode(':', $pair, 2), 2, null);
    if ($sku !== null && $qty !== null) { $items[$sku] = (int)$qty; }
  }
}

$baseInfo = "token=*** hidden *** actor={$actor} tid={$tid}";

function printResult(string $label, array $res): void {
  $ok = $res['success'] ?? false;
  $rid = $res['request_id'] ?? '';
  if ($ok) {
    echo "PASS [$label] request_id={$rid}\n";
  } else {
    $err = is_array($res['error'] ?? null) ? json_encode($res['error']) : (string)($res['error'] ?? 'Unknown error');
    echo "FAIL [$label] request_id={$rid} error={$err}\n";
  }
}

out("CIS Transfers Stock CLI Test — {$baseInfo}");

// 1) auth_check
if ($action === 'auth' || $action === 'all') {
  $res = jpost('auth_check', [], $token, $actor);
  printResult('auth_check', $res);
}

// 2) status
if (($action === 'status' || $action === 'all') && $tid > 0) {
  $res = jpost('get_status', ['transfer_id'=>$tid], $token, $actor);
  printResult('get_status', $res);
}

// 3) finalize
if (($action === 'finalize' || $action === 'all') && $tid > 0) {
  $res = jpost('finalize_pack', ['transfer_id'=>$tid], $token, $actor);
  printResult('finalize_pack', $res);
}

// 4) send
if (($action === 'send' || $action === 'all') && $tid > 0) {
  $fields = ['transfer_id'=>$tid];
  if ($carrier !== '' || $tracking !== '' || $ref !== '') {
    $fields['shipment'] = ['carrier'=>$carrier,'tracking'=>$tracking,'reference'=>$ref];
  }
  $res = jpost('send_transfer', $fields, $token, $actor);
  printResult('send_transfer', $res);
}

// 5) receive_partial
if (($action === 'receive_partial' || $action === 'all') && $tid > 0 && !empty($items)) {
  $res = jpost('receive_partial', ['transfer_id'=>$tid,'items'=>$items], $token, $actor);
  printResult('receive_partial', $res);
}

// 6) receive_final
if (($action === 'receive_final' || $action === 'all') && $tid > 0 && !empty($items)) {
  $res = jpost('receive_final', ['transfer_id'=>$tid,'items'=>$items], $token, $actor);
  printResult('receive_final', $res);
}

out('Done.');
