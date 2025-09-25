<?php
declare(strict_types=1);

namespace CIS\Transfers\Receive;

/**
 * ReceiveHelper — Transfer receiving logic
 * - Shipment summary (parcels + items)
 * - Scan/select lines
 * - Save receipt (writes transfer_receipts + transfer_receipt_items)
 * - Create discrepancies (short/over)
 * - Resolve discrepancies
 */
final class ReceiveHelper
{
    public function getShipmentSummary(int $transferId): array
    {
        $pdo = db();

        // Latest shipment id (nullable)
        $sidStmt = $pdo->prepare("
            SELECT id FROM transfer_shipments
            WHERE transfer_id=:t
            ORDER BY id DESC
            LIMIT 1
        ");
        $sidStmt->execute([':t' => $transferId]);
        $sid = (int)($sidStmt->fetchColumn() ?: 0);

        $parcels = [];
        if ($sid) {
            $p = $pdo->prepare("SELECT id, box_number, weight_kg FROM transfer_parcels WHERE shipment_id=:s ORDER BY box_number ASC");
            $p->execute([':s' => $sid]);
            $parcels = $p->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        // Items + totals received so far
        $it = $pdo->prepare("
            SELECT
                ti.id,
                ti.product_id,
                ti.request_qty AS expected,
                COALESCE(vp.sku, '')  AS sku,
                COALESCE(vp.name, '') AS name,
                COALESCE(SUM(tri.qty_received), 0) AS received
            FROM transfer_items ti
            LEFT JOIN vend_products vp ON vp.id = ti.product_id
            LEFT JOIN transfer_receipt_items tri ON tri.transfer_item_id = ti.id
            LEFT JOIN transfer_receipts tr       ON tr.id = tri.receipt_id AND tr.transfer_id = ti.transfer_id
            WHERE ti.transfer_id = :t
            GROUP BY ti.id, ti.product_id, ti.request_qty, vp.sku, vp.name
            ORDER BY ti.id ASC
        ");
        $it->execute([':t' => $transferId]);
        $items = $it->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return [
            'shipment_id' => $sid ?: null,
            'parcels' => array_map(static function ($r) {
                return [
                    'id'         => (int)$r['id'],
                    'box_number' => (int)$r['box_number'],
                    'weight_kg'  => isset($r['weight_kg']) ? (float)$r['weight_kg'] : null,
                ];
            }, $parcels),
            'items' => array_map(static function ($r) {
                return [
                    'id'        => (int)$r['id'],
                    'product_id'=> (int)$r['product_id'],
                    'sku'       => (string)$r['sku'],
                    'name'      => (string)$r['name'],
                    'expected'  => (int)$r['expected'],
                    'received'  => (int)$r['received'],
                ];
            }, $items),
        ];
    }

    public function scanOrSelect(int $transferId, string $type, string $value, int $qty): array
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            if ($type === 'tracking') {
                // Tracking scans could be wired later; no-op for now
                $pdo->commit();
                return ['ok' => true, 'updated' => 0];
            }

            if ($type !== 'item') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'invalid_type'];
            }

            // Find a transfer item by SKU or barcode
            $sel = $pdo->prepare("
                SELECT ti.id
                FROM transfer_items ti
                JOIN vend_products vp ON vp.id = ti.product_id
                WHERE ti.transfer_id=:t AND (vp.sku=:v OR vp.barcode=:v)
                LIMIT 1
            ");
            $sel->execute([':t' => $transferId, ':v' => $value]);
            $itemId = (int)($sel->fetchColumn() ?: 0);

            if (!$itemId) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'item_not_found'];
            }

            $actorId = (int)($_SESSION['userID'] ?? ($_SESSION['user_id'] ?? 0));

            // Create a receipt header
            $h = $pdo->prepare("INSERT INTO transfer_receipts(transfer_id, received_by, received_at, created_at) VALUES(:t, :u, NOW(), NOW())");
            $h->execute([':t' => $transferId, ':u' => $actorId]);
            $rid = (int)$pdo->lastInsertId();

            // Upsert the receipt line
            $l = $pdo->prepare("
                INSERT INTO transfer_receipt_items(receipt_id, transfer_item_id, qty_received)
                VALUES(:r, :i, :q)
                ON DUPLICATE KEY UPDATE qty_received = qty_received + VALUES(qty_received)
            ");
            $l->execute([':r' => $rid, ':i' => $itemId, ':q' => max(0, $qty)]);

            $pdo->commit();
            return ['ok' => true, 'receipt_id' => $rid, 'item_id' => $itemId, 'delta' => max(0, $qty)];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'receive_failed'];
        }
    }

    public function saveReceipt(int $transferId, array $items): array
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $actorId = (int)($_SESSION['userID'] ?? ($_SESSION['user_id'] ?? 0));

            $h = $pdo->prepare("INSERT INTO transfer_receipts(transfer_id, received_by, received_at, created_at) VALUES(:t, :u, NOW(), NOW())");
            $h->execute([':t' => $transferId, ':u' => $actorId]);
            $rid = (int)$pdo->lastInsertId();

            $l = $pdo->prepare("
                INSERT INTO transfer_receipt_items(receipt_id, transfer_item_id, qty_received, `condition`, notes)
                VALUES(:r, :i, :q, :c, :n)
                ON DUPLICATE KEY UPDATE qty_received = VALUES(qty_received), `condition`=VALUES(`condition`), notes=VALUES(notes)
            ");

            foreach ($items as $line) {
                $ti = (int)($line['transfer_item_id'] ?? 0);
                $qr = (int)($line['qty_received'] ?? 0);
                $co = (string)($line['condition'] ?? 'ok');
                $nt = (string)($line['notes'] ?? '');

                if ($ti <= 0) continue;

                $l->execute([':r' => $rid, ':i' => $ti, ':q' => max(0, $qr), ':c' => $co, ':n' => $nt]);

                // Discrepancy (short/over)
                $exp = $pdo->prepare('SELECT request_qty FROM transfer_items WHERE id=:i AND transfer_id=:t');
                $exp->execute([':i' => $ti, ':t' => $transferId]);
                $expected = (int)($exp->fetchColumn() ?: 0);

                if ($expected !== $qr) {
                    $type = ($qr < $expected) ? 'short' : 'over';
                    $ins  = $pdo->prepare('
                        INSERT INTO transfer_discrepancies(transfer_id, transfer_item_id, type, qty_expected, qty_actual, status, notes, created_at)
                        VALUES(:t, :i, :y, :e, :a, "open", :n, NOW())
                    ');
                    $ins->execute([':t' => $transferId, ':i' => $ti, ':y' => $type, ':e' => $expected, ':a' => $qr, ':n' => $nt]);
                }
            }

            $pdo->commit();
            return ['ok' => true, 'receipt_id' => $rid];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => 'save_receipt_failed'];
        }
    }

    public function listDiscrepancies(int $transferId): array
    {
        $pdo = db();
        $q = $pdo->prepare("
            SELECT id, type, qty_expected, qty_actual, status, notes, created_at, resolved_at, resolved_by
            FROM transfer_discrepancies
            WHERE transfer_id=:t
            ORDER BY id DESC
        ");
        $q->execute([':t' => $transferId]);
        $rows = $q->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $r) {
            return [
                'id'           => (int)$r['id'],
                'type'         => (string)$r['type'],
                'qty_expected' => (int)$r['qty_expected'],
                'qty_actual'   => (int)$r['qty_actual'],
                'status'       => (string)$r['status'],
                'notes'        => $r['notes'],
                'created_at'   => $r['created_at'],
                'resolved_at'  => $r['resolved_at'],
                'resolved_by'  => $r['resolved_by'] !== null ? (int)$r['resolved_by'] : null,
            ];
        }, $rows);
    }

    public function resolveDiscrepancy(int $id, int $actorId, string $noteAppend = ''): array
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($noteAppend !== '') {
                $upd = $pdo->prepare("UPDATE transfer_discrepancies SET notes = CONCAT(COALESCE(notes,''), CASE WHEN notes IS NULL OR notes='' THEN '' ELSE '\n' END, :add) WHERE id=:id");
                $upd->execute([':add' => $noteAppend, ':id' => $id]);
            }
            $q = $pdo->prepare('UPDATE transfer_discrepancies SET status="resolved", resolved_by=:u, resolved_at=NOW() WHERE id=:id');
            $q->execute([':u' => $actorId, ':id' => $id]);
            $pdo->commit();
            return ['ok' => true];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'resolve_failed'];
        }
    }
}
