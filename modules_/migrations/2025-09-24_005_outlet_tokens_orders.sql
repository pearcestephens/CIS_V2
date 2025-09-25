-- Migration: Courier tokens at outlet-level and ensure transfer_carrier_orders table exists

CREATE TABLE IF NOT EXISTS `outlet_courier_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `outlet_id` VARCHAR(64) NOT NULL,
  `carrier` VARCHAR(50) NOT NULL,
  `token_sealed` VARBINARY(4096) NOT NULL COMMENT 'Encrypted/Sealed token blob',
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_outlet_carrier` (`outlet_id`,`carrier`),
  KEY `idx_outlet_carrier` (`outlet_id`,`carrier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-outlet sealed credentials for courier integrations';

CREATE TABLE IF NOT EXISTS `transfer_carrier_orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Row ID',
  `transfer_id` INT(11) NOT NULL COMMENT 'FK to transfers.id',
  `carrier` VARCHAR(50) NOT NULL COMMENT 'Carrier code e.g., NZ_POST, GSS',
  `order_id` VARCHAR(100) DEFAULT NULL COMMENT 'Carrier order identifier (string, may be numeric)',
  `order_number` VARCHAR(100) NOT NULL COMMENT 'Our canonical order number (e.g., TR-1234)',
  `payload` LONGTEXT DEFAULT NULL COMMENT 'Raw API response snapshot (JSON)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_transfer_carrier` (`transfer_id`,`carrier`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_order_number` (`order_number`),
  CONSTRAINT `fk_tco_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='External carrier orders per transfer (NZ Post, GSS, etc).';
