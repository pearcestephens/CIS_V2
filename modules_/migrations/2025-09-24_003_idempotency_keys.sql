-- Migration: Create idempotency_keys table for middleware mw_idempotency()
-- Safe/idempotent: CREATE TABLE IF NOT EXISTS; UNIQUE on idem_key

CREATE TABLE IF NOT EXISTS `idempotency_keys` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `idem_key` VARBINARY(64) NOT NULL,
  `request_hash` VARBINARY(64) NOT NULL,
  `response_json` JSON NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_idem` (`idem_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
