<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/csrf.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/middleware/kernel.php';

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

  $pdo = db();
  $pdo->beginTransaction();

  // ensure a shipment row exists
  $q = $pdo->prepare('INSERT INTO transfer_shipments(transfer_id, carrier, mode, status, created_at) VALUES(:t,:c,:m,:s, NOW())');
  $q->execute([
    ':t' => $tid,
    ':c' => $carrier,
    ':m' => 'manual',
    ':s' => 'created'
  ]);
  $shipmentId = (int)$pdo->lastInsertId();

  // one parcel; you can extend UI to multiple if needed
  $qp = $pdo->prepare('INSERT INTO transfer_parcels(shipment_id, parcel_number, tracking_number, tracking_url, weight_g, created_at) VALUES(:s, :n, :trk, :url, :wg, NOW())');
  $qp->execute([
    ':s' => $shipmentId,
    ':n' => 1,
    ':trk'=> $tracking,
    ':url'=> null,
    ':wg' => max(0, $weight_g)
  ]);

  // optional: mark shipment as packed (you can leave status updates to later steps)
  $pdo->commit();

  // audit + log (soft fail if tables missing)
  try {
    $a = $pdo->prepare('INSERT INTO transfer_audit_log(transfer_id, event, meta_json, created_at) VALUES(:t,:e,:j,NOW())');
    $a->execute([':t'=>$tid, ':e'=>'manual_label_added', ':j'=>json_encode(['carrier'=>$carrier,'service'=>$service,'tracking'=>$tracking,'weight_g'=>$weight_g], JSON_UNESCAPED_SLASHES)]);
  } catch (\Throwable $e) {}

  echo json_encode(['ok'=>true,'shipment_id'=>$shipmentId,'request_id'=>$ctx['request_id'] ?? null], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'exception','request_id'=>$ctx['request_id'] ?? null]);
}
