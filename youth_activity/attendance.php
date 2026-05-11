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

// MARK / UPDATE ATTENDANCE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $event_id = (int)$_POST['event_id'];
    $attendance_date = $_POST['attendance_date'];
    $statuses = $_POST['status'] ?? [];

    // VALIDATE: only allow attendance on the event date
    $ev_check = $conn->query("SELECT DATE(event_date) AS edate FROM events WHERE id = $event_id")->fetch_assoc();
    if ($ev_check && $ev_check['edate'] !== date('Y-m-d')) {
        $error = "Attendance can only be marked on the event date (" . date('M d, Y', strtotime($ev_check['edate'])) . ").";
    } else {
        // Get all registrants for this event
        $reg_result = $conn->query("SELECT user_id FROM registrations WHERE event_id = $event_id");
        while ($reg = $reg_result->fetch_assoc()) {
            $uid = $reg['user_id'];
            $status = isset($statuses[$uid]) ? 'present' : 'absent';

            // Upsert attendance
            $chk = $conn->query("SELECT id FROM attendance WHERE user_id=$uid AND event_id=$event_id AND attendance_date='$attendance_date'");
            if ($chk->num_rows > 0) {
                $conn->query("UPDATE attendance SET status='$status' WHERE user_id=$uid AND event_id=$event_id AND attendance_date='$attendance_date'");
            } else {
                $stmt = $conn->prepare("INSERT INTO attendance (user_id, event_id, status, attendance_date) VALUES (?,?,?,?)");
                $stmt->bind_param("iiss", $uid, $event_id, $status, $attendance_date);
                $stmt->execute();
            }
        }
        $success = "Attendance saved successfully!";
    }
}

// FETCH EVENTS FOR DROPDOWN
$event_list = $conn->query("SELECT id, event_name, event_date FROM events ORDER BY event_date DESC");

// SELECTED EVENT & FILTER
$selected_event = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$filter_status = $_GET['filter_status'] ?? '';
$search = trim($_GET['search'] ?? '');

// Auto-set attendance_date to event date (not user-selectable)
$attendance_date = date('Y-m-d');
$is_event_today = false;
if ($selected_event > 0) {
    $ev_date_check = $conn->query("SELECT DATE(event_date) AS edate FROM events WHERE id = $selected_event")->fetch_assoc();
    if ($ev_date_check) {
        $attendance_date = $ev_date_check['edate'];
        $is_event_today = ($ev_date_check['edate'] === date('Y-m-d'));
    }
}

// ATTENDANCE TABLE: CTE + multiple joins + aggregation
$attendance_data = null;
$event_info = null;
if ($selected_event > 0) {
    $event_info = $conn->query("SELECT * FROM events WHERE id = $selected_event")->fetch_assoc();

    $filter_where = '';
    $having = '';
    if ($filter_status === 'present') $filter_where = " AND COALESCE(a.status, 'absent') = 'present'";
    if ($filter_status === 'absent') $filter_where = " AND (a.id IS NULL OR a.status = 'absent')";
    if ($search !== '') $filter_where .= " AND u.name LIKE '%".addslashes($search)."%'";

    $query = "
        WITH attendance_summary AS (
            SELECT 
                user_id,
                event_id,
                COUNT(CASE WHEN status = 'present' THEN 1 END) AS times_present,
                MAX(attendance_date) AS last_attended
            FROM attendance
            GROUP BY user_id, event_id
        )
        SELECT 
            u.id AS user_id,
            u.name,
            u.email,
            COALESCE(a.status, 'absent') AS status,
            COALESCE(a.attendance_date, '$attendance_date') AS attendance_date,
            COALESCE(ats.times_present, 0) AS times_present,
            (SELECT COUNT(*) FROM events) AS total_events,
            (SELECT COUNT(*) FROM attendance WHERE user_id = u.id AND status = 'present') AS total_present_all
        FROM registrations r
        JOIN users u ON u.id = r.user_id
        LEFT JOIN attendance a ON a.user_id = r.user_id AND a.event_id = r.event_id AND a.attendance_date = '$attendance_date'
        LEFT JOIN attendance_summary ats ON ats.user_id = r.user_id AND ats.event_id = r.event_id
        WHERE r.event_id = $selected_event $filter_where
        ORDER BY u.name ASC
    ";
    $attendance_data = $conn->query($query);
}

// STATS
$stats = null;
if ($selected_event > 0) {
    $stats = $conn->query("
        SELECT 
            COUNT(DISTINCT r.user_id) AS total_reg,
            COUNT(DISTINCT CASE WHEN a.status='present' THEN a.user_id END) AS total_present,
            COUNT(DISTINCT CASE WHEN a.status='absent' OR a.id IS NULL THEN r.user_id END) AS total_absent,
            ROUND(COUNT(DISTINCT CASE WHEN a.status='present' THEN a.user_id END) / COUNT(DISTINCT r.user_id) * 100, 1) AS pct
        FROM registrations r
        LEFT JOIN attendance a ON a.user_id = r.user_id AND a.event_id = r.event_id AND a.attendance_date = '$attendance_date'
        WHERE r.event_id = $selected_event
    ")->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Attendance – Admin</title>
<link rel="stylesheet" href="admin_dashboard.css">
<style>
.form-group { margin-bottom:14px; }
.form-group label { display:block;font-size:12px;font-weight:600;color:#1a3a5c;margin-bottom:5px;text-transform:uppercase; }
.form-group input, .form-group select { width:100%;padding:9px 12px;border:1px solid #c8e3f3;border-radius:7px;font-size:13px;color:#334;background:#f5f9fd;outline:none; }
.form-group input:focus, .form-group select:focus { border-color:#2980b9;background:#fff; }
.stat-box { background:#f5f9fd;border:1px solid #c8e3f3;border-radius:8px;padding:12px 16px;text-align:center; }
.stat-box .num { font-size:24px;font-weight:700;color:#1a3a5c; }
.stat-box .lbl { font-size:11px;color:#7aafc8;font-weight:600;text-transform:uppercase; }
.stats-row { display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px; }
.alert { padding:10px 14px;border-radius:7px;margin-bottom:16px;font-size:13px; }
.alert-success { background:#edfaf3;border:1px solid #a8e6c4;color:#1a7a44; }
.alert-error { background:#fff0f0;border:1px solid #f5c6c6;color:#c0392b; }
.toggle-check { width:18px;height:18px;cursor:pointer; }
.badge-present { background:#d5f5e3;color:#1a7a44;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600; }
.badge-absent { background:#fdecea;color:#c0392b;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600; }
.alert-warning { background:#fef3cd;border:1px solid #f5d78a;color:#7d5a00;padding:14px 18px;border-radius:8px;margin-bottom:16px;font-size:13px;line-height:1.5; }
</style>
</head>
<body>
<div class="sidebar">
    <h2>ADMIN</h2>
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="events.php">Events</a>
    <a href="attendance.php" class="active">Attendance</a>
    <a href="payments.php">Payments</a>
    <a href="reports.php">Reports</a>
    <a href="notifications.php">Notifications</a>
    <a href="logout.php" class="logout">Logout</a>
</div>
<div class="main">
    <div class="header">
        <h1>Attendance Tracking</h1>
        <div class="header-right">
            <span>Welcome, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
            <div class="avatar">A</div>
        </div>
    </div>
    <div class="container">
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

        <!-- EVENT SELECTOR -->
        <div class="section" style="margin-bottom:16px;">
            <h2>Select Event</h2>
            <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                <div class="form-group" style="flex:2;min-width:200px;margin-bottom:0;">
                    <label>Event</label>
                    <select name="event_id" onchange="this.form.submit()">
                        <option value="">-- Select Event --</option>
                        <?php
                        $event_list->data_seek(0);
                        while ($ev = $event_list->fetch_assoc()):
                        ?>
                        <option value="<?= $ev['id'] ?>" <?= $selected_event == $ev['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ev['event_name']) ?> (<?= date('M d, Y', strtotime($ev['event_date'])) ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group" style="flex:1;min-width:160px;margin-bottom:0;">
                    <label>Event Date</label>
                    <input type="date" name="attendance_date" value="<?= $attendance_date ?>" readonly disabled style="opacity:0.6;cursor:not-allowed;">
                </div>
                <div class="form-group" style="flex:1;min-width:140px;margin-bottom:0;">
                    <label>Status Filter</label>
                    <select name="filter_status">
                        <option value="">All</option>
                        <option value="present" <?= $filter_status == 'present' ? 'selected' : '' ?>>Present</option>
                        <option value="absent" <?= $filter_status == 'absent' ? 'selected' : '' ?>>Absent</option>
                    </select>
                </div>
                <div class="form-group" style="flex:1;min-width:160px;margin-bottom:0;">
                    <label>Search Name</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search...">
                </div>
                <button type="submit">Filter</button>
            </form>
        </div>

        <?php if ($selected_event > 0 && $event_info): ?>

        <!-- STATS -->
        <?php if ($stats): ?>
        <div class="stats-row">
            <div class="stat-box"><div class="num"><?= $stats['total_reg'] ?></div><div class="lbl">Registered</div></div>
            <div class="stat-box"><div class="num" style="color:#27ae60;"><?= $stats['total_present'] ?></div><div class="lbl">Present</div></div>
            <div class="stat-box"><div class="num" style="color:#e74c3c;"><?= $stats['total_absent'] ?></div><div class="lbl">Absent</div></div>
            <div class="stat-box"><div class="num" style="color:#2980b9;"><?= $stats['pct'] ?? 0 ?>%</div><div class="lbl">Attendance Rate</div></div>
        </div>
        <?php endif; ?>

        <!-- ATTENDANCE FORM -->
        <div class="section">
            <div class="flex">
                <h2><?= htmlspecialchars($event_info['event_name']) ?> — <?= date('M d, Y', strtotime($attendance_date)) ?></h2>

                <?php if ($is_event_today): ?>
                <form method="POST" id="attendanceForm">
                    <input type="hidden" name="event_id" value="<?= $selected_event ?>">
                    <input type="hidden" name="attendance_date" value="<?= $attendance_date ?>">
                    <div style="display:flex;gap:8px;">
                        <button type="button" onclick="markAll(true)" style="background:#27ae60;padding:7px 14px;font-size:12px;">All Present</button>
                        <button type="button" onclick="markAll(false)" style="background:#e74c3c;padding:7px 14px;font-size:12px;">All Absent</button>
                        <button type="submit" name="save_attendance" style="padding:7px 14px;font-size:12px;">Save</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>

            <?php if (!$is_event_today): ?>
            <div class="alert-warning">
                Attendance is only available on the event date (<strong><?= date('M d, Y', strtotime($attendance_date)) ?></strong>). You can view records but cannot mark or change attendance today.
            </div>
            <?php endif; ?>

            <table>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Times Present (this event)</th>
                    <th>Total Events Attended</th>
                    <th style="text-align:center;">Mark Present</th>
                    <th>Status</th>
                </tr>
                <?php if ($attendance_data && $attendance_data->num_rows > 0): ?>
                <?php $i = 1; while ($row = $attendance_data->fetch_assoc()): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= $row['times_present'] ?></td>
                    <td><?= $row['total_present_all'] ?></td>
                    <td style="text-align:center;">
                        <input <?= $is_event_today ? 'form="attendanceForm"' : 'disabled' ?> type="checkbox" class="toggle-check att-check"
                            name="status[<?= $row['user_id'] ?>]"
                            value="present"
                            <?= $row['status'] === 'present' ? 'checked' : '' ?>
                            <?= !$is_event_today ? 'style="opacity:0.4;cursor:not-allowed;"' : '' ?>>
                    </td>
                    <td>
                        <?php if ($row['status'] === 'present'): ?>
                            <span class="badge-present">Present</span>
                        <?php else: ?>
                            <span class="badge-absent">Absent</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php else: ?>
                <tr><td colspan="7" style="text-align:center;color:#aaa;padding:20px;">No participants registered for this event.</td></tr>
                <?php endif; ?>
            </table>
        </div>

        <?php else: ?>
        <div class="section">
            <p style="text-align:center;color:#aaa;padding:30px;">Select an event above to manage attendance.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
function markAll(present) {
    document.querySelectorAll('.att-check').forEach(cb => cb.checked = present);
}
</script>
</body>
</html>