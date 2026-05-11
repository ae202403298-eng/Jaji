<?php
session_start();
require_once __DIR__ . '/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user_id = $_SESSION['user_id'];
    $event_id = $_POST['event_id'] ?? null;

    if (!$event_id) {
        die("Invalid event.");
    }

    // OPTIONAL: Prevent duplicate registration
    $check = $conn->prepare("SELECT id FROM registrations WHERE user_id = ? AND event_id = ?");
    $check->bind_param("ii", $user_id, $event_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        // Already registered
        header("Location: user_dashboard.php");
        exit();
    }

    // Insert registration
    $stmt = $conn->prepare("INSERT INTO registrations (user_id, event_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $event_id);

    if ($stmt->execute()) {
        header("Location: user_dashboard.php");
        exit();
    } else {
        echo "Error registering: " . $conn->error;
    }

    $stmt->close();
}
?>