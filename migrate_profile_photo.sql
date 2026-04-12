-- ============================================================
--  Herald — Profile Photo Migration
--  Run this ONCE in phpMyAdmin or MySQL CLI
--  before using the Settings > Upload Photo feature.
-- ============================================================

USE smart_edu;

-- Add profile_photo column (safe: only adds if not already present)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) NULL
    AFTER status;

-- Optional: verify
-- SELECT id, institutional_id, profile_photo FROM users LIMIT 5;
