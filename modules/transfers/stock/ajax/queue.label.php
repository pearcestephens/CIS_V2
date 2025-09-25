<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 2) . '/_shared/QueueClient.php';

try {
    $raw = file_get_contents('php://input') ?: '';
    $in  = json_decode($raw, true) ?: $_POST;

    $tid     = isset($in['transfer_pk']) ? (int)$in['transfer_pk'] : 0;
    $carrier = isset($in['carrier']) ? (string)$in['carrier'] : 'MVP';
    $plan    = is_array($in['parcel_plan'] ?? null) ? $in['parcel_plan'] : ['parcels' => []];
    $idk     = isset($in['idempotency_key']) ? (string)$in['idempotency_key'] : null;

    if ($tid <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>['code'=>'bad_request','message'=>'transfer_pk required']]); exit; }

    $qc = new TransferQueueClient();
    $res = $qc->label($tid, $plan, $carrier, $idk);
    http_response_code(200);
    echo json_encode($res, JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>['code'=>'exception','message'=>$e->getMessage()]]);
}
