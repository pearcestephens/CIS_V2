-- Migration: container_rules for packing guidance

CREATE TABLE IF NOT EXISTS `container_rules` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `alias` VARCHAR(64) NOT NULL,
  `max_weight_g` INT(11) NOT NULL,
  `max_items` INT(11) NOT NULL DEFAULT 0,
  `dimensions_mm` VARCHAR(64) DEFAULT NULL COMMENT 'LxWxH mm',
  `notes` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_alias` (`alias`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Packing container rules: weight limits and guidelines';
