-- Migration: add email column to admin table
-- Adds nullable email and unique index if not already present.

ALTER TABLE admin
  ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL AFTER username;

-- MySQL prior to 8.0 does not support IF NOT EXISTS for columns; for compatibility you may need to run:
--   ALTER TABLE admin ADD COLUMN email VARCHAR(255) NULL AFTER username;
-- manually if the above fails.

-- Create index (unique) if not existing (MySQL lacks direct IF NOT EXISTS for indexes)
-- Check first: SHOW INDEX FROM admin WHERE Key_name='uniq_admin_email'; then create if absent.
-- Provided here for convenience (may error if already exists):
ALTER TABLE admin ADD UNIQUE KEY uniq_admin_email (email);
