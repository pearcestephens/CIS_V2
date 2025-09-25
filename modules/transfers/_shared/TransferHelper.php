<?php
declare(strict_types=1);

namespace CIS\Transfers\Shared;

/**
 * TransferHelper
 * Central helpers reused by Pack/Receive modules.
 * - ID maps for transfer_items
 * - Resolution by item_id/product_id
 * - Parcel plan validation
 */
final class TransferHelper
{
    /** Build maps of transfer_items for fast lookups. */
    public static function loadTransferItemMap(\PDO $pdo, int $transferId): array
    {
        $q = $pdo->prepare("SELECT id, product_id FROM transfer_items WHERE transfer_id = :t");
        $q->execute([':t' => $transferId]);

        $byItem = [];
        $byProduct = [];
        foreach ($q->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $id = (int)$r['id'];
            $pid = (int)$r['product_id'];
            $byItem[$id] = $pid;
            $byProduct[$pid] = $id;
        }
        return ['by_item' => $byItem, 'by_product' => $byProduct];
    }

    /** Resolve transfer_item.id by either provided item_id or product_id. */
    public static function resolveTransferItemId(int $transferId, ?int $itemId, ?int $productId, array $preMap): ?int
    {
        if ($itemId && isset($preMap['by_item'][$itemId])) {
            return $itemId;
        }
        if ($productId && isset($preMap['by_product'][$productId])) {
            return (int)$preMap['by_product'][$productId];
        }
        return null;
    }

    /** Validate a parcel plan for a given transfer. */
    public static function validateParcelPlan(int $transferId, array $plan, array $preMap): array
    {
        $attachable = [];
        $unknown = [];
        $parcels = (array)($plan['parcels'] ?? []);

        foreach ($parcels as $pi => $parcel) {
            $items = (array)($parcel['items'] ?? []);
            foreach ($items as $line) {
                $qty = (int)($line['qty'] ?? 0);
                $iid = isset($line['item_id']) ? (int)$line['item_id'] : null;
                $pid = isset($line['product_id']) ? (int)$line['product_id'] : null;
                $resolved = self::resolveTransferItemId($transferId, $iid, $pid, $preMap);
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
                'parcels' => count($parcels),
                'lines_ok' => count($attachable),
                'lines_unknown' => count($unknown),
            ],
        ];
    }
}
