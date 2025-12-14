-- Migration: add employee email column & unique index
-- Purpose: ensure employee table has an email field for notifications/password resets
-- Safe re-run: uses conditional checks where supported; may need manual check on older MySQL.

-- 1. Add column if missing
ALTER TABLE employee
  ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL AFTER last_name;

-- 2. Add unique index (allows multiple NULLs; enforces uniqueness on non-NULL emails)
-- MySQL doesn't support IF NOT EXISTS for indexes prior to 8.0, so this may error if already present.
-- To check manually before applying:
--   SHOW INDEX FROM employee WHERE Key_name='uniq_employee_email';
ALTER TABLE employee ADD UNIQUE KEY uniq_employee_email (email);

-- 3. Optional future hardening (commented out):
-- ALTER TABLE employee MODIFY email VARCHAR(255) NOT NULL;
-- Add only after all rows have valid emails.

-- Rollback (manual):
-- ALTER TABLE employee DROP INDEX uniq_employee_email;
-- ALTER TABLE employee DROP COLUMN email;
