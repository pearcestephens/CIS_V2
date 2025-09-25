<?php
declare(strict_types=1);

/**
 * Unified Transfer Model (base)
 *
 * Intent: Provide a shared shape for multiple transfer types that map to a common schema similar to
 * Lightspeed Consignment: a header + lines, with origin/destination, status transitions, and optional
 * shipping/tracking metadata. Purchase Orders are modeled separately due to the Supplier party.
 */

namespace Transfers\Base;

final class Types
{
    public const STOCK = 'STOCK';        // outlet→outlet
    public const JUICE = 'JUICE';        // facility→facility
    public const INSTORE = 'INSTORE';    // within outlet (bin/area moves)

    /** @return string[] */
    public static function all(): array { return [self::STOCK, self::JUICE, self::INSTORE]; }
    public static function isValid(string $t): bool { return in_array(strtoupper($t), self::all(), true); }
}

final class Status
{
    // Keep generic lifecycle compatible with numeric or string schemas
    public const OPEN     = 'OPEN';      // 0
    public const READY    = 'READY';     // 1 (packed)
    public const SENT     = 'SENT';      // 2
    public const RECEIVED = 'RECEIVED';  // 3
}

final class Model
{
    /** Normalize a DB row into a stable shape across schemas. */
    public static function normalizeHeader(array $row): array
    {
        // Accept both stock_transfers and transfers style columns
        $id  = (int)($row['id'] ?? $row['transfer_id'] ?? 0);
        $src = $row['source_outlet_id'] ?? $row['outlet_from'] ?? null;
        $dst = $row['dest_outlet_id']   ?? $row['outlet_to']   ?? null;
        $status = $row['status'] ?? 0;
        $statusS = is_numeric($status) ? self::statusNumberToString((int)$status) : strtoupper((string)$status);
        $type = strtoupper((string)($row['transfer_type'] ?? Types::STOCK));
        if (!Types::isValid($type)) $type = Types::STOCK;

        return [
            'id' => $id,
            'type' => $type,
            'source' => $src,
            'destination' => $dst,
            'status' => $statusS,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            // Optional shipping/tracking
            'tracking_number' => $row['tracking_number'] ?? null,
            'carrier' => $row['carrier'] ?? $row['courier'] ?? null,
        ];
    }

    public static function statusNumberToString(int $n): string
    {
        switch ($n) {
            case 1: return Status::READY;
            case 2: return Status::SENT;
            case 3: return Status::RECEIVED;
            default: return Status::OPEN;
        }
    }
}
