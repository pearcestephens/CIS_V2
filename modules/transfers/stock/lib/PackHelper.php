<?php
declare(strict_types=1);

namespace CIS\Transfers\Stock;

class PackHelper
{
    public function calculateShipUnits(int $productId, int $qty): array
    {
        $unitG     = $this->resolveUnitWeightG($productId);
        $shipUnits = max(1, (int)$qty);
        return ['ship_units' => $shipUnits, 'unit_g' => $unitG, 'weight_g' => $shipUnits * $unitG];
    }

    public function resolveUnitWeightG(int $productId): int
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
                $pid = isset($line['product_id']) ? (int)$line['product_id'] : null;

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
            // Ensure shipment
            $shipmentId = (int)$pdo->query("
                SELECT id FROM transfer_shipments
                WHERE transfer_id = " . (int)$transferId . "
                ORDER BY id DESC LIMIT 1
            ")->fetchColumn();

            if (!$shipmentId) {
                $shipmentId = $this->createShipment($transferId, $carrierName ?: 'internal_drive', 'internal_drive');
            }

            // Ensure parcel
            if (!$parcelId) {
                $boxNumber = ($boxNumber && $boxNumber > 0) ? (int)$boxNumber : 1;
                $sel       = $pdo->prepare("SELECT id FROM transfer_parcels WHERE shipment_id=:s AND box_number=:b LIMIT 1");
                $sel->execute([':s' => $shipmentId, ':b' => $boxNumber]);
                $parcelId = (int)($sel->fetchColumn() ?: 0);
                if (!$parcelId) {
                    $parcelId = $this->addParcel($shipmentId, (int)$boxNumber, 0);
                }
            }

            // Compute status (simpler than CASE WHEN :c)
            $status = (strtolower($carrierName) === 'internal_drive') ? 'in_transit' : 'label_printed';

            $upd = $pdo->prepare("
                UPDATE transfer_parcels
                   SET carrier_name   = :c,
                       tracking_number = :t,
                       tracking_url    = :u,
                       status          = :s,
                       updated_at      = NOW()
                 WHERE id = :id LIMIT 1
            ");
            $upd->execute([
                ':c' => ($carrierName ?: 'internal_drive'),
                ':t' => ($trackingNumber ?: null),
                ':u' => ($trackingUrl ?: null),
                ':s' => $status,
                ':id'=> (int)$parcelId,
            ]);

            $this->audit($transferId, 'tracking.set', [
                'parcel_id'      => $parcelId,
                'carrier'        => $carrierName,
                'tracking'       => $trackingNumber,
                'tracking_url'   => $trackingUrl,
                'computed_status'=> $status,
            ]);
            $this->log($transferId, "Tracking set for parcel #{$parcelId} carrier={$carrierName} tracking={$trackingNumber}");

            $pdo->commit();
            return ['ok' => true, 'parcel_id' => (int)$parcelId];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => 'tracking_persist_failed'];
        }
    }

    public function setShipmentMode(int $transferId, string $mode, ?string $status = null): array
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $shipmentId = (int)$pdo->query("
                SELECT id FROM transfer_shipments
                WHERE transfer_id = " . (int)$transferId . "
                ORDER BY id DESC LIMIT 1
            ")->fetchColumn();

            if (!$shipmentId) {
                $shipmentId = $this->createShipment($transferId, $mode === 'internal_drive' ? 'internal_drive' : 'MVP', $mode);
            }

            $sql    = "UPDATE transfer_shipments SET mode=:m" . ($status ? ", status=:s" : "") . " WHERE id=:id LIMIT 1";
            $params = [':m' => $mode, ':id' => $shipmentId];
            if ($status) $params[':s'] = $status;
            $st = $pdo->prepare($sql);
            $st->execute($params);

            $this->audit($transferId, 'shipment.mode', ['shipment_id' => $shipmentId, 'mode' => $mode, 'status' => $status]);

            $pdo->commit();
            return ['ok' => true, 'shipment_id' => $shipmentId];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
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
            $pid = (int)$it['product_id'];
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
            $mode       = (getenv('COURIERS_ENABLED') === '1') ? 'MVP' : 'internal_drive';
            $shipmentId = $this->createShipment($transferId, $carrier, $mode);

            $parcelsOut = [];
            $skipped    = [];
            $map        = $this->loadTransferItemMap($transferId);
            $idx        = 0;

            foreach ((array)$plan['parcels'] as $parcel) {
                $idx++;
                $weightG  = (int)($parcel['weight_g'] ?? 0);
                $parcelId = $this->addParcel($shipmentId, $idx, $weightG);

                $items = (array)($parcel['items'] ?? []);
                $count = 0;

                foreach ($items as $line) {
                    $qty = (int)($line['qty'] ?? 0);
                    $iid = isset($line['item_id']) ? (int)$line['item_id'] : null;
                    $pid = isset($line['product_id']) ? (int)$line['product_id'] : null;

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
                    'weight_kg'  => round($weightG / 1000, 3),
                    'items_count'=> $count,
                ];
            }

            $this->audit($transferId, 'mvp_label_created', [
                'shipment_id' => $shipmentId,
                'carrier'     => $carrier,
                'mode'        => $mode,
                'parcels'     => count($parcelsOut),
                'skipped'     => count($skipped),
            ]);
            $this->log($transferId, "Label created[{$mode}] shipment_id={$shipmentId} parcels=" . count($parcelsOut));

            $pdo->commit();
            return ['ok' => true, 'shipment_id' => $shipmentId, 'parcels' => $parcelsOut, 'skipped' => $skipped];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
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
            SELECT ti.id, ti.product_id, ti.request_qty AS requested_qty,
                   COALESCE(vp.sku,'') AS sku, COALESCE(vp.name,'') AS name
            FROM transfer_items ti
            LEFT JOIN vend_products vp ON vp.id = ti.product_id
            WHERE ti.transfer_id = :t
            ORDER BY ti.id ASC
        ");
        $q->execute([':t' => $transferId]);
        $rows = $q->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$r) {
            $pid  = (int)$r['product_id'];
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
            SELECT tp.id, tp.box_number, tp.weight_g,
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
                'weight_kg'  => round(((int)$r['weight_g']) / 1000, 3),
                'items_count'=> (int)$r['items_count'],
            ];
        }
        return ['shipment_id' => (int)$shipmentId, 'parcels' => $out];
    }

    public function createShipment(int $transferId, string $carrier, string $mode): int
    {
        $pdo = db();
        $q   = $pdo->prepare("
            INSERT INTO transfer_shipments(transfer_id, carrier, mode, created_at)
            VALUES (:t, :c, :m, NOW())
        ");
        $q->execute([':t' => $transferId, ':c' => $carrier, ':m' => $mode]);
        return (int)$pdo->lastInsertId();
    }

    public function addParcel(int $shipmentId, int $boxNumber, int $weightG): int
    {
        $pdo = db();
        $q   = $pdo->prepare("
            INSERT INTO transfer_parcels(shipment_id, box_number, weight_g, created_at)
            VALUES (:s, :b, :w, NOW())
        ");
        $q->execute([':s' => $shipmentId, ':b' => $boxNumber, ':w' => $weightG]);
        return (int)$pdo->lastInsertId();
    }

    public function attachItemToParcel(int $parcelId, int $transferItemId, int $qty): void
    {
        $pdo = db();
        $q   = $pdo->prepare("
            INSERT INTO transfer_parcel_items(parcel_id, transfer_item_id, qty)
            VALUES (:p, :i, :q)
            ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)
        ");
        $q->execute([':p' => $parcelId, ':i' => $transferItemId, ':q' => $qty]);
    }

    public function resolveTransferItemId(int $transferId, ?int $itemId, ?int $productId, ?array $preMap = null): ?int
    {
        $map = $preMap ?? $this->loadTransferItemMap($transferId);
        if ($itemId && isset($map['by_item'][$itemId]))    return $itemId;
        if ($productId && isset($map['by_product'][$productId])) return (int)$map['by_product'][$productId];
        return null;
    }

    public function loadTransferItemMap(int $transferId): array
    {
        $pdo = db();
        $q   = $pdo->prepare("SELECT id, product_id FROM transfer_items WHERE transfer_id=:t");
        $q->execute([':t' => $transferId]);
        $byItem = $byProduct = [];
        foreach ($q->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $id = (int)$r['id'];
            $pid = (int)$r['product_id'];
            $byItem[$id] = $pid;
            $byProduct[$pid] = $id;
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

    public function log(int $transferId, string $message): void
    {
        $pdo = db();
        try {
            $q = $pdo->prepare("INSERT INTO transfer_logs(transfer_id, message, created_at) VALUES (:t, :m, NOW())");
            $q->execute([':t' => $transferId, ':m' => $message]);
        } catch (\Throwable $e) {
        }
    }
}
