<?php
declare(strict_types=1);

namespace Modules\Transfers\Stock\Lib;

use Core\DB;
use PDO;

/**
 * AccessPolicy — centralize transfer access checks.
 * By default permissive; if your platform exposes userHasOutletAccess($uid,$outletId),
 * it will be used to enforce outlet-from/to access.
 */
final class AccessPolicy
{
    public static function canAccessTransfer(int $userId, int $transferId): bool
    {
        $db = DB::instance();
        $st = $db->prepare('SELECT outlet_from, outlet_to FROM transfers WHERE id=:id');
        $st->execute(['id'=>$transferId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;

        // Hook into existing platform policy if present
        if (function_exists('userHasOutletAccess')) {
            return userHasOutletAccess($userId, (string)$row['outlet_from'])
                && userHasOutletAccess($userId, (string)$row['outlet_to']);
        }
        // Default allow; tighten later when ready
        return true;
    }
}
