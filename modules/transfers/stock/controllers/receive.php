<?php
declare(strict_types=1);

/**
 * File: modules/transfers/stock/controllers/receive.php
 * Purpose: Render the stock transfer receive workflow with partial delivery support.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: cis core (csrf), PDO access via router context, transfer tables
 */

require_once CISV2_ROOT . '/core/csrf.php';

/** @var array $ctx from router */
$pdo    = $ctx['pdo'];
$params = $ctx['params'];

$transferId = isset($params['transfer']) ? (int)$params['transfer'] : 0;
if ($transferId <= 0 && !empty($params['transfer_id'])) {
    $transferId = (int)$params['transfer_id'];
}

$meta = [
    'title'      => 'Receive Transfer',
    'breadcrumb' => [
        ['label' => 'Transfers', 'href' => '/cisv2/router.php?module=transfers/stock'],
        ['label' => 'Receive'],
    ],
];

if ($transferId <= 0) {
    $content = '<div class="alert alert-warning">Missing transfer identifier.</div>';
    return;
}

$transferStmt = $pdo->prepare(
    'SELECT id, public_id, vend_number, status, type, outlet_from, outlet_to, created_at, updated_at, created_by
     FROM transfers
     WHERE id = :id
     LIMIT 1'
);
$transferStmt->execute([':id' => $transferId]);
$transferRow = $transferStmt->fetch(PDO::FETCH_ASSOC) ?: null;

if (!$transferRow) {
    $content = '<div class="alert alert-warning">Transfer not found.</div>';
    return;
}

$transfer = [
    'id'               => (int)$transferRow['id'],
    'public_code'      => (string)$transferRow['public_id'],
    'vend_number'      => $transferRow['vend_number'],
    'status'           => (string)$transferRow['status'],
    'type'             => (string)$transferRow['type'],
    'origin_outlet_id' => (string)$transferRow['outlet_from'],
    'dest_outlet_id'   => (string)$transferRow['outlet_to'],
    'created_at'       => (string)$transferRow['created_at'],
    'updated_at'       => (string)$transferRow['updated_at'],
    'created_by'       => (int)$transferRow['created_by'],
];

$displayCode = $transfer['public_code']
    ?: ($transfer['vend_number'] ?: 'Transfer #' . $transferId);

$meta['title'] = 'Receive ' . $displayCode;
$meta['breadcrumb'] = [
    ['label' => 'Transfers', 'href' => '/cisv2/router.php?module=transfers/stock'],
    ['label' => 'Stock', 'href' => '/cisv2/router.php?module=transfers/stock'],
    ['label' => $displayCode, 'href' => '/cisv2/router.php?module=transfers/stock&view=pack&transfer=' . $transferId],
    ['label' => 'Receive'],
];

$outletStmt = $pdo->prepare(
    'SELECT id, name
       FROM vend_outlets
      WHERE id IN (:from, :to)'
);
$outletStmt->execute([
    ':from' => $transfer['origin_outlet_id'],
    ':to'   => $transfer['dest_outlet_id'],
]);
foreach ($outletStmt->fetchAll(PDO::FETCH_ASSOC) as $outlet) {
    if ($outlet['id'] === $transfer['origin_outlet_id']) {
        $transfer['origin_outlet_name'] = $outlet['name'];
    }
    if ($outlet['id'] === $transfer['dest_outlet_id']) {
        $transfer['dest_outlet_name'] = $outlet['name'];
    }
}
$transfer['origin_outlet_name'] = $transfer['origin_outlet_name'] ?? $transfer['origin_outlet_id'];
$transfer['dest_outlet_name']   = $transfer['dest_outlet_name'] ?? $transfer['dest_outlet_id'];

$itemStmt = $pdo->prepare(
    'SELECT ti.id, ti.product_id, ti.qty_requested, ti.qty_sent_total, ti.qty_received_total,
            COALESCE(vp.sku, "") AS sku,
            COALESCE(vp.name, "") AS name
       FROM transfer_items ti
  LEFT JOIN vend_products vp ON vp.id = ti.product_id
      WHERE ti.transfer_id = :tid
   ORDER BY vp.name ASC, ti.id ASC'
);
$itemStmt->execute([':tid' => $transferId]);
$itemRows = $itemStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$items = [];
$totalExpectedUnits = 0;
$totalReceivedUnits = 0;
$totalOutstandingUnits = 0;

foreach ($itemRows as $row) {
    $requested      = (int)($row['qty_requested'] ?? 0);
    $sent           = (int)($row['qty_sent_total'] ?? 0);
    $received       = (int)($row['qty_received_total'] ?? 0);
    $expected       = $sent > 0 ? $sent : $requested;
    $outstanding    = max(0, $expected - $received);
    $completionPct  = $expected > 0 ? round(($received / $expected) * 100, 2) : 0.0;

    $items[] = [
        'id'                 => (int)$row['id'],
        'product_id'         => (string)$row['product_id'],
        'sku'                => (string)$row['sku'],
        'name'               => (string)$row['name'],
        'qty_requested'      => $requested,
        'qty_sent_total'     => $sent,
        'qty_received_total' => $received,
        'expected_qty'       => $expected,
        'outstanding_qty'    => $outstanding,
        'completion_pct'     => $completionPct,
    ];

    $totalExpectedUnits   += $expected;
    $totalReceivedUnits   += $received;
    $totalOutstandingUnits += $outstanding;
}

$shipmentStmt = $pdo->prepare(
    'SELECT id, delivery_mode, status, packed_at, received_at, carrier_name, tracking_number, tracking_url
       FROM transfer_shipments
      WHERE transfer_id = :tid
   ORDER BY id ASC'
);
$shipmentStmt->execute([':tid' => $transferId]);
$shipmentRows = $shipmentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$parcelStmt = $pdo->prepare(
    'SELECT tp.id, tp.box_number, tp.status, tp.tracking_number, tp.courier, tp.weight_kg,
            tp.received_at, tp.notes, tp.parcel_number,
            COALESCE(SUM(tpi.qty), 0) AS items_declared,
            COALESCE(SUM(tpi.qty_received), 0) AS items_received
       FROM transfer_parcels tp
  LEFT JOIN transfer_parcel_items tpi ON tpi.parcel_id = tp.id
      WHERE tp.shipment_id = :sid
   GROUP BY tp.id, tp.box_number, tp.status, tp.tracking_number, tp.courier, tp.weight_kg, tp.received_at, tp.notes, tp.parcel_number
   ORDER BY tp.box_number ASC'
);

$shipments = [];
$totalParcels = 0;
$parcelStatusTally = [
    'received'    => 0,
    'in_transit'  => 0,
    'missing'     => 0,
    'damaged'     => 0,
    'cancelled'   => 0,
];

foreach ($shipmentRows as $row) {
    $parcelStmt->execute([':sid' => (int)$row['id']]);
    $parcelRows = $parcelStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $parcels = [];
    foreach ($parcelRows as $parcel) {
        $statusKey = (string)$parcel['status'];
        if (array_key_exists($statusKey, $parcelStatusTally)) {
            $parcelStatusTally[$statusKey]++;
        }
        $totalParcels++;

        $parcels[] = [
            'id'              => (int)$parcel['id'],
            'box_number'      => (int)$parcel['box_number'],
            'status'          => (string)$parcel['status'],
            'tracking_number' => $parcel['tracking_number'],
            'courier'         => $parcel['courier'],
            'weight_kg'       => $parcel['weight_kg'] !== null ? (float)$parcel['weight_kg'] : null,
            'received_at'     => $parcel['received_at'],
            'notes'           => $parcel['notes'],
            'parcel_number'   => (int)$parcel['parcel_number'],
            'items_declared'  => (int)$parcel['items_declared'],
            'items_received'  => (int)$parcel['items_received'],
        ];
    }

    $shipments[] = [
        'id'              => (int)$row['id'],
        'delivery_mode'   => (string)$row['delivery_mode'],
        'status'          => (string)$row['status'],
        'packed_at'       => $row['packed_at'],
        'received_at'     => $row['received_at'],
        'carrier_name'    => $row['carrier_name'],
        'tracking_number' => $row['tracking_number'],
        'tracking_url'    => $row['tracking_url'],
        'parcels'         => $parcels,
    ];
}

$openDiscrepancyStmt = $pdo->prepare(
    'SELECT COUNT(*) AS cnt
       FROM transfer_discrepancies
      WHERE transfer_id = :tid
        AND status = "open"'
);
$openDiscrepancyStmt->execute([':tid' => $transferId]);
$openDiscrepancyCount = (int)($openDiscrepancyStmt->fetchColumn() ?: 0);

$discrepancyStmt = $pdo->prepare(
    'SELECT id, product_id, type, qty_expected, qty_actual, status, notes, created_at, resolved_at
       FROM transfer_discrepancies
      WHERE transfer_id = :tid
   ORDER BY (status = "open") DESC, created_at DESC
      LIMIT 50'
);
$discrepancyStmt->execute([':tid' => $transferId]);
$discrepancies = [];
foreach ($discrepancyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $discrepancies[] = [
        'id'           => (int)$row['id'],
        'product_id'   => $row['product_id'],
        'type'         => (string)$row['type'],
        'qty_expected' => (int)$row['qty_expected'],
        'qty_actual'   => (int)$row['qty_actual'],
        'status'       => (string)$row['status'],
        'notes'        => $row['notes'],
        'created_at'   => $row['created_at'],
        'resolved_at'  => $row['resolved_at'],
    ];
}

$noteStmt = $pdo->prepare(
    'SELECT id, note_text, created_by, created_at
       FROM transfer_notes
      WHERE transfer_id = :tid
   ORDER BY id DESC
      LIMIT 10'
);
$noteStmt->execute([':tid' => $transferId]);
$notes = [];
foreach ($noteStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $notes[] = [
        'id'         => (int)$row['id'],
        'note_text'  => $row['note_text'],
        'created_by' => (int)$row['created_by'],
        'created_at' => $row['created_at'],
    ];
}

$media = [];
$productIdColumnAvailable = false;
try {
    $columnCheck = $pdo->query("SHOW COLUMNS FROM transfer_media LIKE 'product_id'");
    $productIdColumnAvailable = (bool)$columnCheck->fetch();
} catch (Throwable $e) {
    $productIdColumnAvailable = false;
}

$mediaSelectExtra = $productIdColumnAvailable ? ', tm.product_id' : ', NULL AS product_id';
try {
    $mediaStmt = $pdo->prepare(
        'SELECT tm.id, tm.kind, tm.mime_type, tm.size_bytes, tm.path, tm.parcel_id, tm.discrepancy_id, tm.note, tm.created_at' .
        $mediaSelectExtra .
        '  FROM transfer_media tm
         WHERE tm.transfer_id = :tid
      ORDER BY tm.id DESC
         LIMIT 20'
    );
    $mediaStmt->execute([':tid' => $transferId]);
    foreach ($mediaStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $media[] = [
            'id'             => (int)$row['id'],
            'kind'           => (string)$row['kind'],
            'mime_type'      => (string)$row['mime_type'],
            'size_bytes'     => (int)$row['size_bytes'],
            'path'           => (string)$row['path'],
            'parcel_id'      => $row['parcel_id'] !== null ? (int)$row['parcel_id'] : null,
            'discrepancy_id' => $row['discrepancy_id'] !== null ? (int)$row['discrepancy_id'] : null,
            'note'           => $row['note'],
            'created_at'     => $row['created_at'],
            'product_id'     => $productIdColumnAvailable ? $row['product_id'] : null,
        ];
    }
} catch (Throwable $e) {
    $media = [];
}

$progressPct = $totalExpectedUnits > 0
    ? min(100, round(($totalReceivedUnits / $totalExpectedUnits) * 100, 2))
    : 0.0;

$allItemsReceived   = ($totalOutstandingUnits === 0) && ($totalExpectedUnits > 0);
$allParcelsReceived = ($totalParcels > 0) && ($parcelStatusTally['received'] === $totalParcels);

$readyToFinalize = $allItemsReceived && $openDiscrepancyCount === 0;
$hasPartialDelivery = !$allParcelsReceived || !$allItemsReceived;

$receiveMetrics = [
    'items' => [
        'total_expected'    => $totalExpectedUnits,
        'total_received'    => $totalReceivedUnits,
        'total_outstanding' => $totalOutstandingUnits,
        'progress_pct'      => $progressPct,
    ],
    'parcels' => [
        'total'      => $totalParcels,
        'received'   => $parcelStatusTally['received'],
        'in_transit' => $parcelStatusTally['in_transit'],
        'missing'    => $parcelStatusTally['missing'],
        'damaged'    => $parcelStatusTally['damaged'],
        'cancelled'  => $parcelStatusTally['cancelled'],
    ],
    'flags' => [
        'ready_to_finalize' => $readyToFinalize,
        'has_partial'       => $hasPartialDelivery,
        'has_open_discrepancies' => $openDiscrepancyCount > 0,
        'requires_parcel_declaration' => ($totalParcels === 0 && $totalExpectedUnits > 0),
    ],
];

$requestId = null;
try {
    $requestId = bin2hex(random_bytes(8));
} catch (Throwable $e) {
    $requestId = bin2hex((string)uniqid('', true));
}

$receiveConfig = [
    'transferId'            => $transferId,
    'transferCode'          => $displayCode,
    'transferStatus'        => $transfer['status'],
    'origin'                => [
        'id'   => $transfer['origin_outlet_id'],
        'name' => $transfer['origin_outlet_name'],
    ],
    'destination'           => [
        'id'   => $transfer['dest_outlet_id'],
        'name' => $transfer['dest_outlet_name'],
    ],
    'metrics'               => $receiveMetrics,
    'discrepancy_open_count'=> $openDiscrepancyCount,
    'endpoints'             => [
        'set_qty'           => '/cisv2/modules/transfers/stock/ajax/actions/receive_set_qty.php',
        'parcel_action'     => '/cisv2/modules/transfers/stock/ajax/actions/parcel_receive_action.php',
        'add_discrepancy'   => '/cisv2/modules/transfers/stock/ajax/actions/discrepancy_add.php',
        'finalize'          => '/cisv2/modules/transfers/stock/ajax/actions/finalize_receive_sync.php',
        'create_upload_token' => '/cisv2/api/uploads/create_token.php',
        'media_ingest'      => '/cisv2/api/uploads/ingest.php',
        'media_qr'          => '/cisv2/api/uploads/qr.php',
    ],
    'csrf'                  => cis_csrf_token(),
    'request_id'            => $requestId,
    'ready_to_finalize'     => $readyToFinalize,
    'redirect_on_complete'  => '/cisv2/router.php?module=transfers/stock',
    'vend_release_mode'     => 'immediate',
    'timestamp'             => gmdate('c'),
];

$receiveTransfer      = $transfer;
$receiveItems         = $items;
$receiveShipments     = $shipments;
$receiveDiscrepancies = $discrepancies;
$receiveMedia         = $media;
$receiveNotes         = $notes;
$receiveConfigVar     = $receiveConfig;
$receiveMetricsVar    = $receiveMetrics;

$viewFile = __DIR__ . '/../views/receive.php';
ob_start();
require $viewFile;
$content = (string)ob_get_clean();
