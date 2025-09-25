<?php
declare(strict_types=1);

namespace Modules\Transfers\Stock\Services;

use Core\DB;
use PDO;
use Throwable;

final class FreightCalculator
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::instance();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Resolve numeric carrier_id from a canonical code (e.g., 'NZ_POST', 'GSS').
     * Returns null if not found.
     */
    public function getCarrierIdByCode(string $code): ?int
    {
        $stmt = $this->db->prepare('SELECT carrier_id FROM carriers WHERE code = :c LIMIT 1');
        $stmt->execute(['c' => $code]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int)$id : null;
    }

    /**
     * Fetches items on the transfer with resolved unit weight (grams).
     * Fallback order: vend_products.avg_weight_grams → category_weights.avg_weight_grams → 100g default.
     *
     * @return array<int, array{id:int, product_id:string, qty:int, unit_weight_g:int, line_weight_g:int}>
     */
    public function getWeightedItems(int $transferId): array
    {
        $sql = <<<SQL
        SELECT
          ti.id                                  AS id,
          ti.product_id                          AS product_id,
          COALESCE(ti.qty_sent_total, 0)         AS qty,
          COALESCE(vp.avg_weight_grams,
                   cw.avg_weight_grams,
                   100)                           AS unit_weight_g
        FROM transfer_items ti
        LEFT JOIN vend_products vp ON vp.id = ti.product_id
        LEFT JOIN product_classification_unified pcu ON pcu.product_id = ti.product_id
        LEFT JOIN category_weights cw ON cw.category_id = pcu.category_id
        WHERE ti.transfer_id = :tid
          AND COALESCE(ti.qty_sent_total,0) > 0
        ORDER BY ti.id ASC
        SQL;

        $st = $this->db->prepare($sql);
        $st->execute(['tid' => $transferId]);

        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $qty    = (int)$r['qty'];
            $unit   = max(0, (int)$r['unit_weight_g']);
            $line   = $qty * $unit;
            $rows[] = [
                'id'             => (int)$r['id'],
                'product_id'     => (string)$r['product_id'],
                'qty'            => $qty,
                'unit_weight_g'  => $unit,
                'line_weight_g'  => $line,
            ];
        }
        return $rows;
    }

    /**
     * Pick the cheapest valid container for a given carrier and weight (grams).
     * Valid if max_weight_grams is NULL or >= required grams.
     * Tie-breaks: lowest cost → lowest max_weight_grams → lowest container_id.
     *
     * @return array|null {container_id, code, kind, max_weight_grams, cost}
     */
    public function pickContainer(int $carrierId, int $weightGrams): ?array
    {
        $sql = <<<SQL
        SELECT
          c.container_id,
          c.code,
          c.kind,
          fr.max_weight_grams,
          fr.cost
        FROM containers c
        JOIN freight_rules fr ON fr.container_id = c.container_id
        WHERE c.carrier_id = :cid
          AND (fr.max_weight_grams IS NULL OR fr.max_weight_grams >= :w)
        ORDER BY fr.cost ASC,
                 fr.max_weight_grams ASC,
                 c.container_id ASC
        LIMIT 1
        SQL;

        $st = $this->db->prepare($sql);
        $st->execute(['cid' => $carrierId, 'w' => $weightGrams]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        return [
            'container_id'     => (int)$row['container_id'],
            'code'             => (string)$row['code'],
            'kind'             => (string)$row['kind'],
            'max_weight_grams' => isset($row['max_weight_grams']) ? (int)$row['max_weight_grams'] : null,
            'cost'             => (float)$row['cost'],
        ];
    }

    /**
     * Greedy parcelization: split total weight into N parcels each ≤ container cap.
     * If container has no cap, return one parcel.
     *
     * @return array<int,int> grams per parcel
     */
    public function planParcelsByCap(int $totalGrams, ?int $capGrams): array
    {
        if ($totalGrams <= 0) return [];
        if (empty($capGrams)) return [$totalGrams];

        $parcels = [];
        $remain  = $totalGrams;
        while ($remain > 0) {
            $take = min($remain, $capGrams);
            $parcels[] = $take;
            $remain -= $take;
        }
        return $parcels;
    }
}
