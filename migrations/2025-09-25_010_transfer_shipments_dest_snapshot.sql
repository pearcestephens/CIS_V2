-- Migration: Add destination snapshot columns to transfer_shipments
-- Purpose : Persist address overrides used during label dispatch for audit
-- Author  : GitHub Copilot
-- Created : 2025-09-25

ALTER TABLE transfer_shipments
  ADD COLUMN IF NOT EXISTS dest_name         VARCHAR(160)   NULL AFTER delivery_mode,
  ADD COLUMN IF NOT EXISTS dest_company      VARCHAR(160)   NULL AFTER dest_name,
  ADD COLUMN IF NOT EXISTS dest_addr1        VARCHAR(160)   NULL AFTER dest_company,
  ADD COLUMN IF NOT EXISTS dest_addr2        VARCHAR(160)   NULL AFTER dest_addr1,
  ADD COLUMN IF NOT EXISTS dest_suburb       VARCHAR(120)   NULL AFTER dest_addr2,
  ADD COLUMN IF NOT EXISTS dest_city         VARCHAR(120)   NULL AFTER dest_suburb,
  ADD COLUMN IF NOT EXISTS dest_postcode     VARCHAR(16)    NULL AFTER dest_city,
  ADD COLUMN IF NOT EXISTS dest_email        VARCHAR(190)   NULL AFTER dest_postcode,
  ADD COLUMN IF NOT EXISTS dest_phone        VARCHAR(50)    NULL AFTER dest_email,
  ADD COLUMN IF NOT EXISTS dest_instructions VARCHAR(500)   NULL AFTER dest_phone;
