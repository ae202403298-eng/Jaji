-- Add event_end_date column to events table
-- event_date = start date/time, event_end_date = end date/time
ALTER TABLE `events` ADD COLUMN `event_end_date` datetime DEFAULT NULL AFTER `event_date`;

-- Set existing events' end date to same as start date (same day, 5pm default)
UPDATE `events` SET `event_end_date` = DATE_ADD(event_date, INTERVAL 4 HOUR) WHERE `event_end_date` IS NULL;
