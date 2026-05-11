-- ============================================================
-- Migration: Add is_free flag to events table
-- Run this once on your database before deploying the updated files
-- ============================================================

-- 1. Add the is_free column (defaults to 0 = paid event)
ALTER TABLE events
    ADD COLUMN is_free TINYINT(1) NOT NULL DEFAULT 0
    AFTER fee;

-- 2. Backfill: treat any existing events with fee = 0 as free
UPDATE events SET is_free = 1 WHERE fee = 0;

-- 3. (Optional) Verify the result
-- SELECT id, event_name, fee, is_free FROM events;
