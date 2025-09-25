<?php declare(strict_types=1);
/**
 * File: modules/transfers/stock/ajax/actions/finalize_receive_sync.php
 * Purpose: Finalize a transfer receiving session, supporting partial deliveries.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: transfer_items, transfer_parcels, transfer_shipments, transfer_discrepancies
 */

require_once dirname(__DIR__, 6) . '/bootstrap.php';
header('Content-Type: application/json');

try {
    cis_require_login();

    $payload = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
    $transferId = (int)($payload['transfer_id'] ?? 0);
    if ($transferId <= 0) {
        throw new InvalidArgumentException('Invalid transfer reference');
    }

    $pdo = db_rw();
    $pdo->beginTransaction();

    $openDiscrepancies = (int)$pdo->query('SELECT COUNT(*) FROM transfer_discrepancies WHERE transfer_id = ' . $transferId . " AND status = 'open'")->fetchColumn();
    if ($openDiscrepancies > 0) {
        throw new RuntimeException('Resolve open discrepancies before finalizing');
    }

    // Clamp any over-received values back to sent totals
    $pdo->exec('UPDATE transfer_items SET qty_received_total = LEAST(qty_received_total, qty_sent_total), updated_at = NOW() WHERE transfer_id = ' . $transferId);

    $lineOutstanding = (int)$pdo->query('SELECT COALESCE(SUM(qty_sent_total - qty_received_total), 0) FROM transfer_items WHERE transfer_id = ' . $transferId)->fetchColumn();
    $allLinesReceived = ($lineOutstanding === 0);

    $parcelStatsStmt = $pdo->prepare(
        "SELECT
            SUM(status = 'received') AS received,
            SUM(status = 'missing')  AS missing,
            SUM(status = 'damaged')  AS damaged,
            SUM(status = 'in_transit') AS in_transit,
            SUM(status = 'cancelled') AS cancelled,
            COUNT(*) AS total
         FROM transfer_parcels p
         JOIN transfer_shipments s ON s.id = p.shipment_id
        WHERE s.transfer_id = :tid"
    );
    $parcelStatsStmt->execute([':tid' => $transferId]);
    $parcelStatsRow = $parcelStatsStmt->fetch() ?: ['received' => 0, 'missing' => 0, 'damaged' => 0, 'in_transit' => 0, 'cancelled' => 0, 'total' => 0];

    $totalParcels   = (int)($parcelStatsRow['total'] ?? 0);
    $receivedParcels = (int)($parcelStatsRow['received'] ?? 0);
    $missingParcels  = (int)($parcelStatsRow['missing'] ?? 0);
    $damagedParcels  = (int)($parcelStatsRow['damaged'] ?? 0);

    $allParcelsReceived = ($totalParcels === 0) ? $allLinesReceived : ($totalParcels === $receivedParcels);
    $isComplete = $allLinesReceived && $allParcelsReceived;

    if ($isComplete) {
        $pdo->prepare("UPDATE transfer_shipments SET status = 'received', received_at = NOW(), received_by = :uid WHERE transfer_id = :tid")
            ->execute([':uid' => (int)($_SESSION['user_id'] ?? $_SESSION['userID'] ?? 0), ':tid' => $transferId]);
        $pdo->prepare("UPDATE transfers SET state = 'RECEIVED', status = 'received', updated_at = NOW() WHERE id = :tid")
            ->execute([':tid' => $transferId]);
    } else {
        $pdo->prepare("UPDATE transfer_shipments SET status = 'partial', received_at = NOW(), received_by = :uid WHERE transfer_id = :tid AND status <> 'received'")
            ->execute([':uid' => (int)($_SESSION['user_id'] ?? $_SESSION['userID'] ?? 0), ':tid' => $transferId]);
        $pdo->prepare("UPDATE transfers SET state = 'RECEIVING', status = 'partial', updated_at = NOW() WHERE id = :tid")
            ->execute([':tid' => $transferId]);
    }

    $pdo->commit();

    cis_log('INFO', 'transfers', 'receive.finalized', [
        'transfer_id'      => $transferId,
        'complete'         => $isComplete,
        'lines_outstanding'=> $lineOutstanding,
        'parcels_total'    => $totalParcels,
        'parcels_received' => $receivedParcels,
        'parcels_missing'  => $missingParcels,
        'parcels_damaged'  => $damagedParcels,
    ]);

    echo json_encode([
        'ok'               => true,
        'complete'         => $isComplete,
        'lines_outstanding'=> $lineOutstanding,
        'parcels'          => [
            'total'    => $totalParcels,
            'received' => $receivedParcels,
            'missing'  => $missingParcels,
            'damaged'  => $damagedParcels,
        ],
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
}
