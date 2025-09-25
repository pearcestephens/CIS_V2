-- Migration: create transfer_media_tokens and extend transfer_media for capture metadata
-- Author: GitHub Copilot
-- Created: 2025-09-25

START TRANSACTION;

CREATE TABLE IF NOT EXISTS transfer_media_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT UNSIGNED NOT NULL,
    parcel_id INT UNSIGNED NULL,
    discrepancy_id INT UNSIGNED NULL,
    scope ENUM('transfer','parcel','discrepancy') NOT NULL DEFAULT 'transfer',
    token CHAR(64) NOT NULL,
    secret_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    allow_mime JSON NULL,
    max_bytes INT UNSIGNED NOT NULL DEFAULT 15728640,
    status ENUM('issued','used','expired','revoked') NOT NULL DEFAULT 'issued',
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME NULL,
    used_by INT UNSIGNED NULL,
    meta JSON NULL,
    UNIQUE KEY uq_media_token (token),
    KEY idx_media_tokens_transfer (transfer_id, status),
    KEY idx_media_tokens_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE transfer_media
    ADD COLUMN IF NOT EXISTS token_id INT UNSIGNED NULL AFTER discrepancy_id,
    ADD COLUMN IF NOT EXISTS captured_by INT UNSIGNED NULL AFTER token_id,
    ADD COLUMN IF NOT EXISTS captured_by_name VARCHAR(120) NULL AFTER captured_by,
    ADD COLUMN IF NOT EXISTS captured_ip VARCHAR(45) NULL AFTER captured_by_name,
    ADD COLUMN IF NOT EXISTS captured_lat DECIMAL(10,7) NULL AFTER captured_ip,
    ADD COLUMN IF NOT EXISTS captured_lng DECIMAL(10,7) NULL AFTER captured_lat,
    ADD COLUMN IF NOT EXISTS captured_meta JSON NULL AFTER captured_lng,
    ADD COLUMN IF NOT EXISTS captured_at DATETIME NULL AFTER captured_meta,
    ADD COLUMN IF NOT EXISTS source VARCHAR(32) NOT NULL DEFAULT 'mobile' AFTER captured_at,
    ADD COLUMN IF NOT EXISTS device_label VARCHAR(120) NULL AFTER source;

ALTER TABLE transfer_media
    ADD KEY IF NOT EXISTS idx_transfer_media_token (token_id),
    ADD KEY IF NOT EXISTS idx_transfer_media_capture (captured_at),
    ADD KEY IF NOT EXISTS idx_transfer_media_latlng (captured_lat, captured_lng);

COMMIT;
