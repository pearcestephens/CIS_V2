<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/csrf.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/middleware/kernel.php';
require_once dirname(__DIR__, 2) . '/_shared/QueueClient.php';
require_once dirname(__DIR__) . '/lib/PackHelper.php';
require_once dirname(__DIR__) . '/lib/TokensResolver.php';
require_once dirname(__DIR__) . '/lib/PrintAgent.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

 $ctx = ['request_id' => null];

try {
    $pipeline = mw_pipeline([
        mw_trace(),
        mw_security_headers(),
        mw_json_or_form_normalizer(),
        mw_csrf_or_api_key(getenv('TEST_CLI_API_KEY') ?: 'TEST-CLI-KEY-123'),
        mw_validate_content_type(['application/json', 'application/x-www-form-urlencoded']),
        mw_content_length_limit(512 * 1024),
        mw_rate_limit('transfers.stock.queue.label', 120, 60),
        mw_idempotency(),
    ]);
    $ctx = $pipeline([]);
    $in  = $ctx['input'] ?? [];

    $transferId = (int) ($in['transfer_pk'] ?? 0);
    $carrier    = strtoupper((string) ($in['carrier'] ?? 'GSS'));
    $plan       = is_array($in['parcel_plan'] ?? null) ? $in['parcel_plan'] : ['parcels' => []];
    $idk        = isset($in['idempotency_key']) ? (string) $in['idempotency_key'] : null;

    if ($transferId <= 0) {
        http_response_code(400);
        echo json_encode([
            'ok'         => false,
            'error'      => 'transfer_pk required',
            'request_id' => $ctx['request_id'] ?? null,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $pdo = db();
    $transferStmt = $pdo->prepare('SELECT id, public_id, vend_number, outlet_from FROM transfers WHERE id = :id LIMIT 1');
    $transferStmt->execute([':id' => $transferId]);
    $transferRow = $transferStmt->fetch(PDO::FETCH_ASSOC);

    if (!$transferRow) {
        http_response_code(404);
        echo json_encode([
            'ok'         => false,
            'error'      => 'transfer_not_found',
            'request_id' => $ctx['request_id'] ?? null,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $queueClient   = new TransferQueueClient();
    $queueResponse = $queueClient->label($transferId, $plan, $carrier, $idk);
    $queueOk       = (bool) ($queueResponse['ok'] ?? $queueResponse['success'] ?? false);

    if (!$queueOk) {
        http_response_code(422);
        echo json_encode([
            'ok'         => false,
            'error'      => $queueResponse['error'] ?? 'queue_failed',
            'request_id' => $ctx['request_id'] ?? null,
            'queue'      => $queueResponse,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $remoteParcels = extract_parcels_from_queue($queueResponse);
    foreach ($remoteParcels as $idx => $parcel) {
        if (!isset($plan['parcels'][$idx])) {
            continue;
        }
        $tracking = $parcel['tracking'] ?? $parcel['tracking_number'] ?? null;
        if ($tracking) {
            $plan['parcels'][$idx]['tracking_number'] = $tracking;
        }
    }

    $helper   = new \CIS\Transfers\Stock\PackHelper();
    $labelRes = $helper->generateLabel($transferId, $carrier, $plan);

    if (!$labelRes['ok']) {
        http_response_code(500);
        echo json_encode([
            'ok'         => false,
            'error'      => $labelRes['error'] ?? 'label_persist_failed',
            'request_id' => $ctx['request_id'] ?? null,
            'queue'      => $queueResponse,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $parcelsPersisted = $labelRes['parcels'] ?? [];
    foreach ($parcelsPersisted as $idx => $localParcel) {
        $remote = $remoteParcels[$idx] ?? [];
        $trackingNumber = $remote['tracking'] ?? $remote['tracking_number'] ?? null;
        $trackingUrl    = $remote['tracking_url'] ?? $remote['label_url'] ?? null;
        if ($trackingNumber || $trackingUrl) {
            $helper->setParcelTracking(
                $transferId,
                (int) ($localParcel['id'] ?? 0) ?: null,
                (int) ($localParcel['box_number'] ?? ($idx + 1)),
                $carrier,
                $trackingNumber,
                $trackingUrl
            );
        }
    }

    $printResult = null;
    $labelsForPrint = extract_labels_for_print($queueResponse, $remoteParcels);
    $shouldPrint    = should_print_now($carrier, $plan, $queueResponse);
    if ($shouldPrint) {
        if ($labelsForPrint) {
            $tokens = [];
            if (!empty($transferRow['outlet_from'])) {
                try {
                    $tokens = TokensResolver::forOutlet((string) $transferRow['outlet_from']);
                } catch (\Throwable $e) {
                    $tokens = [];
                }
            }
            $printMeta = [
                'transfer_id'   => $transferId,
                'transfer_code' => $transferRow['public_id'] ?? $transferRow['vend_number'] ?? null,
                'carrier'       => $carrier,
            ];
            $printResult = PrintAgent::enqueue($labelsForPrint, $tokens, $printMeta);
        } else {
            $printResult = ['ok' => false, 'error' => 'no_label_assets'];
        }
    }

    $helper->log($transferId, 'label.queue', [
        'carrier' => $carrier,
        'plan'    => $plan,
        'queue'   => $queueResponse,
    ]);
    $helper->audit($transferId, 'label.queue', [
        'carrier' => $carrier,
        'queue'   => $queueResponse,
    ]);

    $payload = [
        'ok'           => true,
        'shipment_id'  => $labelRes['shipment_id'] ?? null,
        'parcels'      => $parcelsPersisted,
        'queue'        => $queueResponse,
        'print'        => $printResult,
        'request_id'   => $ctx['request_id'] ?? null,
    ];

    if (!empty($ctx['__idem'])) {
        mw_idem_store($ctx, $payload);
    }

    http_response_code(200);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'         => false,
        'error'      => 'exception',
        'message'    => $e->getMessage(),
        'request_id' => $ctx['request_id'] ?? null,
    ], JSON_UNESCAPED_SLASHES);
}

/**
 * @return array<int, array<string, mixed>>
 */
function extract_parcels_from_queue(array $queueResponse): array
{
    $paths = [
        $queueResponse['parcels'] ?? null,
        $queueResponse['data']['parcels'] ?? null,
        $queueResponse['labels']['parcels'] ?? null,
    ];
    foreach ($paths as $candidate) {
        if (is_array($candidate)) {
            return array_values(array_filter($candidate, 'is_array'));
        }
    }
    return [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function extract_labels_for_print(array $queueResponse, array $remoteParcels): array
{
    $labels = [];
    $sources = [
        $queueResponse['labels'] ?? null,
        $queueResponse['data']['labels'] ?? null,
    ];
    foreach ($sources as $source) {
        if (is_array($source)) {
            foreach ($source as $label) {
                if (!is_array($label)) {
                    continue;
                }
                $url = $label['url'] ?? $label['label_url'] ?? null;
                if (!$url) {
                    continue;
                }
                $labels[] = [
                    'url'       => $url,
                    'file_type' => $label['file_type'] ?? infer_file_type($url),
                    'copies'    => (int) ($label['copies'] ?? 1),
                ];
            }
        }
    }

    if (!$labels) {
        foreach ($remoteParcels as $parcel) {
            if (!is_array($parcel)) {
                continue;
            }
            $url = $parcel['label_url'] ?? $parcel['label'] ?? null;
            if (!$url) {
                continue;
            }
            $labels[] = [
                'url'       => $url,
                'file_type' => $parcel['file_type'] ?? infer_file_type($url),
                'copies'    => (int) ($parcel['copies'] ?? 1),
            ];
        }
    }

    return $labels;
}

function infer_file_type(string $url): string
{
    if (str_ends_with(strtolower($url), '.zpl')) {
        return 'zpl';
    }
    if (str_ends_with(strtolower($url), '.png')) {
        return 'png';
    }
    return 'pdf';
}

function should_print_now(string $carrier, array $plan, array $queueResponse): bool
{
    if (!empty($plan['options']['nzpost']['print_now'])) {
        return true;
    }
    if (!empty($plan['options']['print_agent']['print_now'])) {
        return true;
    }
    if (!empty($queueResponse['print_now'])) {
        return true;
    }
    return false;
}
