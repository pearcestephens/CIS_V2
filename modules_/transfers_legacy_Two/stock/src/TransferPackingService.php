<?php declare(strict_types=1);

/**
 * File: modules/transfers/stock/src/TransferPackingService.php
 * Purpose: Encapsulate packing finalisation logic so controllers/AJAX handlers stay thin.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 */

namespace CISV2\Modules\Transfers\Stock; // Lightweight namespacing to avoid accidental clashes

use PDO;
use RuntimeException;
use InvalidArgumentException;
use Throwable;

class TransferPackingService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Finalise packing totals, update shipment + transfer states, and emit totals back to caller.
     *
     * @param int $transferId Transfer identifier.
     * @param int $userId     Acting staff member.
     * @return array{boxes:int, weight_g:int}
     */
    public function finalize(int $transferId, int $userId): array
    {
        if ($transferId <= 0) {
            throw new InvalidArgumentException('Transfer id must be positive');
        }

        $this->pdo->beginTransaction();

        try {
            $aggregateStmt = $this->pdo->prepare(
                'SELECT COALESCE(SUM(p.weight_grams), 0) AS total_weight,
                        COUNT(p.id)                       AS total_boxes
                   FROM transfer_shipments s
              LEFT JOIN transfer_parcels p ON p.shipment_id = s.id
                  WHERE s.transfer_id = :transfer_id'
            );
            $aggregateStmt->execute([':transfer_id' => $transferId]);
            $totals = $aggregateStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_weight' => 0, 'total_boxes' => 0];

            $totalWeight = (int)($totals['total_weight'] ?? 0);
            $totalBoxes  = (int)($totals['total_boxes'] ?? 0);

            $updateTransfer = $this->pdo->prepare(
                "UPDATE transfers
                    SET total_boxes = :boxes,
                        total_weight_g = :weight,
                        state = 'PACKAGED',
                        updated_at = NOW()
                  WHERE id = :transfer_id"
            );
            $updateTransfer->execute([
                ':boxes'        => $totalBoxes,
                ':weight'       => $totalWeight,
                ':transfer_id'  => $transferId,
            ]);

            $updateShipments = $this->pdo->prepare(
                "UPDATE transfer_shipments
                    SET status = 'packed',
                        packed_at = NOW(),
                        packed_by = :user_id
                  WHERE transfer_id = :transfer_id"
            );
            $updateShipments->execute([
                ':user_id'      => $userId > 0 ? $userId : null,
                ':transfer_id'  => $transferId,
            ]);

            $this->pdo->commit();

            return [
                'boxes'    => $totalBoxes,
                'weight_g' => $totalWeight,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Unable to finalise pack: ' . $e->getMessage(), 0, $e);
        }
    }
}
