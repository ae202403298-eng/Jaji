<?php
session_start();
require_once __DIR__ . '/database.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

// Get full name from session
$user_name = $_SESSION['user_name'] ?? 'User';

// Extract FIRST NAME only
$first_name = explode(' ', trim($user_name))[0];
$initials = strtoupper(substr($first_name, 0, 1));

$user_id = $_SESSION['user_id'];

// Get all events
$events = $conn->query("SELECT * FROM events ORDER BY event_date ASC");

// Get registered IDs
$registered_ids = [];
$reg_query = $conn->query("SELECT event_id FROM registrations WHERE user_id = '$user_id'");
while ($row = $reg_query->fetch_assoc()) {
    $registered_ids[] = $row['event_id'];
}

// Registered events
$registered_events = $conn->query("
    SELECT e.* FROM registrations r
    JOIN events e ON r.event_id = e.id
    WHERE r.user_id = '$user_id'
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Dashboard</title>
<link rel="stylesheet" href="user_dashboard.css">
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>MENU</h2>
    <a href="user_dashboard.php" class="active">Dashboard</a>
    <a href="user_events.php">My Events</a>
    <a href="user_payments.php">Payments</a>
    <a href="user_attendance.php">Attendance</a>
    <a href="user_notifications.php">Notifications</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<!-- MAIN -->
<div class="main">

    <!-- HEADER -->
    <div class="header">
        <h1>Welcome, <?php echo htmlspecialchars($first_name); ?>!</h1>
        <div class="header-right">
            <div class="avatar"><?php echo $initials; ?></div>
        </div>
    </div>

    <div class="container">

        <!-- AVAILABLE EVENTS -->
        <div class="section">
            <h2>Available Events</h2>

            <div class="cards-grid">
            <?php while($event = $events->fetch_assoc()) { ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($event['event_name']); ?></h3>
                    <p>Date: <?php echo $event['event_date']; ?></p>
                    <p>Location: <?php echo htmlspecialchars($event['location']); ?></p>
                    <p>
                        <?php if (!empty($event['is_free'])): ?>
                            <strong style="color:#27ae60;">Free Event</strong>
                        <?php else: ?>
                            Fee: ₱<?php echo $event['fee']; ?>
                        <?php endif; ?>
                    </p>

                    <?php if (in_array($event['id'], $registered_ids)) { ?>
                        <button class="disabled" disabled>Registered</button>
                    <?php } else { ?>
                        <form method="POST" action="register_event.php">
                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                            <button type="submit">Register</button>
                        </form>
                    <?php } ?>
                </div>
            <?php } ?>
            </div>
        </div>

        <!-- MY REGISTERED EVENTS -->
        <div class="section">
            <h2>My Events</h2>

            <?php if ($registered_events->num_rows > 0) { ?>
                <div class="cards-grid">
                <?php while($event = $registered_events->fetch_assoc()) { ?>
                    <div class="card">
                        <h3><?php echo htmlspecialchars($event['event_name']); ?></h3>
                        <p>Date: <?php echo $event['event_date']; ?></p>
                        <p>Location: <?php echo htmlspecialchars($event['location']); ?></p>
                    </div>
                <?php } ?>
                </div>
            <?php } else { ?>
                <p>No registered events yet.</p>
            <?php } ?>
        </div>

    </div>
</div>

</body>
</html>