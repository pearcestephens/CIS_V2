-- Migration: Create schema_migrations audit table
-- Tracks executed migration files with checksum and outcome

CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `checksum_sha256` char(64) NOT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `executed_by` varchar(64) DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 1,
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_schema_migrations_file_checksum` (`filename`,`checksum_sha256`),
  KEY `idx_schema_migrations_time` (`executed_at`),
  KEY `idx_schema_migrations_success` (`success`,`executed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
