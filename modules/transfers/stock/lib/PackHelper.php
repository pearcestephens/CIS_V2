<?php
declare(strict_types=1);

namespace CIS\Transfers\Stock;

use PDO;

class PackHelper
{
    private function getActorId(): ?int
    {
        $session = $_SESSION ?? [];
        $keys    = ['userID', 'user_id', 'staff_id'];
        foreach ($keys as $key) {
            if (!empty($session[$key])) {
                return (int) $session[$key];
            }
        }
        return null;
    }

    public function calculateShipUnits(string $productId, int $qty): array
    {
        $unitG     = $this->resolveUnitWeightG($productId);
        $shipUnits = max(1, (int)$qty);
        return ['ship_units' => $shipUnits, 'unit_g' => $unitG, 'weight_g' => $shipUnits * $unitG];
    }

    public function resolveUnitWeightG(string $productId): int
    {
        $pdo = db();
        $q   = $pdo->prepare("SELECT IFNULL(ROUND(vp.weight_grams),0) AS wg FROM vend_products vp WHERE vp.id=:pid LIMIT 1");
        $q->execute([':pid' => $productId]);
        $wg = (int)($q->fetchColumn() ?: 0);
        if ($wg > 0) return $wg;

        try {
            $q = $pdo->prepare("
                SELECT IFNULL(ROUND(cw.default_weight_g),0) AS wg
                FROM product_classification_unified pcu
                JOIN category_weights cw ON cw.category_id = pcu.category_id
                WHERE pcu.product_id = :pid
                LIMIT 1
            ");
            $q->execute([':pid' => $productId]);
            $wg = (int)($q->fetchColumn() ?: 0);
            if ($wg > 0) return $wg;
        } catch (\Throwable $e) {
        }

        return 100; // safe default
    }

    public function validateParcelPlan(int $transferId, array $plan): array
    {
        $map        = $this->loadTransferItemMap($transferId);
        $attachable = [];
        $unknown    = [];

        $parcels = (array)($plan['parcels'] ?? []);
        foreach ($parcels as $pi => $parcel) {
            $items = (array)($parcel['items'] ?? []);
            foreach ($items as $line) {
                $qty = (int)($line['qty'] ?? 0);
                $iid = isset($line['item_id']) ? (int)$line['item_id'] : null;
                $pid = isset($line['product_id']) ? (string)$line['product_id'] : null;

                $resolved = $this->resolveTransferItemId($transferId, $iid, $pid, $map);
                if ($resolved) {
                    $attachable[] = ['parcel_index' => $pi, 'item_id' => $resolved, 'qty' => $qty];
                } else {
                    $unknown[] = ['parcel_index' => $pi, 'item_id' => $iid, 'product_id' => $pid, 'qty' => $qty];
                }
            }
        }

        return [
            'attachable' => $attachable,
            'unknown'    => $unknown,
            'notes'      => [
                'parcels'       => count($parcels),
                'lines_ok'      => count($attachable),
                'lines_unknown' => count($unknown),
            ],
        ];
    }

    public function setParcelTracking(
        int $transferId,
        ?int $parcelId,
        ?int $boxNumber,
        string $carrierName,
        ?string $trackingNumber,
        ?string $trackingUrl
    ): array {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $normalizedCarrier = trim($carrierName) !== '' ? strtoupper($carrierName) : 'INTERNAL';
            $deliveryMode      = ($normalizedCarrier === 'INTERNAL') ? 'internal_drive' : 'courier';

            $shipmentId = null;
            if ($parcelId) {
                $existing = $pdo->prepare("SELECT shipment_id FROM transfer_parcels WHERE id = :id LIMIT 1");
                $existing->execute([':id' => $parcelId]);
                $shipmentId = (int) ($existing->fetchColumn() ?: 0);
            }

            if (!$shipmentId) {
                $stmt = $pdo->prepare("SELECT id FROM transfer_shipments WHERE transfer_id = :t ORDER BY id DESC LIMIT 1");
                $stmt->execute([':t' => $transferId]);
                $shipmentId = (int) ($stmt->fetchColumn() ?: 0);
            }

            if (!$shipmentId) {
                $shipmentId = $this->createShipment($transferId, $normalizedCarrier, $deliveryMode, 'packed');
            } else {
                $pdo->prepare("
                    UPDATE transfer_shipments
                       SET carrier_name   = :carrier,
                           delivery_mode  = :mode,
                           tracking_number= :tracking,
                           tracking_url   = :url,
                           status         = 'in_transit',
                           updated_at     = NOW()
                     WHERE id = :id LIMIT 1
                ")->execute([
                    ':carrier'  => $normalizedCarrier,
                    ':mode'     => $deliveryMode,
                    ':tracking' => $trackingNumber,
                    ':url'      => $trackingUrl,
                    ':id'       => $shipmentId,
                ]);
            }

            if (!$parcelId) {
                $boxNumber = ($boxNumber && $boxNumber > 0) ? $boxNumber : 1;
                $parcelId  = $this->addParcel($shipmentId, $boxNumber, null, $normalizedCarrier, $trackingNumber);
            }

            $pdo->prepare("
                UPDATE transfer_parcels
                   SET courier        = :courier,
                       tracking_number= :tracking,
                       status         = 'in_transit',
                       updated_at     = NOW()
                 WHERE id = :id LIMIT 1
            ")->execute([
                ':courier'  => $normalizedCarrier,
                ':tracking' => $trackingNumber,
                ':id'       => $parcelId,
            ]);

            $this->audit($transferId, 'tracking.set', [
                'parcel_id'    => $parcelId,
                'carrier'      => $normalizedCarrier,
                'tracking'     => $trackingNumber,
                'tracking_url' => $trackingUrl,
            ]);
            $this->log($transferId, 'tracking.set', [
                'parcel_id' => $parcelId,
                'carrier'   => $normalizedCarrier,
                'tracking'  => $trackingNumber,
            ]);

            $pdo->commit();
            return ['ok' => true, 'parcel_id' => (int) $parcelId];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => 'tracking_persist_failed'];
        }
    }

    public function setShipmentMode(int $transferId, string $mode, ?string $status = null): array
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $normalizedMode = in_array($mode, ['courier', 'internal_drive', 'pickup'], true) ? $mode : 'courier';
            $shipmentStmt   = $pdo->prepare("SELECT id FROM transfer_shipments WHERE transfer_id = :t ORDER BY id DESC LIMIT 1");
            $shipmentStmt->execute([':t' => $transferId]);
            $shipmentId = (int) ($shipmentStmt->fetchColumn() ?: 0);

            if (!$shipmentId) {
                $shipmentId = $this->createShipment($transferId, strtoupper($normalizedMode), $normalizedMode, $status ?? 'packed');
            } else {
                $sql = "UPDATE transfer_shipments SET delivery_mode = :mode";
                $params = [':mode' => $normalizedMode, ':id' => $shipmentId];
                if ($status) {
                    $sql .= ", status = :status";
                    $params[':status'] = $status;
                }
                $sql .= " WHERE id = :id LIMIT 1";
                $pdo->prepare($sql)->execute($params);
            }

            $this->audit($transferId, 'shipment.mode', [
                'shipment_id' => $shipmentId,
                'mode'        => $normalizedMode,
                'status'      => $status,
            ]);

            $pdo->commit();
            return ['ok' => true, 'shipment_id' => $shipmentId];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => 'set_mode_failed'];
        }
    }

    public function autoAttachIfEmpty(int $transferId, array $plan): array
    {
        $parcels = (array)($plan['parcels'] ?? []);
        if (!$parcels) $parcels = [['weight_g' => 0, 'items' => []]];

        $needsAuto = false;
        foreach ($parcels as $p) {
            if (empty($p['items'])) { $needsAuto = true; break; }
        }
        if (!$needsAuto) return ['parcels' => $parcels];

        $items       = $this->listItems($transferId);
        $autoItems   = [];
        $totalWeight = 0;

        foreach ($items as $it) {
            $pid = (string)$it['product_id'];
            $qty = (int)($it['requested_qty'] ?? 0);
            if ($qty <= 0) continue;

            $calc   = $this->calculateShipUnits($pid, $qty);
            $su     = (int)$calc['ship_units'];
            $unitG  = (int)$calc['unit_g'];
            $autoItems[] = ['item_id' => (int)$it['id'], 'product_id' => $pid, 'qty' => $su];
            $totalWeight += ($su * $unitG);
        }

        $parcels[0]['items']    = $autoItems;
        $parcels[0]['weight_g'] = max(100, (int)($parcels[0]['weight_g'] ?? 0) ?: $totalWeight);

        return ['parcels' => $parcels];
    }

    public function generateLabelMvp(int $transferId, string $carrier, array $plan): array
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $deliveryMode = (strtoupper($carrier) === 'INTERNAL') ? 'internal_drive' : 'courier';
            $shipmentId   = $this->createShipment($transferId, $carrier, $deliveryMode);

            $parcelsOut = [];
            $skipped    = [];
            $map        = $this->loadTransferItemMap($transferId);
            $idx        = 0;

            foreach ((array)($plan['parcels'] ?? []) as $parcel) {
                $idx++;
                $weightG  = (int)($parcel['weight_g'] ?? 0);
                $weightKg = $weightG > 0 ? round($weightG / 1000, 3) : null;
                $notes    = isset($parcel['notes']) ? (string)$parcel['notes'] : null;
                $parcelId = $this->addParcel(
                    $shipmentId,
                    $idx,
                    $weightKg,
                    strtoupper($carrier),
                    isset($parcel['tracking_number']) ? (string)$parcel['tracking_number'] : null,
                    $notes
                );

                $items = (array)($parcel['items'] ?? []);
                $count = 0;

                foreach ($items as $line) {
                    $qty = (int)($line['qty'] ?? 0);
                    $iid = isset($line['item_id']) ? (int)$line['item_id'] : null;
                    $pid = isset($line['product_id']) ? (string)$line['product_id'] : null;

                    $resolved = $this->resolveTransferItemId($transferId, $iid, $pid, $map);
                    if (!$resolved || $qty <= 0) {
                        $skipped[] = ['box' => $idx, 'item_id' => $iid, 'product_id' => $pid, 'qty' => $qty];
                        continue;
                    }
                    $this->attachItemToParcel($parcelId, $resolved, $qty);
                    $count += $qty;
                }

                $parcelsOut[] = [
                    'id'         => $parcelId,
                    'box_number' => $idx,
                    'weight_kg'  => $weightKg,
                    'items_count'=> $count,
                ];
            }

            $this->audit($transferId, 'mvp_label_created', [
                'shipment_id' => $shipmentId,
                'carrier'     => $carrier,
                'mode'        => $deliveryMode,
                'parcels'     => count($parcelsOut),
                'skipped'     => count($skipped),
            ]);
            $this->log($transferId, 'label.created', [
                'shipment_id' => $shipmentId,
                'carrier'     => $carrier,
                'mode'        => $deliveryMode,
                'parcels'     => count($parcelsOut),
                'skipped'     => count($skipped),
            ]);

            $pdo->commit();
            return ['ok' => true, 'shipment_id' => $shipmentId, 'parcels' => $parcelsOut, 'skipped' => $skipped];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => 'Failed to generate MVP label'];
        }
    }

    public function generateLabel(int $transferId, string $carrier, array $plan): array
    {
        // Extend here for real carrier APIs; MVP path for now
        return $this->generateLabelMvp($transferId, $carrier, $plan);
    }

    public function addPackNote(int $transferId, string $notes): void
    {
        if ($notes === '') return;
        $pdo = db();
        $q   = $pdo->prepare("INSERT INTO transfer_notes(transfer_id, note, created_at) VALUES (:t, :n, NOW())");
        $q->execute([':t' => $transferId, ':n' => $notes]);
        $this->audit($transferId, 'pack_note_added', ['len' => strlen($notes)]);
    }

    public function listItems(int $transferId): array
    {
        $pdo = db();
        $q   = $pdo->prepare("
            SELECT ti.id, ti.product_id, ti.qty_requested AS requested_qty,
                   COALESCE(vp.sku,'') AS sku, COALESCE(vp.name,'') AS name
            FROM transfer_items ti
            LEFT JOIN vend_products vp ON vp.id = ti.product_id
            WHERE ti.transfer_id = :t
            ORDER BY ti.id ASC
        ");
        $q->execute([':t' => $transferId]);
        $rows = $q->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$r) {
            $pid  = (string)$r['product_id'];
            $qty  = (int)($r['requested_qty'] ?? 0);
            $calc = $this->calculateShipUnits($pid, $qty);
            $r['unit_g']               = $calc['unit_g'];
            $r['suggested_ship_units'] = $calc['ship_units'];
        }
        unset($r);

        return $rows;
    }

    public function getParcels(int $transferId): array
    {
        $pdo = db();
        $q   = $pdo->prepare("
            SELECT ts.id
              FROM transfer_shipments ts
             WHERE ts.transfer_id = :t
             ORDER BY ts.id DESC
             LIMIT 1
        ");
        $q->execute([':t' => $transferId]);
        $shipmentId = $q->fetchColumn();
        if (!$shipmentId) {
            return ['shipment_id' => null, 'parcels' => []];
        }

     $q = $pdo->prepare("
         SELECT tp.id, tp.box_number, tp.weight_kg,
             (SELECT COALESCE(SUM(tpi.qty),0)
             FROM transfer_parcel_items tpi
            WHERE tpi.parcel_id = tp.id) AS items_count
        FROM transfer_parcels tp
          WHERE tp.shipment_id = :s
          ORDER BY tp.box_number ASC
     ");
        $q->execute([':s' => (int)$shipmentId]);

        $rows = $q->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $out  = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'         => (int)$r['id'],
                'box_number' => (int)$r['box_number'],
                'weight_kg'  => isset($r['weight_kg']) ? (float)$r['weight_kg'] : null,
                'items_count'=> (int)$r['items_count'],
            ];
        }
        return ['shipment_id' => (int)$shipmentId, 'parcels' => $out];
    }

    public function createShipment(
        int $transferId,
        string $carrier,
        string $deliveryMode = 'courier',
        string $status = 'packed'
    ): int {
        $pdo      = db();
        $packedBy = $this->getActorId();
        $q        = $pdo->prepare("
            INSERT INTO transfer_shipments(
                transfer_id,
                delivery_mode,
                status,
                packed_at,
                packed_by,
                created_at,
                carrier_name,
                nicotine_in_shipment
            ) VALUES (
                :t,
                :mode,
                :status,
                NOW(),
                :packed_by,
                NOW(),
                :carrier,
                0
            )
        ");
        $q->execute([
            ':t'         => $transferId,
            ':mode'      => $deliveryMode,
            ':status'    => $status,
            ':packed_by' => $packedBy,
            ':carrier'   => $carrier,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function addParcel(
        int $shipmentId,
        int $boxNumber,
        ?float $weightKg,
        ?string $courier = null,
        ?string $trackingNumber = null,
        ?string $notes = null
    ): int {
        $pdo = db();
        $q   = $pdo->prepare("
            INSERT INTO transfer_parcels(
                shipment_id,
                box_number,
                parcel_number,
                weight_kg,
                courier,
                tracking_number,
                status,
                notes,
                created_at,
                updated_at
            ) VALUES (
                :shipment,
                :box,
                :parcel,
                :weight_kg,
                :courier,
                :tracking,
                'in_transit',
                :notes,
                NOW(),
                NOW()
            )
        ");
        $q->execute([
            ':shipment' => $shipmentId,
            ':box'      => $boxNumber,
            ':parcel'   => $boxNumber,
            ':weight_kg'=> $weightKg,
            ':courier'  => $courier,
            ':tracking' => $trackingNumber,
            ':notes'    => $notes,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function attachItemToParcel(int $parcelId, int $transferItemId, int $qty): void
    {
        if ($qty <= 0) {
            return;
        }
        $pdo = db();
        $q   = $pdo->prepare("
            INSERT INTO transfer_parcel_items(parcel_id, item_id, qty, qty_received)
            VALUES (:parcel, :item, :qty, 0)
            ON DUPLICATE KEY UPDATE qty = VALUES(qty)
        ");
        $q->execute([
            ':parcel' => $parcelId,
            ':item'   => $transferItemId,
            ':qty'    => $qty,
        ]);
    }

    public function resolveTransferItemId(int $transferId, ?int $itemId, ?string $productId, ?array $preMap = null): ?int
    {
        $map = $preMap ?? $this->loadTransferItemMap($transferId);
        if ($itemId && isset($map['by_item'][$itemId])) {
            return $itemId;
        }
        if ($productId && isset($map['by_product'][$productId])) {
            return (int) $map['by_product'][$productId];
        }
        return null;
    }

    public function loadTransferItemMap(int $transferId): array
    {
        $pdo = db();
        $q   = $pdo->prepare("SELECT id, product_id FROM transfer_items WHERE transfer_id=:t");
        $q->execute([':t' => $transferId]);
        $byItem = $byProduct = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $id  = (int) $r['id'];
            $pid = (string) $r['product_id'];
            $byItem[$id]      = $pid;
            $byProduct[$pid]  = $id;
        }
        return ['by_item' => $byItem, 'by_product' => $byProduct];
    }

    public function audit(int $transferId, string $event, array $meta = []): void
    {
        $pdo = db();
        try {
            $q = $pdo->prepare("
                INSERT INTO transfer_audit_log(transfer_id, event, meta_json, created_at)
                VALUES (:t, :e, :m, NOW())
            ");
            $q->execute([':t' => $transferId, ':e' => $event, ':m' => json_encode($meta, JSON_UNESCAPED_SLASHES)]);
        } catch (\Throwable $e) {
        }
    }

    public function log(int $transferId, string $eventType, array $payload = []): void
    {
        $pdo = db();
        try {
            $q = $pdo->prepare("
                INSERT INTO transfer_logs(transfer_id, event_type, event_data, created_at)
                VALUES (:t, :event, :data, NOW())
            ");
            $q->execute([
                ':t'     => $transferId,
                ':event' => $eventType,
                ':data'  => json_encode($payload, JSON_UNESCAPED_SLASHES),
            ]);
        } catch (\Throwable $e) {
        }
    }
}
