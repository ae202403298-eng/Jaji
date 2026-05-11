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

// ── Upcoming events the user is registered for ──
$upcoming_events = $conn->query("
    SELECT e.event_name, e.event_date, e.location, e.fee, e.is_free,
           DATEDIFF(e.event_date, NOW()) AS days_until,
           COALESCE(SUM(p.amount_paid), 0) AS total_paid
    FROM registrations r
    JOIN events e ON e.id = r.event_id
    LEFT JOIN payments p ON p.user_id = r.user_id AND p.event_id = r.event_id AND e.is_free = 0
    WHERE r.user_id = $user_id AND e.event_date >= NOW()
    GROUP BY e.id
    ORDER BY e.event_date ASC
");

// ── Payment reminders (unpaid / partial for this user) ──
$payment_alerts = $conn->query("
    SELECT e.id AS event_id, e.event_name, e.fee,
           COALESCE(SUM(p.amount_paid), 0) AS total_paid,
           e.fee - COALESCE(SUM(p.amount_paid), 0) AS balance,
           CASE
               WHEN COALESCE(SUM(p.amount_paid), 0) = 0 THEN 'Unpaid'
               ELSE 'Partial'
           END AS pay_status
    FROM registrations r
    JOIN events e ON e.id = r.event_id
    LEFT JOIN payments p ON p.user_id = r.user_id AND p.event_id = r.event_id
    WHERE r.user_id = $user_id AND e.is_free = 0
    GROUP BY e.id
    HAVING total_paid < e.fee
    ORDER BY balance DESC
");

// ── Attendance records ──
$attendance_records = $conn->query("
    SELECT e.event_name, e.event_date, a.status, a.attendance_date
    FROM attendance a
    JOIN events e ON e.id = a.event_id
    WHERE a.user_id = $user_id
    ORDER BY a.attendance_date DESC
    LIMIT 10
");

// ── Build notification alerts ──
$notifications = [];
$upcoming_arr  = [];
while ($ev = $upcoming_events->fetch_assoc()) {
    $upcoming_arr[] = $ev;
    $days = (int)$ev['days_until'];
    if ($days === 0)
        $notifications[] = ['type'=>'urgent', 'msg'=>"<strong>{$ev['event_name']}</strong> is happening TODAY at {$ev['location']}! Don't forget to attend."];
    elseif ($days === 1)
        $notifications[] = ['type'=>'warn', 'msg'=>"<strong>{$ev['event_name']}</strong> starts TOMORROW — get ready!"];
    elseif ($days <= 7)
        $notifications[] = ['type'=>'info', 'msg'=>"<strong>{$ev['event_name']}</strong> is in {$days} days."];

    // Payment reminder for upcoming events
    if (!$ev['is_free'] && $ev['total_paid'] < $ev['fee']) {
        $remaining = $ev['fee'] - $ev['total_paid'];
        $notifications[] = ['type'=>'warn', 'msg'=>"You have an outstanding balance of <strong>₱" . number_format($remaining, 2) . "</strong> for <strong>{$ev['event_name']}</strong>."];
    }
}

if (empty($notifications))
    $notifications[] = ['type'=>'info', 'msg'=>"You're all caught up! No new notifications."];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications</title>
<link rel="stylesheet" href="user_dashboard.css">
<style>
/* ── Notification list ───────────────────────────────── */
.notif-list { list-style: none; padding: 0; margin: 0; }

.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 0;
    border-bottom: 1px solid #f0f6fb;
}
.notif-item:first-child { padding-top: 0; }
.notif-item:last-child  { border-bottom: none; padding-bottom: 0; }

.notif-icon {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
}
.notif-icon.urgent { background: #fde8e8; color: #9b1c1c; }
.notif-icon.warn   { background: #fef3cd; color: #7d5a00; }
.notif-icon.info   { background: #daeef8; color: #1e5080; }

.notif-text {
    font-size: 13.5px;
    color: #334455;
    line-height: 1.6;
    padding-top: 6px;
}

/* ── Upcoming event cards ────────────────────────────── */
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
    transition: box-shadow 0.2s;
}
.upcoming-card:hover { box-shadow: 0 4px 14px rgba(41,128,185,0.08); }

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

/* ── Payment alert cards ─────────────────────────────── */
.pay-alert-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    background: #fffaf5;
    border: 1px solid #f5dcc8;
    border-left: 4px solid #e05a2b;
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 12px;
    transition: box-shadow 0.2s;
}
.pay-alert-card:hover { box-shadow: 0 4px 14px rgba(224,90,43,0.08); }
.pay-alert-card:last-child { margin-bottom: 0; }

.pay-info h4 {
    font-size: 14px;
    font-weight: 700;
    color: #1a3a5c;
    margin-bottom: 6px;
}
.pay-info p {
    font-size: 12.5px;
    color: #7aafc8;
}
.pay-info .balance-amount {
    color: #e05a2b;
    font-weight: 700;
    font-size: 13.5px;
}

.btn-pay {
    background: #27ae60;
    font-size: 12px;
    padding: 7px 16px;
    border: none;
    border-radius: 8px;
    color: #fff;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-weight: 600;
    transition: background 0.15s;
}
.btn-pay:hover { background: #1e8449; }

/* ── Data table ──────────────────────────────────────── */
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.data-table thead tr { border-bottom: 2px solid #eaf3fb; }
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
    padding: 14px;
    color: #334455;
    border-bottom: 1px solid #f0f6fb;
    vertical-align: middle;
}
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: #f8fbfd; }

/* ── Pills ───────────────────────────────────────────── */
.pill {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 700;
}
.pill-present { background: #d5f5e3; color: #1a7a44; }
.pill-absent  { background: #fde8e8; color: #9b1c1c; }
.pill-unpaid  { background: #fde8e8; color: #9b1c1c; }
.pill-partial { background: #fef3cd; color: #7d5a00; }

/* ── Empty state ─────────────────────────────────────── */
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
    <h2>MENU</h2>
    <a href="user_dashboard.php">Dashboard</a>
    <a href="user_events.php">My Events</a>
    <a href="user_payments.php">Payments</a>
    <a href="user_attendance.php">Attendance</a>
    <a href="user_notifications.php" class="active">Notifications</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="main">
<div class="header">
    <h1>Notifications</h1>
    <div class="header-right">
        <span>Hi, <?= htmlspecialchars($first_name) ?></span>
        <div class="avatar"><?= $initials ?></div>
    </div>
</div>
<div class="container">

    <!-- ALERTS -->
    <div class="section">
        <h2>Alerts</h2>
        <ul class="notif-list">
            <?php
            $icons = ['urgent'=>'!','warn'=>'!','info'=>'i'];
            foreach ($notifications as $n): ?>
            <li class="notif-item">
                <div class="notif-icon <?= $n['type'] ?>"><?= $icons[$n['type']] ?? 'i' ?></div>
                <span class="notif-text"><?= $n['msg'] ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- UPCOMING EVENTS -->
    <div class="section">
        <h2>Your Upcoming Events</h2>
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
                        <span>
                            <?php if ($ev['is_free']): ?>
                                <strong style="color:#27ae60;">Free</strong>
                            <?php else: ?>
                                Fee: ₱<?= number_format($ev['fee'], 2) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <span class="days-chip <?= $chip_class ?>"><?= $chip_label ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="empty-state">No upcoming events you're registered for.</p>
        <?php endif; ?>
    </div>

    <!-- PAYMENT REMINDERS -->
    <div class="section">
        <h2>Payment Reminders</h2>
        <?php if ($payment_alerts->num_rows > 0): ?>
        <?php while($pa = $payment_alerts->fetch_assoc()): ?>
        <div class="pay-alert-card">
            <div class="pay-info">
                <h4><?= htmlspecialchars($pa['event_name']) ?></h4>
                <p>
                    Fee: ₱<?= number_format($pa['fee'], 2) ?> &middot;
                    Paid: ₱<?= number_format($pa['total_paid'], 2) ?> &middot;
                    Balance: <span class="balance-amount">₱<?= number_format($pa['balance'], 2) ?></span>
                </p>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <span class="pill <?= $pa['pay_status'] === 'Unpaid' ? 'pill-unpaid' : 'pill-partial' ?>">
                    <?= $pa['pay_status'] ?>
                </span>
                <a href="user_payments.php" class="btn-pay">Pay Now</a>
            </div>
        </div>
        <?php endwhile; ?>
        <?php else: ?>
        <p class="empty-state">All payments are up to date!</p>
        <?php endif; ?>
    </div>

    <!-- RECENT ATTENDANCE -->
    <div class="section">
        <h2>Recent Attendance</h2>
        <?php if ($attendance_records->num_rows > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Event Date</th>
                    <th>Attended</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php while($ar = $attendance_records->fetch_assoc()): ?>
            <tr>
                <td><strong><?= htmlspecialchars($ar['event_name']) ?></strong></td>
                <td><?= date('M d, Y', strtotime($ar['event_date'])) ?></td>
                <td><?= date('M d, Y', strtotime($ar['attendance_date'])) ?></td>
                <td>
                    <span class="pill <?= $ar['status'] === 'present' ? 'pill-present' : 'pill-absent' ?>">
                        <?= ucfirst($ar['status']) ?>
                    </span>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="empty-state">No attendance records yet.</p>
        <?php endif; ?>
    </div>

</div>
</div>

</body>
</html>
