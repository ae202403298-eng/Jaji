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

// Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: events.php");
    exit();
}
$event_id = (int)$_GET['id'];

// HANDLE UPDATE
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
        $stmt = $conn->prepare("UPDATE events SET event_name=?, description=?, event_date=?, event_end_date=?, location=?, fee=?, is_free=? WHERE id=?");
        $stmt->bind_param("sssssdii", $event_name, $description, $event_date, $event_end_date, $location, $fee, $is_free, $event_id);
        if ($stmt->execute()) {
            $success = "Event updated successfully!";
        } else {
            $error = "Failed to update: " . $conn->error;
        }
        $stmt->close();
    }
}

// LOAD CURRENT DATA
$res = $conn->prepare("SELECT * FROM events WHERE id = ?");
$res->bind_param("i", $event_id);
$res->execute();
$ev = $res->get_result()->fetch_assoc();

if (!$ev) {
    header("Location: events.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Event – Admin</title>
<link rel="stylesheet" href="admin_dashboard.css">
<style>
.form-card { background:#fff; border:1px solid #c8e3f3; border-radius:10px; padding:24px 28px; max-width:700px; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.form-group { margin-bottom:14px; }
.form-group label { display:block; font-size:12px; font-weight:600; color:#1a3a5c; margin-bottom:5px; text-transform:uppercase; }
.form-group input, .form-group textarea, .form-group select {
    width:100%; padding:9px 12px; border:1px solid #c8e3f3; border-radius:7px;
    font-size:13px; color:#334; background:#f5f9fd; outline:none;
}
.form-group input:focus, .form-group textarea:focus { border-color:#2980b9; background:#fff; }
.form-group textarea { resize:vertical; min-height:80px; }
.btn-success { background:#27ae60; }
.btn-success:hover { background:#1e8449; }
.btn-cancel { background:#95a5a6; }
.btn-cancel:hover { background:#7f8c8d; }
.alert { padding:10px 14px; border-radius:7px; margin-bottom:16px; font-size:13px; }
.alert-success { background:#edfaf3; border:1px solid #a8e6c4; color:#1a7a44; }
.alert-error   { background:#fff0f0; border:1px solid #f5c6c6; color:#c0392b; }
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
    <h1>Edit Event</h1>
    <div class="header-right">
        <span>Welcome, Admin</span>
        <div class="avatar">A</div>
    </div>
</div>
<div class="container">

<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

<div class="form-card">
    <h2 style="font-size:15px;color:#1a3a5c;margin-bottom:20px;">Editing: <?= htmlspecialchars($ev['event_name']) ?></h2>
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label>Event Name *</label>
                <input type="text" name="event_name" value="<?= htmlspecialchars($ev['event_name']) ?>" required>
            </div>
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" value="<?= htmlspecialchars($ev['location'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Start Date & Time *</label>
                <input type="datetime-local" name="event_date" value="<?= date('Y-m-d\TH:i', strtotime($ev['event_date'])) ?>" required>
            </div>
            <div class="form-group">
                <label>End Date & Time *</label>
                <input type="datetime-local" name="event_end_date" value="<?= $ev['event_end_date'] ? date('Y-m-d\TH:i', strtotime($ev['event_end_date'])) : '' ?>" required>
            </div>
            <div class="form-group">
                <label>Event Fee (₱)</label>
                <input type="number" name="fee" id="fee_input" value="<?= $ev['fee'] ?>" min="0" step="0.01"
                       <?= (!empty($ev['is_free'])) ? 'disabled style="opacity:0.4;"' : '' ?>>
            </div>
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
            <input type="checkbox" name="is_free" id="is_free_check" value="1"
                   <?= (!empty($ev['is_free'])) ? 'checked' : '' ?>
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
            <textarea name="description"><?= htmlspecialchars($ev['description'] ?? '') ?></textarea>
        </div>
        <div style="display:flex;gap:10px;">
            <button type="submit" class="btn-success">Save Changes</button>
            <a href="events.php"><button type="button" class="btn-cancel">Cancel</button></a>
            <a href="admin_dashboard.php"><button type="button" class="btn-cancel">Dashboard</button></a>
        </div>
    </form>
</div>

</div>
</div>
</body>
</html>
