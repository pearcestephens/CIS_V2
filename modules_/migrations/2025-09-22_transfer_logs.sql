-- Migration: Ensure transfer_logs and transfer_audit_log exist with required columns and indexes
-- Idempotent: uses CREATE TABLE IF NOT EXISTS and conditional indexes

-- transfer_logs
CREATE TABLE IF NOT EXISTS `transfer_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` bigint NULL,
  `shipment_id` bigint NULL,
  `item_id` bigint NULL,
  `parcel_id` bigint NULL,
  `staff_transfer_id` bigint NULL,
  `event_type` varchar(64) NOT NULL,
  `event_data` json NULL,
  `actor_user_id` int NULL,
  `actor_role` varchar(64) NULL,
  `severity` enum('info','warn','error') NOT NULL DEFAULT 'info',
  `source_system` varchar(64) NOT NULL DEFAULT 'cis.transfers',
  `trace_id` varchar(64) NULL,
  `customer_id` varchar(64) NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transfer_type_time` (`transfer_id`,`event_type`,`created_at`),
  KEY `idx_trace` (`trace_id`),
  KEY `idx_source_severity_time` (`source_system`,`severity`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- transfer_audit_log
CREATE TABLE IF NOT EXISTS `transfer_audit_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(64) NOT NULL,
  `entity_pk` bigint NULL,
  `transfer_pk` bigint NULL,
  `transfer_id` bigint NULL,
  `vend_consignment_id` varchar(64) NULL,
  `vend_transfer_id` varchar(64) NULL,
  `action` varchar(64) NOT NULL,
  `status` varchar(32) NOT NULL,
  `actor_type` varchar(32) NOT NULL,
  `actor_id` varchar(64) NULL,
  `outlet_from` varchar(64) NULL,
  `outlet_to` varchar(64) NULL,
  `data_before` json NULL,
  `data_after` json NULL,
  `metadata` json NULL,
  `error_details` json NULL,
  `processing_time_ms` int NULL,
  `api_response` json NULL,
  `session_id` varchar(128) NULL,
  `ip_address` varchar(64) NULL,
  `user_agent` varchar(255) NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_entity_action_time` (`entity_type`,`action`,`created_at`),
  KEY `idx_transfer_time` (`transfer_id`,`created_at`),
  KEY `idx_status_time` (`status`,`created_at`),
  KEY `idx_actor_time` (`actor_type`,`actor_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add covering indexes (MariaDB 10.5 compatible conditional creation)
SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'transfer_logs' AND index_name = 'idx_tl_transfer_type_time');
SET @sql := IF(@exists = 0, 'CREATE INDEX `idx_tl_transfer_type_time` ON `transfer_logs` (`transfer_id`,`event_type`,`created_at`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'transfer_logs' AND index_name = 'idx_tl_trace');
SET @sql := IF(@exists = 0, 'CREATE INDEX `idx_tl_trace` ON `transfer_logs` (`trace_id`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'transfer_logs' AND index_name = 'idx_tl_source_severity_time');
SET @sql := IF(@exists = 0, 'CREATE INDEX `idx_tl_source_severity_time` ON `transfer_logs` (`source_system`,`severity`,`created_at`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'transfer_audit_log' AND index_name = 'idx_tal_entity_action_time');
SET @sql := IF(@exists = 0, 'CREATE INDEX `idx_tal_entity_action_time` ON `transfer_audit_log` (`entity_type`,`action`,`created_at`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'transfer_audit_log' AND index_name = 'idx_tal_transfer_time');
SET @sql := IF(@exists = 0, 'CREATE INDEX `idx_tal_transfer_time` ON `transfer_audit_log` (`transfer_id`,`created_at`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'transfer_audit_log' AND index_name = 'idx_tal_status_time');
SET @sql := IF(@exists = 0, 'CREATE INDEX `idx_tal_status_time` ON `transfer_audit_log` (`status`,`created_at`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'transfer_audit_log' AND index_name = 'idx_tal_actor_time');
SET @sql := IF(@exists = 0, 'CREATE INDEX `idx_tal_actor_time` ON `transfer_audit_log` (`actor_type`,`actor_id`,`created_at`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
