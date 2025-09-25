-- 004_transfer_carrier_orders.sql
-- Purpose: Persist external carrier order context (e.g., NZ Post eShip order_id/order_number) per transfer.
-- Idempotent: Safe to run multiple times. Creates table if not exists and keys accordingly.

CREATE TABLE IF NOT EXISTS `transfer_carrier_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Row ID',
  `transfer_id` int(11) NOT NULL COMMENT 'FK to transfers.id',
  `carrier` varchar(50) NOT NULL COMMENT 'Carrier code e.g., NZ_POST, GSS',
  `order_id` varchar(100) DEFAULT NULL COMMENT 'Carrier order identifier (string, may be numeric)',
  `order_number` varchar(100) NOT NULL COMMENT 'Our canonical order number (e.g., TR-1234)',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)) COMMENT 'Raw API response snapshot (JSON)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_transfer_carrier` (`transfer_id`,`carrier`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_order_number` (`order_number`),
  CONSTRAINT `fk_tco_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='External carrier orders per transfer (NZ Post, GSS, etc).';

-- Upsert helper (for reference):
-- INSERT INTO transfer_carrier_orders (transfer_id, carrier, order_id, order_number, payload)
-- VALUES (?,?,?,?,?)
-- ON DUPLICATE KEY UPDATE order_id=VALUES(order_id), order_number=VALUES(order_number), payload=VALUES(payload), updated_at=NOW();
