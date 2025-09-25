<?php declare(strict_types=1);
/**
 * File: modules/transfers/stock/ajax/actions/discrepancy_add.php
 * Purpose: Create a discrepancy record during receiving (missing/damaged/lost/mistake).
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: transfer_discrepancies table
 */

require_once dirname(__DIR__, 6) . '/bootstrap.php';
header('Content-Type: application/json');

try {
    cis_require_login();

    $input = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);

    $transferId = (int)($input['transfer_id'] ?? 0);
    $productId  = trim((string)($input['product_id'] ?? ''));
    $itemId     = isset($input['item_id']) ? (int)$input['item_id'] : null;
    $type       = (string)($input['type'] ?? '');
    $qty        = (int)($input['qty'] ?? 0);
    $notes      = trim((string)($input['notes'] ?? ''));

    $allowedTypes = ['missing', 'damaged', 'lost', 'mistake'];
    if ($transferId <= 0 || $productId === '' || !in_array($type, $allowedTypes, true) || $qty <= 0) {
        throw new InvalidArgumentException('Invalid discrepancy payload');
    }

    $pdo = db_rw();

    if ($itemId !== null && $itemId > 0) {
        $itemCheck = $pdo->prepare('SELECT COUNT(*) FROM transfer_items WHERE id = :id AND transfer_id = :tid');
        $itemCheck->execute([':id' => $itemId, ':tid' => $transferId]);
        if ((int)$itemCheck->fetchColumn() === 0) {
            throw new RuntimeException('Item does not belong to transfer');
        }
    }

    $insert = $pdo->prepare(
        'INSERT INTO transfer_discrepancies (transfer_id, item_id, product_id, type, qty, notes, status, created_by, created_at)
         VALUES (:tid, :item, :pid, :type, :qty, :notes, "open", :by, NOW())'
    );
    $insert->execute([
        ':tid'  => $transferId,
        ':item' => $itemId ?: null,
        ':pid'  => $productId,
        ':type' => $type,
        ':qty'  => $qty,
        ':notes'=> $notes !== '' ? $notes : null,
        ':by'   => (int)($_SESSION['user_id'] ?? $_SESSION['userID'] ?? 0),
    ]);

    cis_log('WARNING', 'transfers', 'discrepancy.add', [
        'transfer_id' => $transferId,
        'product_id'  => $productId,
        'item_id'     => $itemId,
        'type'        => $type,
        'qty'         => $qty,
    ]);

    echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
}
