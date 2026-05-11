<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}

$upcoming = $conn->query("
    SELECT e.*, COUNT(DISTINCT r.id) AS registered_count, DATEDIFF(e.event_date, NOW()) AS days_until
    FROM events e
    LEFT JOIN registrations r ON r.event_id = e.id
    WHERE e.event_date >= NOW()
    GROUP BY e.id
    ORDER BY e.event_date ASC
");

$unpaid_members = $conn->query("
    SELECT u.name, u.email, e.event_name, e.fee,
           COALESCE(SUM(p.amount_paid), 0) AS paid,
           e.fee - COALESCE(SUM(p.amount_paid), 0) AS balance,
           CASE WHEN SUM(p.amount_paid) IS NULL OR SUM(p.amount_paid) = 0 THEN 'Unpaid' ELSE 'Partial' END AS status
    FROM registrations r
    JOIN users u ON u.id = r.user_id
    JOIN events e ON e.id = r.event_id
    LEFT JOIN payments p ON p.user_id = r.user_id AND p.event_id = r.event_id
    GROUP BY r.user_id, r.event_id
    HAVING paid < e.fee
    ORDER BY balance DESC
");

$notifications = [];
$upcoming_arr = [];
while ($ev = $upcoming->fetch_assoc()) {
    $upcoming_arr[] = $ev;
    $days = (int)$ev['days_until'];
    if ($days === 0)
        $notifications[] = ['type'=>'urgent','msg'=>"<strong>{$ev['event_name']}</strong> is happening TODAY at {$ev['location']}!"];
    elseif ($days === 1)
        $notifications[] = ['type'=>'warn','msg'=>"<strong>{$ev['event_name']}</strong> starts TOMORROW — prepare the attendance list."];
    elseif ($days <= 7)
        $notifications[] = ['type'=>'info','msg'=>"<strong>{$ev['event_name']}</strong> is {$days} days away. {$ev['registered_count']} member(s) registered."];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications – Admin</title>
<link rel="stylesheet" href="admin_dashboard.css">
<style>
/* ── Section h2 border-bottom for notifications ───────── */
.section h2 {
    padding-bottom: 14px;
    border-bottom: 1px solid #eaf3fb;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ── Alert list ───────────────────────────────────────── */
.notif-list { list-style: none; padding: 0; }

.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 0;
    border-bottom: 1px solid #f0f6fb;
}
.notif-item:first-child { padding-top: 0; }
.notif-item:last-child { border-bottom: none; padding-bottom: 0; }

.notif-icon {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
}
.notif-icon.urgent { background: #fde8e8; }
.notif-icon.warn   { background: #fef3cd; }
.notif-icon.info   { background: #daeef8; }

.notif-text { font-size: 13.5px; color: #334455; line-height: 1.6; padding-top: 6px; }

/* ── Upcoming cards ───────────────────────────────────── */
.upcoming-cards { display: flex; flex-direction: column; gap: 14px; }

.upcoming-card {
    background: #f5f9fd;
    border: 1px solid #daeef8;
    border-left: 4px solid #2980b9;
    border-radius: 10px;
    padding: 18px 22px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
}

.upcoming-card h4 {
    font-size: 14.5px;
    font-weight: 700;
    color: #1a3a5c;
    margin-bottom: 10px;
}

.upcoming-meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    font-size: 12.5px;
    color: #5a8aa8;
}

.upcoming-meta span { display: flex; align-items: center; gap: 5px; }

.days-chip {
    display: inline-flex;
    align-items: center;
    padding: 5px 14px;
    border-radius: 99px;
    font-size: 11.5px;
    font-weight: 700;
    white-space: nowrap;
    flex-shrink: 0;
}
.days-chip.today    { background: #fde8e8; color: #9b1c1c; }
.days-chip.soon     { background: #fef3cd; color: #7d5a00; }
.days-chip.upcoming { background: #d5f5e3; color: #1a7a44; }

/* ── Tables ───────────────────────────────────────────── */
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.data-table thead tr {
    border-bottom: 2px solid #eaf3fb;
}

.data-table th {
    padding: 10px 14px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #7aafc8;
}

.data-table td {
    padding: 15px 14px;
    color: #334455;
    border-bottom: 1px solid #f0f6fb;
    vertical-align: middle;
}

.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: #f8fbfd; }

.balance-cell { color: #e05a2b; font-weight: 700; font-size: 13.5px; }

/* ── Pills ────────────────────────────────────────────── */
.pill {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 700;
}
.pill-unpaid  { background: #fde8e8; color: #9b1c1c; }
.pill-partial { background: #fef3cd; color: #7d5a00; }

/* ── Review button ────────────────────────────────────── */
.btn-review {
    background: #27ae60;
    color: #fff;
    border: none;
    padding: 7px 16px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: background 0.15s;
}
.btn-review:hover { background: #1e8449; }

/* ── Placeholder box ──────────────────────────────────── */
.placeholder-box {
    background: #f8f9fa;
    border: 1px dashed #adb5bd;
    border-radius: 10px;
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.placeholder-row { display: flex; flex-direction: column; gap: 4px; }
.placeholder-row strong { font-size: 12.5px; color: #445; }
.placeholder-row code {
    background: #e9ecef;
    padding: 4px 10px;
    border-radius: 5px;
    font-size: 12px;
    color: #444;
    display: inline-block;
}
.intro-text { font-size: 13px; color: #556677; margin-bottom: 14px; line-height: 1.6; }

/* ── Empty states ─────────────────────────────────────── */
.empty-state {
    text-align: center;
    padding: 30px 20px;
    color: #9aafbe;
    font-size: 13.5px;
}
</style>
</head>
<body>

<div class="sidebar">
    <h2>ADMIN</h2>
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="events.php">Events</a>
    <a href="attendance.php">Attendance</a>
    <a href="payments.php">Payments</a>
    <a href="reports.php">Reports</a>
    <a href="notifications.php" class="active">Notifications</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="main">
<div class="header">
    <h1>Notifications</h1>
    <div class="header-right">
        <span>Welcome, Admin</span>
        <div class="avatar">A</div>
    </div>
</div>
<div class="container">

    <!-- SYSTEM ALERTS -->
    <div class="section">
        <h2>System Alerts</h2>
        <?php if (!empty($notifications)): ?>
        <ul class="notif-list">
            <?php
            $icons = ['urgent'=>'!','warn'=>'!','info'=>'i','success'=>'OK'];
            foreach ($notifications as $n): ?>
            <li class="notif-item">
                <div class="notif-icon <?= $n['type'] ?>"><?= $icons[$n['type']] ?? 'i' ?></div>
                <span class="notif-text"><?= $n['msg'] ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="empty-state">No active alerts at this time.</p>
        <?php endif; ?>
    </div>

    <!-- UPCOMING EVENTS -->
    <div class="section">
        <h2>Upcoming Events</h2>
        <?php if (!empty($upcoming_arr)): ?>
        <div class="upcoming-cards">
            <?php foreach ($upcoming_arr as $ev):
                $days = (int)$ev['days_until'];
                $chip_class = $days === 0 ? 'today' : ($days <= 7 ? 'soon' : 'upcoming');
                $chip_label = $days === 0 ? 'TODAY' : ($days === 1 ? 'TOMORROW' : "in {$days} days");
            ?>
            <div class="upcoming-card">
                <div>
                    <h4><?= htmlspecialchars($ev['event_name']) ?></h4>
                    <div class="upcoming-meta">
                        <span>Date: <?= date('M d, Y h:i A', strtotime($ev['event_date'])) ?></span>
                        <span>Location: <?= htmlspecialchars($ev['location'] ?? '—') ?></span>
                        <span>Fee: ₱<?= number_format($ev['fee'], 2) ?></span>
                        <span><?= $ev['registered_count'] ?> registered</span>
                    </div>
                </div>
                <span class="days-chip <?= $chip_class ?>"><?= $chip_label ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="empty-state">No upcoming events scheduled.</p>
        <?php endif; ?>
    </div>

    <!-- OUTSTANDING BALANCES -->
    <div class="section">
        <h2>Members with Outstanding Balance</h2>
        <?php if ($unpaid_members->num_rows > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Email</th>
                    <th>Event</th>
                    <th>Fee</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php while($um = $unpaid_members->fetch_assoc()): ?>
            <tr>
                <td><strong><?= htmlspecialchars($um['name']) ?></strong></td>
                <td style="color:#7aafc8;"><?= htmlspecialchars($um['email']) ?></td>
                <td><?= htmlspecialchars($um['event_name']) ?></td>
                <td>₱<?= number_format($um['fee'], 2) ?></td>
                <td>₱<?= number_format($um['paid'], 2) ?></td>
                <td class="balance-cell">₱<?= number_format($um['balance'], 2) ?></td>
                <td>
                    <span class="pill <?= $um['status'] === 'Unpaid' ? 'pill-unpaid' : 'pill-partial' ?>">
                        <?= $um['status'] ?>
                    </span>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="empty-state">All members are fully paid.</p>
        <?php endif; ?>
    </div>



    

</div>
</div>
</body>
</html>