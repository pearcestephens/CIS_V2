-- Migration: Receive core tables
-- Creates transfer_receipts and transfer_receipt_items aligning to transfers schema

CREATE TABLE IF NOT EXISTS `transfer_receipts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `transfer_id` INT(11) NOT NULL,
  `received_by` INT(11) DEFAULT NULL,
  `received_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tr_transfer` (`transfer_id`),
  KEY `idx_tr_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Header for receive sessions against a transfer';

CREATE TABLE IF NOT EXISTS `transfer_receipt_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `receipt_id` INT(11) NOT NULL,
  `transfer_item_id` INT(11) NOT NULL,
  `qty_received` INT(11) NOT NULL DEFAULT 0,
  `condition` VARCHAR(32) DEFAULT 'ok',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_receipt_item` (`receipt_id`,`transfer_item_id`),
  KEY `idx_tri_receipt` (`receipt_id`),
  KEY `idx_tri_item` (`transfer_item_id`),
  CONSTRAINT `chk_tri_qty_nonneg` CHECK (`qty_received` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lines received per transfer item with optional condition/notes';

-- Conditionally add foreign keys when base tables exist
SET @db := DATABASE();

-- fk_tr_transfer
SET @has_transfers := (
  SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='transfers'
);
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.table_constraints 
  WHERE table_schema=@db AND table_name='transfer_receipts' AND constraint_type='FOREIGN KEY' AND constraint_name='fk_tr_transfer'
);
SET @sql := IF(@has_transfers>0 AND @fk_exists=0,
  'ALTER TABLE `transfer_receipts` ADD CONSTRAINT `fk_tr_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION',
  'SELECT 1'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- fk_tri_receipt
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.table_constraints 
  WHERE table_schema=@db AND table_name='transfer_receipt_items' AND constraint_type='FOREIGN KEY' AND constraint_name='fk_tri_receipt'
);
SET @sql := IF(@fk_exists=0,
  'ALTER TABLE `transfer_receipt_items` ADD CONSTRAINT `fk_tri_receipt` FOREIGN KEY (`receipt_id`) REFERENCES `transfer_receipts` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION',
  'SELECT 1'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- fk_tri_item
SET @has_transfer_items := (
  SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='transfer_items'
);
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.table_constraints 
  WHERE table_schema=@db AND table_name='transfer_receipt_items' AND constraint_type='FOREIGN KEY' AND constraint_name='fk_tri_item'
);
SET @sql := IF(@has_transfer_items>0 AND @fk_exists=0,
  'ALTER TABLE `transfer_receipt_items` ADD CONSTRAINT `fk_tri_item` FOREIGN KEY (`transfer_item_id`) REFERENCES `transfer_items` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION',
  'SELECT 1'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
