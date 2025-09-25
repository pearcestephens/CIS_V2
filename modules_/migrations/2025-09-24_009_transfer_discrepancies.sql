-- Migration: transfer_discrepancies table for receive reconciliation
-- Purpose: track shorts/overs/damages/other during receiving, with status and resolution fields

CREATE TABLE IF NOT EXISTS `transfer_discrepancies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transfer_id` BIGINT UNSIGNED NOT NULL,
  `transfer_item_id` BIGINT UNSIGNED DEFAULT NULL,
  `type` ENUM('short','over','damage','other') NOT NULL,
  `qty_expected` INT NOT NULL DEFAULT 0,
  `qty_actual` INT NOT NULL DEFAULT 0,
  `notes` TEXT DEFAULT NULL,
  `status` ENUM('open','resolved') NOT NULL DEFAULT 'open',
  `resolved_by` BIGINT UNSIGNED DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_td_transfer_created` (`transfer_id`,`created_at`),
  KEY `idx_td_status_created` (`status`,`created_at`),
  KEY `idx_td_item_created` (`transfer_item_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Discrepancies raised during receiving of transfers';

-- Conditionally add foreign keys when base tables exist (MariaDB 10.5 safe)
SET @db := DATABASE();

-- fk_td_transfer
SET @has_transfers := (
  SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='transfers'
);
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.table_constraints 
  WHERE table_schema=@db AND table_name='transfer_discrepancies' AND constraint_type='FOREIGN KEY' AND constraint_name='fk_td_transfer'
);
SET @sql := IF(@has_transfers>0 AND @fk_exists=0,
  'ALTER TABLE `transfer_discrepancies` ADD CONSTRAINT `fk_td_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION',
  'DO 0'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Create indexes if missing (MariaDB 10.5 compatible)
-- Ensure column transfer_item_id exists before creating related index
SET @has_col := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema=@db AND table_name='transfer_discrepancies' AND column_name='transfer_item_id'
);
SET @sql := IF(@has_col=0,
  'ALTER TABLE `transfer_discrepancies` ADD COLUMN `transfer_item_id` BIGINT UNSIGNED DEFAULT NULL AFTER `transfer_id`',
  'DO 0'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='transfer_discrepancies' AND index_name='idx_td_transfer_created'
);
SET @sql := IF(@exists=0,
  'CREATE INDEX `idx_td_transfer_created` ON `transfer_discrepancies` (`transfer_id`,`created_at`)',
  'DO 0'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='transfer_discrepancies' AND index_name='idx_td_status_created'
);
SET @sql := IF(@exists=0,
  'CREATE INDEX `idx_td_status_created` ON `transfer_discrepancies` (`status`,`created_at`)',
  'DO 0'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='transfer_discrepancies' AND index_name='idx_td_item_created'
);
SET @sql := IF(@exists=0,
  'CREATE INDEX `idx_td_item_created` ON `transfer_discrepancies` (`transfer_item_id`,`created_at`)',
  'DO 0'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- fk_td_item
SET @has_transfer_items := (
  SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='transfer_items'
);
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.table_constraints 
  WHERE table_schema=@db AND table_name='transfer_discrepancies' AND constraint_type='FOREIGN KEY' AND constraint_name='fk_td_item'
);
SET @sql := IF(@has_transfer_items>0 AND @fk_exists=0,
  'ALTER TABLE `transfer_discrepancies` ADD CONSTRAINT `fk_td_item` FOREIGN KEY (`transfer_item_id`) REFERENCES `transfer_items` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION',
  'DO 0'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
