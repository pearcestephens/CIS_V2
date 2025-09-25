<?php declare(strict_types=1);
require_once dirname(__DIR__, 3).'/bootstrap.php';
header('Content-Type: application/json');
try {
  // TODO: verify signature if provided by NZPost
  $in = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
  $tracking = (string)($in['tracking_number'] ?? '');
  $status   = strtoupper((string)($in['status'] ?? ''));
  if (!$tracking) throw new InvalidArgumentException('no tracking');

  $pdo = db_rw();
  $row = $pdo->prepare("SELECT p.id parcel_id, s.transfer_id
                        FROM transfer_parcels p
                        JOIN transfer_shipments s ON s.id=p.shipment_id
                        WHERE p.tracking_number=:tn LIMIT 1");
  $row->execute([':tn'=>$tracking]); $m = $row->fetch();
  if (!$m) { echo '{"ok":true}'; return; }

  $code = match($status) {
    'LABEL_CREATED' => 'LABEL_CREATED',
    'MANIFESTED'    => 'MANIFESTED',
    'IN_TRANSIT'    => 'IN_TRANSIT',
    'OUT_FOR_DELIVERY' => 'OUT_FOR_DELIVERY',
    'DELIVERED'     => 'DELIVERED',
    default         => 'EXCEPTION'
  };
  $newState = match($code) {
    'LABEL_CREATED' => 'labelled',
    'MANIFESTED'    => 'manifested',
    'IN_TRANSIT','OUT_FOR_DELIVERY' => 'in_transit',
    'DELIVERED'     => 'received',
    default         => 'exception'
  };

  $pdo->prepare("INSERT INTO transfer_tracking_events (transfer_id,parcel_id,tracking_number,carrier,event_code,event_text,occurred_at,raw_json)
                 VALUES (:t,:p,:tn,'NZPOST',:c,:text,NOW(),:raw)")
      ->execute([':t'=>$m['transfer_id'], ':p'=>$m['parcel_id'], ':tn'=>$tracking, ':c'=>$code, ':text'=>$status, ':raw'=>json_encode($in)]);

  $pdo->prepare("UPDATE transfer_parcels SET status=:s, updated_at=NOW() WHERE id=:id")->execute([':s'=>$newState, ':id'=>$m['parcel_id']]);

  echo '{"ok":true}';
} catch (Throwable $e) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
