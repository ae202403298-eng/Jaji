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
   MY REGISTERED EVENTS with payment status
   ========================= */
$stmt = $conn->prepare("
    SELECT 
        e.id,
        e.event_name,
        e.description,
        e.event_date,
        e.location,
        e.fee,
        e.is_free,
        r.registration_date,
        COALESCE(SUM(p.amount_paid), 0) AS total_paid,
        CASE WHEN e.is_free = 1 THEN 0 ELSE e.fee - COALESCE(SUM(p.amount_paid), 0) END AS balance,
        CASE
            WHEN e.is_free = 1 THEN 'Free'
            WHEN COALESCE(SUM(p.amount_paid), 0) = 0 THEN 'Unpaid'
            WHEN COALESCE(SUM(p.amount_paid), 0) < e.fee THEN 'Partial'
            ELSE 'Paid'
        END AS pay_status,
        CASE
            WHEN e.event_date > NOW() THEN 'Upcoming'
            WHEN DATE(e.event_date) = CURDATE() THEN 'Ongoing'
            ELSE 'Completed'
        END AS event_status,
        COALESCE(a.status, 'absent') AS attendance_status
    FROM registrations r
    JOIN events e ON e.id = r.event_id
    LEFT JOIN payments p ON p.event_id = e.id AND p.user_id = ? AND e.is_free = 0
    LEFT JOIN attendance a ON a.event_id = e.id AND a.user_id = ?
    WHERE r.user_id = ?
    GROUP BY e.id, r.registration_date, a.status
    ORDER BY e.event_date DESC
");
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$my_events = $stmt->get_result();
$event_rows = [];
while ($row = $my_events->fetch_assoc()) $event_rows[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Events</title>
<link rel="stylesheet" href="user_dashboard.css">
<style>
.event-card {
    background:#fff; border:1px solid #c8e3f3; border-radius:12px;
    padding:20px; margin-bottom:16px; transition:box-shadow 0.2s;
}
.event-card:hover { box-shadow:0 4px 16px rgba(41,128,185,0.10); }
.event-card h3 { color:#1a3a5c; font-size:15px; margin-bottom:10px; }
.event-meta { display:flex; gap:14px; flex-wrap:wrap; font-size:12.5px; color:#7aafc8; margin-bottom:10px; }
.event-meta span { display:flex; align-items:center; gap:4px; }
.pill { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
.pill-paid    { background:#d5f5e3; color:#1a7a44; }
.pill-partial { background:#fef3cd; color:#7d5a00; }
.pill-unpaid  { background:#fde8e8; color:#9b1c1c; }
.pill-free    { background:#e8f8f0; color:#1a7a44; border:1px solid #a3dab8; }
.pill-upcoming  { background:#daeef8; color:#1e5080; }
.pill-ongoing   { background:#d5f5e3; color:#1a7a44; }
.pill-completed { background:#e2e3e5; color:#555; }
.pill-present { background:#d5f5e3; color:#1a7a44; }
.pill-absent  { background:#fde8e8; color:#9b1c1c; }
.event-footer { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-top:12px; padding-top:12px; border-top:1px solid #eaf3fb; }
.progress-bar { height:6px; background:#eaf3fb; border-radius:3px; overflow:hidden; flex:1; min-width:80px; }
.progress-fill { height:100%; background:linear-gradient(90deg,#2980b9,#87ceef); border-radius:3px; }
.empty-state { text-align:center; padding:40px; color:#aaa; font-size:14px; }
.btn-pay { background:#27ae60; font-size:12px; padding:6px 14px; border:none; border-radius:7px; color:#fff; cursor:pointer; text-decoration:none; display:inline-block; }
.btn-pay:hover { background:#1e8449; }
</style>
</head>
<body>

<div class="sidebar">
    <h2>MENU</h2>
    <a href="user_dashboard.php">Dashboard</a>
    <a href="user_events.php" class="active">My Events</a>
    <a href="user_payments.php">Payments</a>
    <a href="user_attendance.php">Attendance</a>
    <a href="user_notifications.php">Notifications</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="main">
<div class="header">
    <h1>My Events</h1>
    <div class="header-right">
        <div class="avatar"><?= $initials ?></div>
    </div>
</div>
<div class="container">

<?php if (!empty($event_rows)): ?>

<?php foreach ($event_rows as $ev):
    $is_free  = !empty($ev['is_free']);
    $pay_pct  = $is_free ? 100 : ($ev['fee'] > 0 ? min(100, round(($ev['total_paid'] / $ev['fee']) * 100)) : 100);
    $pay_pill = ['Paid'=>'pill-paid','Partial'=>'pill-partial','Unpaid'=>'pill-unpaid','Free'=>'pill-free'];
    $ev_pill  = ['Upcoming'=>'pill-upcoming','Ongoing'=>'pill-ongoing','Completed'=>'pill-completed'];
    $att_pill = $ev['attendance_status'] === 'present' ? 'pill-present' : 'pill-absent';
?>
<div class="event-card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
        <h3><?= htmlspecialchars($ev['event_name']) ?></h3>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <span class="pill <?= $ev_pill[$ev['event_status']] ?? '' ?>"><?= $ev['event_status'] ?></span>
            <span class="pill <?= $pay_pill[$ev['pay_status']] ?? '' ?>">
                <?= $is_free ? 'Free' : $ev['pay_status'] ?>
            </span>
        </div>
    </div>

    <?php if ($ev['description']): ?>
    <p style="font-size:12.5px;color:#666;margin-bottom:10px;"><?= htmlspecialchars($ev['description']) ?></p>
    <?php endif; ?>

    <div class="event-meta">
        <span>Date: <?= date('M d, Y h:i A', strtotime($ev['event_date'])) ?></span>
        <span>Location: <?= htmlspecialchars($ev['location'] ?? '—') ?></span>
        <span>
            <?php if ($is_free): ?>
                <strong style="color:#27ae60;">Free Event</strong>
            <?php else: ?>
                Fee: ₱<?= number_format($ev['fee'], 2) ?>
            <?php endif; ?>
        </span>
        <span>Registered: <?= date('M d, Y', strtotime($ev['registration_date'])) ?></span>
    </div>

    <div class="event-footer">
        <?php if ($is_free): ?>
            <div style="font-size:12px;color:#27ae60;font-weight:600;">No payment required for this event.</div>
        <?php else: ?>
            <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:200px;">
                <span style="font-size:12px;color:#7aafc8;white-space:nowrap;">
                    Paid: ₱<?= number_format($ev['total_paid'], 2) ?> / ₱<?= number_format($ev['fee'], 2) ?>
                </span>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?= $pay_pct ?>%;"></div>
                </div>
                <span style="font-size:11px;color:#2980b9;font-weight:600;"><?= $pay_pct ?>%</span>
            </div>
        <?php endif; ?>
        <div style="display:flex;gap:8px;align-items:center;">
            <span style="font-size:12px;color:#555;">Attendance:</span>
            <span class="pill <?= $att_pill ?>"><?= ucfirst($ev['attendance_status']) ?></span>
            <?php if (!$is_free && $ev['pay_status'] !== 'Paid'): ?>
            <a href="user_payments.php" class="btn-pay">Pay Now</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php else: ?>
<div class="empty-state">
    <p style="font-size:14px;margin-bottom:12px;color:#7aafc8;">No events found</p>
    <p>You haven't registered for any events yet.</p>
    <a href="user_dashboard.php" style="display:inline-block;margin-top:14px;padding:8px 18px;background:#2980b9;color:#fff;border-radius:7px;text-decoration:none;font-size:13px;">Browse Events</a>
</div>
<?php endif; ?>

</div>
</div>
</body>
</html>
