<?php
require_once 'database.php';

// Add event_end_date column
$conn->query("ALTER TABLE `events` ADD COLUMN `event_end_date` datetime DEFAULT NULL AFTER `event_date`");
echo "Column added: " . ($conn->error ?: "OK") . "\n";

// Set existing events' end date
$conn->query("UPDATE `events` SET `event_end_date` = DATE_ADD(event_date, INTERVAL 4 HOUR) WHERE `event_end_date` IS NULL");
echo "Existing events updated: " . $conn->affected_rows . " rows\n";

// Also fix blank user names while we're at it
$conn->query("UPDATE users SET name = CONCAT(first_name, IF(middle_initial IS NOT NULL AND middle_initial != '', CONCAT(' ', middle_initial, '.'), ''), ' ', surname) WHERE (name IS NULL OR name = '') AND first_name != ''");
echo "User names fixed: " . $conn->affected_rows . " rows\n";

echo "Done!\n";
?>
