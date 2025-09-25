<?php
declare(strict_types=1);

namespace Modules\Transfers\Stock\Services;

use Core\DB;
use PDO;

final class TransferLogger
{
    private PDO $db;
    public function __construct() { $this->db = DB::instance(); }

    /**
     * Write immutable event log.
     * $eventType examples: CREATE|STATUS_CHANGE|ADD_ITEM|PACKED|SENT|RECEIVED|CANCELLED|NOTE|WEBHOOK|TRACKING|EXCEPTION
     */
    public function log(
        string $eventType,
        array  $opts = []  // ['transfer_id'?int, 'shipment_id'?int, 'parcel_id'?int, 'item_id'?int, 'actor_user_id'?int, 'severity'?'info'|'warning'|'error'|'critical', 'source_system'?'CIS'|..., 'event_data'?array, 'trace_id'?string]
    ): void {
        $sql = "INSERT INTO transfer_logs
                (transfer_id, shipment_id, item_id, parcel_id, staff_transfer_id,
                 event_type, event_data, actor_user_id, actor_role, severity,
                 source_system, trace_id, created_at, customer_id)
                VALUES
                (:transfer_id, :shipment_id, :item_id, :parcel_id, NULL,
                 :event_type, :event_data, :actor_user_id, NULL, :severity,
                 :source_system, :trace_id, NOW(), NULL)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'transfer_id'   => $opts['transfer_id']   ?? null,
            'shipment_id'   => $opts['shipment_id']   ?? null,
            'item_id'       => $opts['item_id']       ?? null,
            'parcel_id'     => $opts['parcel_id']     ?? null,
            'event_type'    => $eventType,
            'event_data'    => isset($opts['event_data']) ? json_encode($opts['event_data'], JSON_UNESCAPED_SLASHES) : null,
            'actor_user_id' => $opts['actor_user_id'] ?? null,
            'severity'      => $opts['severity']      ?? 'info',
            'source_system' => $opts['source_system']  ?? 'CIS',
            'trace_id'      => $opts['trace_id']      ?? null,
        ]);
    }
}
