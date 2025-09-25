<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/csrf.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/middleware/kernel.php';
require_once dirname(__DIR__) . '/lib/PackHelper.php';

header('Content-Type: application/json;charset=utf-8');

try {
  $pipe = mw_pipeline([
    mw_trace(),
    mw_security_headers(),
    mw_json_or_form_normalizer(),
    mw_csrf_or_api_key(getenv('TEST_CLI_API_KEY') ?: 'TEST-CLI-KEY-123'),
    mw_validate_content_type(['application/json','application/x-www-form-urlencoded']),
    mw_content_length_limit(512 * 1024),
    mw_rate_limit('transfers.stock.label.create', 60, 60),
    mw_idempotency(),
  ]);
  $ctx = $pipe([]);
  $in  = $ctx['input'];

  $tid    = (int)($in['transfer_pk'] ?? 0);
  $carrier= strtoupper((string)($in['carrier'] ?? 'GSS'));
  $plan   = is_array($in['parcel_plan'] ?? null) ? $in['parcel_plan'] : ['parcels'=>[]];

  if ($tid <= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'transfer_pk required','request_id'=>$ctx['request_id'] ?? null]);
    exit;
  }

  // MVP: PackHelper handles creation + auditing; carrier tokens are resolved by the page/outlet.
  $helper = new \CIS\Transfers\Stock\PackHelper();
  $res = $helper->generateLabel($tid, $carrier, $plan);

  if (!empty($ctx['__idem'])) { mw_idem_store($ctx, $res); }
  http_response_code($res['ok'] ? 200 : 422);
  echo json_encode($res + ['request_id'=>$ctx['request_id'] ?? null], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'exception','request_id'=>$ctx['request_id'] ?? null]);
}
