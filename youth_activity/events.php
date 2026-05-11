<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}

$success = '';
$error = '';

// DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) $success = "Event deleted successfully.";
    else $error = "Failed to delete event.";
    $stmt->close();
}

// ADD / EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_name  = trim($_POST['event_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date  = $_POST['event_date'] ?? '';
    $event_end_date = $_POST['event_end_date'] ?? '';
    $location    = trim($_POST['location'] ?? '');
    $is_free     = isset($_POST['is_free']) ? 1 : 0;
    $fee         = $is_free ? 0.00 : floatval($_POST['fee'] ?? 0);

    if ($event_name === '' || $event_date === '' || $event_end_date === '') {
        $error = "Event name, start date, and end date are required.";
    } else {
        if (!empty($_POST['edit_id'])) {
            $id = (int)$_POST['edit_id'];
            $stmt = $conn->prepare("UPDATE events SET event_name=?, description=?, event_date=?, event_end_date=?, location=?, fee=?, is_free=? WHERE id=?");
            $stmt->bind_param("sssssdii", $event_name, $description, $event_date, $event_end_date, $location, $fee, $is_free, $id);
            if ($stmt->execute()) $success = "Event updated successfully.";
            else $error = "Failed to update event.";
        } else {
            $stmt = $conn->prepare("INSERT INTO events (event_name, description, event_date, event_end_date, location, fee, is_free) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("sssssdi", $event_name, $description, $event_date, $event_end_date, $location, $fee, $is_free);
            if ($stmt->execute()) $success = "Event created successfully.";
            else $error = "Failed to create event.";
        }
        $stmt->close();
    }
}

// FETCH EDIT DATA
$edit_event = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM events WHERE id = $id");
    $edit_event = $res->fetch_assoc();
}

// SEARCH & FILTER
$search = trim($_GET['search'] ?? '');
$filter_date = $_GET['filter_date'] ?? '';
$where = "WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $where .= " AND event_name LIKE ?";
    $params[] = "%$search%";
    $types .= 's';
}
if ($filter_date !== '') {
    $where .= " AND DATE(event_date) = ?";
    $params[] = $filter_date;
    $types .= 's';
}

// WITH CTE for stats
$stats_query = "
    WITH event_stats AS (
        SELECT 
            e.id,
            COUNT(DISTINCT r.id) AS total_registered,
            COUNT(DISTINCT a.id) AS total_attended,
            COALESCE(SUM(p.amount_paid), 0) AS total_collected,
            e.fee * COUNT(DISTINCT r.id) AS total_expected
        FROM events e
        LEFT JOIN registrations r ON r.event_id = e.id
        LEFT JOIN attendance a ON a.event_id = e.id AND a.status = 'present'
        LEFT JOIN payments p ON p.event_id = e.id
        GROUP BY e.id
    )
    SELECT e.*, 
           COALESCE(es.total_registered, 0) AS total_registered,
           COALESCE(es.total_attended, 0) AS total_attended,
           COALESCE(es.total_collected, 0) AS total_collected,
           COALESCE(es.total_expected, 0) AS total_expected
    FROM events e
    LEFT JOIN event_stats es ON es.id = e.id
    $where
    ORDER BY e.event_date DESC
";

if (!empty($params)) {
    $stmt = $conn->prepare($stats_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $events = $stmt->get_result();
} else {
    $events = $conn->query($stats_query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Events – Admin</title>
<link rel="stylesheet" href="admin_dashboard.css">
<style>
.form-card { background:#fff; border:1px solid #c8e3f3; border-radius:10px; padding:20px 24px; margin-bottom:20px; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.form-group { margin-bottom:14px; }
.form-group label { display:block; font-size:12px; font-weight:600; color:#1a3a5c; margin-bottom:5px; text-transform:uppercase; }
.form-group input, .form-group textarea, .form-group select {
    width:100%; padding:9px 12px; border:1px solid #c8e3f3; border-radius:7px;
    font-size:13px; color:#334; background:#f5f9fd; outline:none;
}
.form-group input:focus, .form-group textarea:focus { border-color:#2980b9; background:#fff; }
.form-group textarea { resize:vertical; min-height:70px; }
.btn-success { background:#27ae60; }
.btn-success:hover { background:#1e8449; }
.btn-danger { background:#e74c3c; padding:5px 10px; font-size:12px; }
.btn-danger:hover { background:#c0392b; }
.btn-edit { background:#f39c12; padding:5px 10px; font-size:12px; }
.btn-edit:hover { background:#d68910; }
.alert { padding:10px 14px; border-radius:7px; margin-bottom:16px; font-size:13px; }
.alert-success { background:#edfaf3; border:1px solid #a8e6c4; color:#1a7a44; }
.alert-error { background:#fff0f0; border:1px solid #f5c6c6; color:#c0392b; }
.badge { display:inline-block; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600; }
.badge-blue { background:#daeef8; color:#1e5080; }
.badge-green { background:#d5f5e3; color:#1a7a44; }
</style>
</head>
<body>
<div class="sidebar">
    <h2>ADMIN</h2>
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="events.php" class="active">Events</a>
    <a href="attendance.php">Attendance</a>
    <a href="payments.php">Payments</a>
    <a href="reports.php">Reports</a>
    <a href="notifications.php">Notifications</a>
    <a href="logout.php" class="logout">Logout</a>
</div>
<div class="main">
    <div class="header">
        <h1><?= $edit_event ? 'Edit Event' : 'Events Management' ?></h1>
        <div class="header-right">
            <span>Welcome, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
            <div class="avatar">A</div>
        </div>
    </div>
    <div class="container">

        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

        <!-- ADD / EDIT FORM -->
        <div class="form-card">
            <h2 style="font-size:14px;font-weight:600;color:#1a3a5c;margin-bottom:16px;">
                <?= $edit_event ? 'Edit Event' : 'Add New Event' ?>
            </h2>
            <form method="POST">
                <?php if ($edit_event): ?>
                    <input type="hidden" name="edit_id" value="<?= $edit_event['id'] ?>">
                <?php endif; ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Event Name *</label>
                        <input type="text" name="event_name" value="<?= htmlspecialchars($edit_event['event_name'] ?? '') ?>" required placeholder="e.g. Youth Camp 2026">
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" value="<?= htmlspecialchars($edit_event['location'] ?? '') ?>" placeholder="e.g. City Hall">
                    </div>
                    <div class="form-group">
                        <label>Start Date & Time *</label>
                        <input type="datetime-local" name="event_date" value="<?= $edit_event ? date('Y-m-d\TH:i', strtotime($edit_event['event_date'])) : '' ?>" required>
                    </div>
                    <div class="form-group">
                        <label>End Date & Time *</label>
                        <input type="datetime-local" name="event_end_date" value="<?= $edit_event && $edit_event['event_end_date'] ? date('Y-m-d\TH:i', strtotime($edit_event['event_end_date'])) : '' ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Event Fee (₱)</label>
                        <input type="number" name="fee" id="fee_input" value="<?= $edit_event['fee'] ?? 0 ?>" min="0" step="0.01" placeholder="0.00"
                               <?= (!empty($edit_event['is_free'])) ? 'disabled style="opacity:0.4;"' : '' ?>>
                    </div>
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                    <input type="checkbox" name="is_free" id="is_free_check" value="1"
                           <?= (!empty($edit_event['is_free'])) ? 'checked' : '' ?>
                           onchange="document.getElementById('fee_input').disabled=this.checked;
                                     document.getElementById('fee_input').style.opacity=this.checked?'0.4':'1';
                                     if(this.checked) document.getElementById('fee_input').value='0';"
                           style="width:16px;height:16px;accent-color:#27ae60;cursor:pointer;">
                    <label for="is_free_check" style="font-size:13px;font-weight:600;color:#1a3a5c;cursor:pointer;text-transform:none;margin:0;">
                        This is a <strong>FREE</strong> event (no payment required)
                    </label>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Event details..."><?= htmlspecialchars($edit_event['description'] ?? '') ?></textarea>
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="submit" class="btn-success"><?= $edit_event ? 'Update Event' : 'Create Event' ?></button>
                    <?php if ($edit_event): ?><a href="events.php"><button type="button">Cancel</button></a><?php endif; ?>
                </div>
            </form>
        </div>

        <!-- SEARCH & FILTER -->
        <div class="section">
            <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search event name..." style="padding:8px 12px;border:1px solid #c8e3f3;border-radius:7px;font-size:13px;flex:1;min-width:180px;">
                <input type="date" name="filter_date" value="<?= $filter_date ?>" style="padding:8px 12px;border:1px solid #c8e3f3;border-radius:7px;font-size:13px;">
                <button type="submit">Search</button>
                <a href="events.php"><button type="button" style="background:#95a5a6;">Clear</button></a>
            </form>

            <div class="flex">
                <h2>All Events</h2>
            </div>
            <table>
                <tr>
                    <th>#</th>
                    <th>Event Name</th>
                    <th>Date</th>
                    <th>Location</th>
                    <th>Fee</th>
                    <th>Registered</th>
                    <th>Attended</th>
                    <th>Collected</th>
                    <th>Actions</th>
                </tr>
                <?php if ($events && $events->num_rows > 0): ?>
                <?php $i = 1; while ($ev = $events->fetch_assoc()): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td>
                        <strong><?= htmlspecialchars($ev['event_name']) ?></strong>
                        <?php if (!empty($ev['is_free'])): ?>
                            <span class="badge" style="background:#d5f5e3;color:#1a7a44;margin-left:6px;">FREE</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('M d, Y h:i A', strtotime($ev['event_date'])) ?><br><small style="color:#7aafc8;">to <?= $ev['event_end_date'] ? date('M d, Y h:i A', strtotime($ev['event_end_date'])) : '—' ?></small></td>
                    <td><?= htmlspecialchars($ev['location'] ?? '—') ?></td>
                    <td>
                        <?php if (!empty($ev['is_free'])): ?>
                            <span style="color:#27ae60;font-weight:600;">Free</span>
                        <?php else: ?>
                            ₱<?= number_format($ev['fee'], 2) ?>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-blue"><?= $ev['total_registered'] ?></span></td>
                    <td><span class="badge badge-green"><?= $ev['total_attended'] ?></span></td>
                    <td>
                        <?php if (!empty($ev['is_free'])): ?>
                            <span style="color:#aaa;font-size:12px;">N/A</span>
                        <?php else: ?>
                            ₱<?= number_format($ev['total_collected'], 2) ?>
                        <?php endif; ?>
                    </td>
                    <td style="display:flex;gap:6px;flex-wrap:wrap;">
                        <a href="events.php?edit=<?= $ev['id'] ?>"><button class="btn-edit">Edit</button></a>
                        <a href="attendance.php?event_id=<?= $ev['id'] ?>"><button style="background:#8e44ad;padding:5px 10px;font-size:12px;">Attendance</button></a>
                        <?php if (empty($ev['is_free'])): ?>
                        <a href="payments.php?event_id=<?= $ev['id'] ?>"><button style="background:#27ae60;padding:5px 10px;font-size:12px;">Payments</button></a>
                        <?php endif; ?>
                        <a href="events.php?delete=<?= $ev['id'] ?>" onclick="return confirm('Delete this event?')"><button class="btn-danger">Delete</button></a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php else: ?>
                <tr><td colspan="9" style="text-align:center;color:#aaa;padding:20px;">No events found.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
</body>
</html>