<?php declare(strict_types=1);
/**
 * File: modules/transfers/stock/ajax/actions/parcel_receive_action.php
 * Purpose: Parcel-level quick actions for receiving workflow, including declaration and status updates.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: transfer_parcels, transfer_shipments
 */

require_once dirname(__DIR__, 6) . '/bootstrap.php';
header('Content-Type: application/json');

try {
    cis_require_login();

    $input = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
    $action = (string)($input['action'] ?? '');
    $transferId = (int)($input['transfer_id'] ?? 0);

    if ($transferId <= 0) {
        throw new InvalidArgumentException('Invalid transfer reference');
    }

    $pdo = db_rw();
    $pdo->beginTransaction();

    if ($action === 'declare') {
        $boxNumber = (int)($input['box_number'] ?? 0);
        if ($boxNumber <= 0) {
            throw new InvalidArgumentException('Box number required');
        }

        $status = (string)($input['status'] ?? 'received');
        $allowedStatuses = ['received', 'in_transit', 'missing', 'damaged'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'in_transit';
        }

        $weightKg = isset($input['weight_kg']) ? max(0.0, (float)$input['weight_kg']) : null;
        $notes    = isset($input['notes']) ? trim((string)$input['notes']) : null;

        $shipmentIdStmt = $pdo->prepare('SELECT id FROM transfer_shipments WHERE transfer_id = :tid ORDER BY id DESC LIMIT 1');
        $shipmentIdStmt->execute([':tid' => $transferId]);
        $shipmentId = (int)($shipmentIdStmt->fetchColumn() ?: 0);
        if ($shipmentId <= 0) {
            $createShipment = $pdo->prepare('INSERT INTO transfer_shipments (transfer_id, delivery_mode, status, created_at) VALUES (:tid, "courier", "partial", NOW())');
            $createShipment->execute([':tid' => $transferId]);
            $shipmentId = (int)$pdo->lastInsertId();
        }

        $insert = $pdo->prepare(
            'INSERT INTO transfer_parcels (shipment_id, box_number, parcel_number, weight_kg, status, notes, created_at, updated_at)
             VALUES (:sid, :box, :parcel, :w, :status, :notes, NOW(), NOW())
             ON DUPLICATE KEY UPDATE weight_kg = VALUES(weight_kg), status = VALUES(status), notes = VALUES(notes), updated_at = NOW()'
        );
        $insert->execute([
            ':sid'    => $shipmentId,
            ':box'    => $boxNumber,
            ':parcel' => $boxNumber,
            ':w'      => $weightKg,
            ':status' => $status,
            ':notes'  => $notes,
        ]);

        $parcelIdStmt = $pdo->prepare('SELECT id FROM transfer_parcels WHERE shipment_id = :sid AND box_number = :box LIMIT 1');
        $parcelIdStmt->execute([':sid' => $shipmentId, ':box' => $boxNumber]);
        $parcelId = (int)($parcelIdStmt->fetchColumn() ?: 0);

        $pdo->commit();
        cis_log('INFO', 'transfers', 'parcel.declare', [
            'transfer_id' => $transferId,
            'parcel_id'   => $parcelId,
            'box_number'  => $boxNumber,
            'status'      => $status,
        ]);

        echo json_encode(['ok' => true, 'parcel_id' => $parcelId], JSON_UNESCAPED_SLASHES);
        return;
    }

    $parcelId = (int)($input['parcel_id'] ?? 0);
    if ($parcelId <= 0) {
        throw new InvalidArgumentException('Parcel identifier required');
    }

    $parcelStmt = $pdo->prepare(
        'SELECT p.id, p.status, p.shipment_id, s.transfer_id
           FROM transfer_parcels p
           JOIN transfer_shipments s ON s.id = p.shipment_id
          WHERE p.id = :pid
          FOR UPDATE'
    );
    $parcelStmt->execute([':pid' => $parcelId]);
    $parcelRow = $parcelStmt->fetch();
    if (!$parcelRow || (int)$parcelRow['transfer_id'] !== $transferId) {
        throw new RuntimeException('Parcel not associated with transfer');
    }

    $newStatus = match ($action) {
        'mark_received' => 'received',
        'mark_missing'  => 'missing',
        'mark_damaged'  => 'damaged',
        default         => throw new InvalidArgumentException('Unknown parcel action'),
    };

    $pdo->prepare('UPDATE transfer_parcels SET status = :status, updated_at = NOW(), received_at = CASE WHEN :status = "received" THEN NOW() ELSE received_at END WHERE id = :pid')
        ->execute([':status' => $newStatus, ':pid' => $parcelId]);

    $eventCode = $newStatus === 'received' ? 'DELIVERED' : 'EXCEPTION';
    $eventText = match ($newStatus) {
        'received' => 'Parcel received',
        'missing'  => 'Parcel marked missing',
        'damaged'  => 'Parcel marked damaged',
        default    => 'Status change',
    };

    $pdo->prepare(
        'INSERT INTO transfer_tracking_events (transfer_id, parcel_id, tracking_number, carrier, event_code, event_text, occurred_at)
         SELECT :tid, :pid, tracking_number, courier, :code, :text, NOW()
           FROM transfer_parcels
          WHERE id = :pid'
    )->execute([
        ':tid'  => $transferId,
        ':pid'  => $parcelId,
        ':code' => $eventCode,
        ':text' => $eventText,
    ]);

    $pdo->commit();

    cis_log('INFO', 'transfers', 'parcel.action', [
        'transfer_id' => $transferId,
        'parcel_id'   => $parcelId,
        'status'      => $newStatus,
    ]);

    echo json_encode(['ok' => true, 'status' => $newStatus], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
}
