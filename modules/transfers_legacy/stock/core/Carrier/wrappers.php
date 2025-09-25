<?php
/**
 * Carrier wrappers exposed to AJAX actions (create_label_*), module-local.
 * These intentionally keep the global function names for BC with action scripts.
 */
declare(strict_types=1);

require_once __DIR__ . '/NZPostEShipClient.php';
require_once __DIR__ . '/GSSClient.php';

/**
 * @return array Envelope { success, data|error }
 */
function createNzPostLabel_wrapped(int $transferId, string $service, int $parcels, string $reference, int $userId, int $simulate, string $requestId, array $extras = []) : array
{
    // In non-prod simulate mode, return a fake label without hitting API
    if ($simulate) {
        return [
            'success' => true,
            'data' => [
                'carrier' => 'nzpost',
                'service' => $service,
                'reference' => $reference,
                'tracking_number' => 'SIM-NZP-' . $transferId . '-' . substr($requestId, 0, 6),
                'label_url' => 'https://staff.vapeshed.co.nz/modules/transfers/stock/print/box_sticker.php?transfer=' . $transferId,
                'packages' => (array)($extras['packages'] ?? []),
                'box_count' => $parcels,
            ],
        ];
    }
    $client = new NZPostEShipClient();
    if (!$client->isConfigured()) {
        return ['success'=>false, 'error'=>['code'=>'not_configured','message'=>'NZ Post client not configured']];
    }
    // For brevity, we assume an order exists or is created elsewhere; call createShipment
    $pkgs = (array)($extras['packages'] ?? []);
    $resp = $client->createShipment($transferId, 'TR-' . $transferId, 'NZ_POST', $service, $pkgs, false);
    if (!($resp['ok'] ?? false)) {
        $msg = (string)($resp['error'] ?? '');
        if ($msg === '') {
            $data = (array)($resp['data'] ?? []);
            $msg = (string)($data['message'] ?? ($data['error'] ?? 'Failed'));
            if ($msg === '' && isset($data['errors']) && is_array($data['errors'])) {
                $first = reset($data['errors']);
                if (is_array($first)) { $first = reset($first); }
                if (is_string($first)) { $msg = $first; }
            }
        }
        return ['success'=>false, 'error'=>['code'=>'api_error','message'=> $msg ?: 'Failed', 'status'=> ($resp['status'] ?? 500)]];
    }
    $data = (array)($resp['data'] ?? []);
    return ['success'=>true, 'data'=>[
        'carrier'=>'nzpost', 'service'=>$service, 'reference'=>$reference,
        'tracking_number'=>$data['tracking_number'] ?? ($data['label_number'] ?? ''),
        'label_url' => $data['label_url'] ?? '',
        'packages' => $pkgs,
        'box_count' => $parcels,
    ]];
}

/**
 * @return array Envelope { success, data|error }
 */
function createGssLabel_wrapped(int $transferId, string $service, int $parcels, string $reference, int $userId, int $simulate, string $requestId, array $extras = []) : array
{
    if ($simulate) {
        return [
            'success' => true,
            'data' => [
                'carrier' => 'gss',
                'service' => $service,
                'reference' => $reference,
                'tracking_number' => 'SIM-GSS-' . $transferId . '-' . substr($requestId, 0, 6),
                'label_url' => '',
                'packages' => (array)($extras['packages'] ?? []),
                'box_count' => $parcels,
            ],
        ];
    }
    $client = new GSSClient();
    if (!$client->isConfigured()) {
        return ['success'=>false, 'error'=>['code'=>'not_configured','message'=>'GSS client not configured']];
    }
    // Minimal shipment creation payload; downstream mapping can expand as needed
    $payload = [
        'reference' => $reference ?: ('TR-' . $transferId),
        'service' => $service,
        'parcels' => (array)($extras['packages'] ?? []),
    ];
    $resp = $client->createShipment($payload);
    if (!($resp['ok'] ?? false)) {
        $msg = (string)($resp['error'] ?? '');
        if ($msg === '') {
            $data = (array)($resp['data'] ?? []);
            $msg = (string)($data['message'] ?? ($data['error'] ?? 'Failed'));
            if ($msg === '' && isset($data['errors']) && is_array($data['errors'])) {
                $first = reset($data['errors']);
                if (is_array($first)) { $first = reset($first); }
                if (is_string($first)) { $msg = $first; }
            }
        }
        return ['success'=>false, 'error'=>['code'=>'api_error','message'=> $msg ?: 'Failed', 'status'=> ($resp['status'] ?? 500)]];
    }
    $data = (array)($resp['data'] ?? []);
    return ['success'=>true, 'data'=>[
        'carrier'=>'gss', 'service'=>$service, 'reference'=>$reference,
        'tracking_number'=>$data['tracking'] ?? ($data['connote'] ?? ''),
        'label_url' => $data['label_url'] ?? '',
        'packages' => (array)($extras['packages'] ?? []),
        'box_count' => $parcels,
    ]];
}
