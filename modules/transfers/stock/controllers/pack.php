<?php
declare(strict_types=1);

/**
 * Controller: Pack page
 * Inputs: transfer (int)
 * Output: $content (HTML), $meta (array)
 * Uses: views/pack.php for the main content
 */

require_once CISV2_ROOT . '/core/csrf.php';
require_once __DIR__ . '/../lib/PackHelper.php';
require_once __DIR__ . '/../lib/TokensResolver.php';

/** @var array $ctx from router */
$pdo     = $ctx['pdo'];
$params  = $ctx['params'];
$tid     = isset($params['transfer']) ? max(0, (int)$params['transfer']) : 0;

// Load transfer metadata from core table
$transfer = null;
$destDefaults = [
    'name' => null,
    'company' => null,
    'addr1' => null,
    'addr2' => null,
    'suburb' => null,
    'city' => null,
    'postcode' => null,
    'email' => null,
    'phone' => null,
    'instructions' => null,
    'country' => 'NZ',
];
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

        $outStmt = $pdo->prepare('SELECT id, name FROM vend_outlets WHERE id IN (:from, :to)');
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

        if (!empty($transfer['dest_outlet_id'])) {
            $destRow = null;
            $destFetch = $pdo->prepare('SELECT * FROM vend_outlets WHERE id = :id LIMIT 1');
            $destFetch->execute([':id' => $transfer['dest_outlet_id']]);
            $destRow = $destFetch->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$destRow) {
                $destFetch = $pdo->prepare('SELECT * FROM vend_outlets WHERE code = :code LIMIT 1');
                $destFetch->execute([':code' => $transfer['dest_outlet_id']]);
                $destRow = $destFetch->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            if ($destRow) {
                $pick = static function (array $row, array $candidates, ?string $fallback = null): ?string {
                    foreach ($candidates as $candidate) {
                        if (array_key_exists($candidate, $row) && (string) $row[$candidate] !== '') {
                            return (string) $row[$candidate];
                        }
                    }
                    return $fallback;
                };

                $destDefaults['name'] = $pick($destRow, ['contact_name', 'contact', 'name'], $destDefaults['name']);
                $destDefaults['company'] = $pick($destRow, ['company', 'name'], $destDefaults['company']);
                $destDefaults['addr1'] = $pick($destRow, ['physical_address_1', 'address1', 'addr1', 'street_address', 'street'], $destDefaults['addr1']);
                $destDefaults['addr2'] = $pick($destRow, ['physical_address_2', 'address2', 'addr2'], $destDefaults['addr2']);
                $destDefaults['suburb'] = $pick($destRow, ['physical_suburb', 'suburb', 'district'], $destDefaults['suburb']);
                $destDefaults['city'] = $pick($destRow, ['physical_city', 'city', 'town'], $destDefaults['city']);
                $destDefaults['postcode'] = $pick($destRow, ['physical_postcode', 'postcode', 'post_code'], $destDefaults['postcode']);
                $destDefaults['email'] = $pick($destRow, ['email', 'contact_email'], $destDefaults['email']);
                $destDefaults['phone'] = $pick($destRow, ['physical_phone_number', 'phone', 'contact_phone'], $destDefaults['phone']);
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
        'labels_dispatch' => '/cisv2/modules/transfers/stock/ajax/actions/labels_dispatch.php',
        'finalize_pack'   => '/cisv2/modules/transfers/stock/ajax/actions/finalize_pack_sync.php',
    ],
    'destination'     => $destDefaults,
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
$packDestDefaults = $destDefaults;
$carrierTokens = $tokens;
$carrierSupportVar = $carrierSupport;
require $viewFile;
$content = (string)ob_get_clean();
