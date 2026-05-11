<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$user_name  = $_SESSION['user_name'] ?? 'User';
$first_name = explode(' ', trim($user_name))[0];
$initials   = strtoupper(substr($first_name, 0, 1));

/* =========================
   ATTENDANCE HISTORY
   ========================= */
$stmt = $conn->prepare("
    SELECT 
        e.event_name,
        e.event_date,
        e.location,
        COALESCE(a.status, 'absent') AS status,
        a.attendance_date,
        r.registration_date
    FROM registrations r
    JOIN events e ON e.id = r.event_id
    LEFT JOIN attendance a ON a.event_id = e.id AND a.user_id = ?
    WHERE r.user_id = ?
    ORDER BY e.event_date DESC
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$history = $stmt->get_result();
$rows = [];
while ($row = $history->fetch_assoc()) $rows[] = $row;

$total_events = count($rows);
$total_present = count(array_filter($rows, fn($r) => $r['status'] === 'present'));
$attendance_rate = $total_events > 0 ? round(($total_present / $total_events) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Attendance</title>
<link rel="stylesheet" href="user_dashboard.css">
<style>
.stat-row { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:20px; }
.stat-box { background:#fff; border:1px solid #c8e3f3; border-radius:10px; padding:16px 18px; text-align:center; }
.stat-box .num { font-size:28px; font-weight:700; color:#1a3a5c; }
.stat-box .lbl { font-size:11px; font-weight:600; color:#7aafc8; text-transform:uppercase; margin-top:4px; }
.pill-present { background:#d5f5e3; color:#1a7a44; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; }
.pill-absent  { background:#fde8e8; color:#9b1c1c;  padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; }
.progress-bar { height:8px; background:#eaf3fb; border-radius:4px; overflow:hidden; margin-top:4px; }
.progress-fill { height:100%; background:linear-gradient(90deg,#2980b9,#87ceef); border-radius:4px; }
.empty-state { text-align:center; padding:40px; color:#aaa; }
table th { font-size:11px; }
</style>
</head>
<body>

<div class="sidebar">
    <h2>MENU</h2>
    <a href="user_dashboard.php">Dashboard</a>
    <a href="user_events.php">My Events</a>
    <a href="user_payments.php">Payments</a>
    <a href="user_attendance.php" class="active">Attendance</a>
    <a href="user_notifications.php">Notifications</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="main">
<div class="header">
    <h1>My Attendance</h1>
    <div class="header-right">
        <div class="avatar"><?= $initials ?></div>
    </div>
</div>
<div class="container">

<!-- SUMMARY STATS -->
<div class="stat-row">
    <div class="stat-box">
        <div class="num"><?= $total_events ?></div>
        <div class="lbl">Events Registered</div>
    </div>
    <div class="stat-box">
        <div class="num" style="color:#27ae60;"><?= $total_present ?></div>
        <div class="lbl">Times Present</div>
    </div>
    <div class="stat-box">
        <div class="num"><?= $attendance_rate ?>%</div>
        <div class="lbl">Attendance Rate</div>
        <div class="progress-bar" style="margin-top:8px;">
            <div class="progress-fill" style="width:<?= $attendance_rate ?>%;"></div>
        </div>
    </div>
</div>

<!-- ATTENDANCE HISTORY TABLE -->
<div class="section" style="background:#fff;border:1px solid #c8e3f3;border-radius:10px;padding:20px;">
    <h2 style="font-size:14px;font-weight:600;color:#1a3a5c;margin-bottom:14px;">Attendance History</h2>

    <?php if (!empty($rows)): ?>
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <tr>
            <th style="text-align:left;padding:0 10px 10px;color:#7aafc8;font-size:11px;font-weight:600;text-transform:uppercase;border-bottom:2px solid #daeef8;">Event</th>
            <th style="text-align:left;padding:0 10px 10px;color:#7aafc8;font-size:11px;font-weight:600;text-transform:uppercase;border-bottom:2px solid #daeef8;">Event Date</th>
            <th style="text-align:left;padding:0 10px 10px;color:#7aafc8;font-size:11px;font-weight:600;text-transform:uppercase;border-bottom:2px solid #daeef8;">Location</th>
            <th style="text-align:left;padding:0 10px 10px;color:#7aafc8;font-size:11px;font-weight:600;text-transform:uppercase;border-bottom:2px solid #daeef8;">Date Attended</th>
            <th style="text-align:left;padding:0 10px 10px;color:#7aafc8;font-size:11px;font-weight:600;text-transform:uppercase;border-bottom:2px solid #daeef8;">Status</th>
        </tr>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td style="padding:11px 10px;border-bottom:1px solid #eaf3fb;color:#334;">
                <strong><?= htmlspecialchars($r['event_name']) ?></strong>
            </td>
            <td style="padding:11px 10px;border-bottom:1px solid #eaf3fb;color:#334;">
                <?= date('M d, Y', strtotime($r['event_date'])) ?>
            </td>
            <td style="padding:11px 10px;border-bottom:1px solid #eaf3fb;color:#334;">
                <?= htmlspecialchars($r['location'] ?? '—') ?>
            </td>
            <td style="padding:11px 10px;border-bottom:1px solid #eaf3fb;color:#334;">
                <?= $r['attendance_date'] ? date('M d, Y', strtotime($r['attendance_date'])) : '—' ?>
            </td>
            <td style="padding:11px 10px;border-bottom:1px solid #eaf3fb;">
                <?php if ($r['status'] === 'present'): ?>
                    <span class="pill-present">Present</span>
                <?php else: ?>
                    <span class="pill-absent">Absent</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <div class="empty-state">
        <p style="font-size:14px;margin-bottom:10px;color:#7aafc8;">No records</p>
        <p>No attendance records yet.</p>
        <p style="font-size:12px;color:#bbb;margin-top:6px;">Register for an event to get started.</p>
    </div>
    <?php endif; ?>
</div>

</div>
</div>
</body>
</html>
