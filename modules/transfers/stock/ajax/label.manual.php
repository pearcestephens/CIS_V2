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
    mw_content_length_limit(256 * 1024),
    mw_rate_limit('transfers.stock.label.manual', 60, 60),
  ]);
  $ctx = $pipe([]);
  $in  = $ctx['input'];

  $tid      = (int)($in['transfer_pk'] ?? 0);
  $carrier  = (string)($in['carrier'] ?? 'OTHER');
  $service  = (string)($in['service'] ?? '');
  $tracking = (string)($in['tracking'] ?? '');
  $weight_g = (int)($in['weight_g'] ?? 0);

  if ($tid <= 0 || $tracking === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'transfer_pk and tracking required','request_id'=>$ctx['request_id'] ?? null]);
    exit;
  }

  $helper = new \CIS\Transfers\Stock\PackHelper();
  $carrierCode = strtoupper(trim($carrier) ?: 'INTERNAL');
  $weightInt   = max(0, $weight_g);
  $plan = [
    'reference' => "Transfer #{$tid}",
    'parcels'   => [[
      'weight_g' => $weightInt > 0 ? $weightInt : null,
      'notes'    => $notes,
    ]],
  ];

  $result = $helper->generateLabel($tid, $carrierCode, $plan);
  if (!$result['ok']) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'label_generate_failed','request_id'=>$ctx['request_id'] ?? null]);
    exit;
  }

  $parcelId = $result['parcels'][0]['id'] ?? null;
  $helper->setParcelTracking($tid, $parcelId ? (int)$parcelId : null, 1, $carrierCode, $tracking, null);

  if ($notes !== '') {
    $helper->addPackNote($tid, '[Manual Label] ' . $notes);
  }

  try {
    $helper->audit($tid, 'manual_label_added', [
        'carrier'  => $carrierCode,
        'service'  => $service,
        'tracking' => $tracking,
        'weight_g' => $weightInt,
    ]);
  } catch (\Throwable $e) {
  }

  echo json_encode([
    'ok'          => true,
    'shipment_id' => $result['shipment_id'] ?? null,
    'parcel_id'   => $parcelId,
    'request_id'  => $ctx['request_id'] ?? null,
  ], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'exception','request_id'=>$ctx['request_id'] ?? null]);
}
