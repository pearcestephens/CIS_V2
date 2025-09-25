<?php
declare(strict_types=1);

namespace Modules\Transfers\Stock\Services;

use Core\DB;
use PDO;
use Throwable;

final class TransfersService
{
    private PDO $db;
    private TransferLogger $logger;

    public function __construct()
    {
        $this->db = DB::instance();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->logger = new TransferLogger();
    }

    public function getTransfer(int $id): ?array
    {
        $tx = $this->db->prepare(
            'SELECT id, public_id, outlet_from, outlet_to, status, state, created_at
               FROM transfers
              WHERE id = :id
              LIMIT 1'
        );
        $tx->execute(['id' => $id]);
        $transfer = $tx->fetch(PDO::FETCH_ASSOC);
        if (!$transfer) return null;

        $it = $this->db->prepare(
            'SELECT id, product_id, qty_requested, qty_sent_total, qty_received_total
               FROM transfer_items
              WHERE transfer_id = :tid
              ORDER BY id ASC'
        );
        $it->execute(['tid' => $id]);
        $transfer['items'] = $it->fetchAll(PDO::FETCH_ASSOC);

        return $transfer;
    }

    /**
     * Save PACK quantities (absolute values) and mark PACKAGED.
     * Then create a shipment wave with parcels and parcel_items for the DELTA (new - old).
     *
     * Payload (examples):
     * {
     *   "items": [{ "id":123, "qty_sent_total": 6 }, ...],
     *   "carrier": "NZ_POST" | "GSS" | "MANUAL",
     *   "packages": [
     *      { "weight_grams": 2500, "length_mm":300, "width_mm":200, "height_mm":150, "tracking":"ABC123", "qty":1 },
     *      ...
     *   ],
     *   "trackingNumbers": ["ABC123","DEF456"],   // optional; assigned in order to newly created parcels
     *   "notes": "Optional pack note"             // optional; stored in transfer_notes
     * }
     */
    public function savePack(int $transferId, array $payload, int $userId): array
    {
        if ($transferId <= 0) {
            return ['success' => false, 'error' => 'Missing transfer id'];
        }
        $postedItems = $payload['items'] ?? [];
        if (!is_array($postedItems)) {
            return ['success' => false, 'error' => 'Invalid payload format'];
        }

        // 1) Read current sent totals to compute wave deltas.
        $current = $this->fetchCurrentSentMap($transferId);  // [item_id => qty_sent_total]
        $waveItems = []; // this wave only (delta)
        $packages  = $this->normalizePackages($payload['packages'] ?? []);
        $carrier   = strtoupper((string)($payload['carrier'] ?? 'NZ_POST'));
        $noteText  = isset($payload['notes']) ? trim((string)$payload['notes']) : '';

        try {
            $this->db->beginTransaction();

            // 2) Update absolute sent totals (enforce bounds to avoid CHECK constraint failures).
            $upd = $this->db->prepare(
                'UPDATE transfer_items
                    SET qty_sent_total = :sent, updated_at = NOW()
                  WHERE id = :iid AND transfer_id = :tid'
            );
            foreach ($postedItems as $row) {
                $iid  = (int)($row['id'] ?? 0);
                $new  = max(0, (int)($row['qty_sent_total'] ?? 0));
                if ($iid <= 0) continue;

                // Enforce <= requested
                $req = $this->fetchRequested($iid, $transferId);
                if ($req !== null && $new > $req) $new = $req;

                // Compute delta (wave qty)
                $old = $current[$iid] ?? 0;
                $delta = $new - $old;
                if ($delta > 0) {
                    $waveItems[] = ['item_id' => $iid, 'qty_sent' => $delta];
                }

                $upd->execute(['sent' => $new, 'iid' => $iid, 'tid' => $transferId]);
            }

            // 3) State: PACKAGED (status=sent to preserve your existing semantics)
            $this->db->prepare(
                "UPDATE transfers
                    SET status='sent', state='PACKAGED', updated_at = NOW()
                  WHERE id = :tid"
            )->execute(['tid' => $transferId]);

            // 4) Audit (legacy table)
            $this->db->prepare(
                "INSERT INTO transfer_audit_log
                   (entity_type, entity_pk, transfer_pk, action, status, actor_type, actor_id, created_at)
                 VALUES ('transfer', :tid, :tid, 'PACK_SAVE', 'success', 'user', :uid, NOW())"
            )->execute(['tid' => $transferId, 'uid' => $userId ?: null]);

            // 5) Note (optional)
            if ($noteText !== '') {
                (new NotesService())->addTransferNote($transferId, $noteText, $userId);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return ['success' => false, 'error' => 'Save failed: ' . $e->getMessage()];
        }

        // 6) Post-commit: create shipment wave for the delta items, with parcels and parcel_items.
        $shipmentResult = null;
        if (!empty($waveItems)) {
            try {
                $shipmentResult = (new ShipmentService())->createShipmentWithParcelsAndItems(
                    transferId:  $transferId,
                    deliveryMode: 'courier',
                    carrierName:  $carrier,
                    itemsSent:    $waveItems,
                    parcels:      $packages ?: [['weight_grams' => null]], // at least 1 parcel
                    userId:       $userId
                );

                // Assign tracking numbers to newly created parcels (if provided and not already set)
                if (!empty($payload['trackingNumbers']) && is_array($payload['trackingNumbers'])) {
                    $track = array_values(array_filter(array_map('strval', $payload['trackingNumbers'])));
                    if (!empty($shipmentResult['parcel_ids']) && !empty($track)) {
                        $trackingSvc = new TrackingService();
                        foreach ($shipmentResult['parcel_ids'] as $idx => $pid) {
                            if (!isset($track[$idx])) break;
                            $trackingSvc->setParcelTracking((int)$pid, $track[$idx], $carrier, $transferId);
                        }
                    }
                }

                // High-level log
                $this->logger->log('PACKED', [
                    'transfer_id'   => $transferId,
                    'shipment_id'   => $shipmentResult['shipment_id'] ?? null,
                    'actor_user_id' => $userId,
                    'event_data'    => [
                        'items'   => $waveItems,
                        'parcels' => $shipmentResult['parcel_ids'] ?? [],
                        'carrier' => $carrier
                    ]
                ]);
            } catch (Throwable $e) {
                // Non-fatal: packing saved; shipment creation failed → log error and return warning.
                $this->logger->log('EXCEPTION', [
                    'transfer_id' => $transferId,
                    'severity'    => 'error',
                    'event_data'  => ['op' => 'createShipment', 'error' => $e->getMessage()]
                ]);
                return [
                    'success' => true,
                    'warning' => 'Shipment creation failed: ' . $e->getMessage(),
                ];
            }
        } else {
            // No positive deltas → nothing to ship this wave
            $this->logger->log('PACKED', [
                'transfer_id'   => $transferId,
                'actor_user_id' => $userId,
                'event_data'    => ['note' => 'No new quantities to ship in this wave']
            ]);
        }

        return [
            'success'         => true,
            'shipment_id'     => $shipmentResult['shipment_id'] ?? null,
            'parcel_ids'      => $shipmentResult['parcel_ids']   ?? [],
            'carrier'         => $carrier,
        ];
    }

    // --- helpers -------------------------------------------------------------

    /** Map of current sent totals for this transfer: [item_id => qty_sent_total] */
    private function fetchCurrentSentMap(int $transferId): array
    {
        $st = $this->db->prepare(
            'SELECT id, qty_sent_total FROM transfer_items WHERE transfer_id = :tid'
        );
        $st->execute(['tid' => $transferId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int)$r['id']] = (int)$r['qty_sent_total'];
        }
        return $out;
    }

    /** Fetch requested qty to clamp sent totals (avoid violating CHECK). */
    private function fetchRequested(int $itemId, int $transferId): ?int
    {
        $st = $this->db->prepare(
            'SELECT qty_requested FROM transfer_items WHERE id = :iid AND transfer_id = :tid'
        );
        $st->execute(['iid' => $itemId, 'tid' => $transferId]);
        $v = $st->fetchColumn();
        return ($v === false) ? null : (int)$v;
    }

    /** Normalize packages array (shape used by ShipmentService). */
    private function normalizePackages(array $packages): array
    {
        $out = [];
        foreach ($packages as $p) {
            $out[] = [
                'weight_grams' => isset($p['weight_grams']) ? (int)$p['weight_grams'] : null,
                'length_mm'    => isset($p['length_mm']) ? (int)$p['length_mm'] : null,
                'width_mm'     => isset($p['width_mm'])  ? (int)$p['width_mm']  : null,
                'height_mm'    => isset($p['height_mm']) ? (int)$p['height_mm'] : null,
                'tracking'     => isset($p['tracking']) ? (string)$p['tracking'] : null,
                'qty'          => max(1, (int)($p['qty'] ?? 1)),
            ];
        }
        return $out;
    }
}
