<?php
declare(strict_types=1);

/**
 * Controller: Pack page
 * Inputs: transfer (int)
 * Output: $content (HTML), $meta (array)
 * Uses: views/pack.php for the main content
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/csrf.php';
require_once __DIR__ . '/../lib/PackHelper.php';
require_once __DIR__ . '/../lib/TokensResolver.php';

/** @var array $ctx from router */
$pdo     = $ctx['pdo'];
$params  = $ctx['params'];
$tid     = isset($params['transfer']) ? max(0, (int)$params['transfer']) : 0;

// Load transfer metadata from core table
$transfer = null;
if ($tid > 0) {
    $st = $pdo->prepare("SELECT id, public_id, vend_number, status, outlet_from, outlet_to, created_at
                         FROM transfers WHERE id = :id LIMIT 1");
    $st->execute([':id' => $tid]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($row) {
        $transfer = [
            'id'                 => (int) $row['id'],
            'public_code'        => (string) ($row['public_id'] ?? ''),
            'vend_number'        => (string) ($row['vend_number'] ?? ''),
            'status'             => (string) ($row['status'] ?? 'draft'),
            'origin_outlet_id'   => (string) ($row['outlet_from'] ?? ''),
            'dest_outlet_id'     => (string) ($row['outlet_to'] ?? ''),
            'created_at'         => (string) ($row['created_at'] ?? ''),
        ];

        $outStmt = $pdo->prepare("SELECT id, name FROM vend_outlets WHERE id IN (:from, :to)");
        $outStmt->execute([
            ':from' => $transfer['origin_outlet_id'],
            ':to'   => $transfer['dest_outlet_id'],
        ]);
        foreach ($outStmt->fetchAll(PDO::FETCH_ASSOC) as $outlet) {
            if ($outlet['id'] === $transfer['origin_outlet_id']) {
                $transfer['origin_outlet_name'] = $outlet['name'];
            }
            if ($outlet['id'] === $transfer['dest_outlet_id']) {
                $transfer['dest_outlet_name'] = $outlet['name'];
            }
        }
    }
}

$tokens = [
    'outlet_id'   => $transfer['origin_outlet_id'] ?? null,
    'outlet_name' => null,
    'gss_token'   => '',
    'nzpost_api_key' => '',
    'nzpost_subscription_key' => '',
];

if ($transfer && !empty($transfer['origin_outlet_id'])) {
    try {
        $resolved = \TokensResolver::forOutlet((string)$transfer['origin_outlet_id']);
        if ($resolved) {
            $tokens = array_merge($tokens, $resolved);
        }
    } catch (\Throwable $e) {
        // leave defaults; UI will gracefully disable carriers
    }
}

$packHelper = new \CIS\Transfers\Stock\PackHelper();
$itemRows   = $tid > 0 ? $packHelper->listItems($tid) : [];

$totalShipUnits = 0;
$totalWeightG   = 0;

foreach ($itemRows as $row) {
    $units = (int)($row['suggested_ship_units'] ?? ($row['requested_qty'] ?? 0));
    $unitG = (int)($row['unit_g'] ?? 0);
    $totalShipUnits += $units;
    $totalWeightG   += ($units * max(0, $unitG));
}

$metrics = [
    'items_count'      => count($itemRows),
    'total_ship_units' => $totalShipUnits,
    'total_weight_g'   => $totalWeightG,
    'total_weight_kg'  => $totalWeightG > 0 ? round($totalWeightG / 1000, 2) : 0.0,
];

$carrierSupport = [
    'gss'    => !empty($tokens['gss_token']),
    'nzpost' => !empty($tokens['nzpost_api_key']) && !empty($tokens['nzpost_subscription_key']),
];

$requestId = null;
try {
    $requestId = bin2hex(random_bytes(8));
} catch (\Throwable $e) {
    $requestId = bin2hex((string)uniqid('', true));
}

$displayCode = $transfer['public_code'] ?? $transfer['vend_number'] ?? ($tid > 0 ? "Transfer #{$tid}" : 'Transfer');

$packConfig = [
    'transferId'      => $tid,
    'transferCode'    => $displayCode,
    'transferStatus'  => $transfer['status'] ?? null,
    'outlet'          => [
        'id'   => $tokens['outlet_id'] ?? null,
        'name' => $tokens['outlet_name'] ?? ($transfer['origin_outlet_name'] ?? null),
    ],
    'support'         => $carrierSupport,
    'metrics'         => $metrics,
    'endpoints'       => [
        'queue_label'   => '/cisv2/modules/transfers/stock/ajax/queue.label.php',
        'manual_label'  => '/cisv2/modules/transfers/stock/ajax/label.manual.php',
        'finalize_pack' => '/cisv2/modules/transfers/stock/ajax/actions/finalize_pack.php',
    ],
    'csrf'            => cis_csrf_token(),
    'request_id'      => $requestId,
];

$meta = [
    'title' => $tid > 0 ? "Pack {$displayCode}" : 'Pack Transfer',
    'breadcrumb' => [
        ['label'=>'Transfers','href'=>'/module/transfers'],
        ['label'=>'Stock','href'=>'/module/transfers/stock'],
        ['label'=>$tid > 0 ? "Pack #{$tid}" : 'Pack'],
    ],
];

/** Render the view */
$viewFile = __DIR__.'/../views/pack.php';
ob_start();
$transferVar   = $transfer;  // expose as $transferVar to view
$tidVar        = $tid;
$packItems     = $itemRows;
$packMetrics   = $metrics;
$packConfigVar = $packConfig;
$carrierTokens = $tokens;
$carrierSupportVar = $carrierSupport;
require $viewFile;
$content = (string)ob_get_clean();
