<?php
session_start();
require_once "database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$user_name  = $_SESSION['user_name'] ?? 'User';
$first_name = explode(' ', trim($user_name))[0];
$initials   = strtoupper(substr($first_name, 0, 1));

/* =========================
   EVENT BALANCES ONLY
   ========================= */
$stmt = $conn->prepare("
    SELECT 
        e.id,
        e.event_name,
        e.fee,
        e.is_free,
        COALESCE(SUM(p.amount_paid),0) AS paid
    FROM registrations r
    JOIN events e ON e.id = r.event_id
    LEFT JOIN payments p ON p.event_id = e.id AND p.user_id = ?
    WHERE r.user_id = ? AND e.is_free = 0
    GROUP BY e.id, e.event_name, e.fee, e.is_free
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$events = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Payments</title>
<link rel="stylesheet" href="user_dashboard.css">
<style>
/* ── Payments-specific styles ── */
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
}

thead tr {
    background: linear-gradient(90deg, #1a3a5c, #2980b9);
}

thead th {
    color: #fff;
    padding: 11px 14px;
    text-align: center;
    font-weight: 600;
    letter-spacing: 0.3px;
    font-size: 13px;
}

thead th:first-child { border-radius: 8px 0 0 0; }
thead th:last-child  { border-radius: 0 8px 0 0; }

tbody tr {
    border-bottom: 1px solid #daeef8;
    transition: background 0.15s;
}

tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #f0f7fc; }

tbody td {
    padding: 10px 14px;
    text-align: center;
    color: #334455;
}

tbody td:first-child {
    text-align: left;
    font-weight: 600;
    color: #1a3a5c;
}

tfoot tr {
    background: #f0f6fc;
    border-top: 2px solid #c8e3f3;
}

tfoot td {
    padding: 11px 14px;
    font-weight: 700;
    color: #1a3a5c;
    text-align: center;
    font-size: 13.5px;
}

tfoot td:first-child { text-align: left; }

/* ─── STATUS BADGES ─── */
.badge {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 600;
    letter-spacing: 0.3px;
}

.badge.paid    { background: #e6f9ee; color: #1a7a3c; border: 1px solid #a3dab8; }
.badge.partial { background: #fff8e6; color: #9a6500; border: 1px solid #f5d78a; }
.badge.unpaid  { background: #fff0f0; color: #c0392b; border: 1px solid #f5b8b8; }

/* ─── NOTE ─── */
.note {
    font-size: 12.5px;
    color: #7aafc8;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 6px;
}

@media (max-width: 720px) {
    table { font-size: 12.5px; }
    thead th, tbody td, tfoot td { padding: 9px 10px; }
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>MENU</h2>
    <a href="user_dashboard.php">Dashboard</a>
    <a href="user_events.php">My Events</a>
    <a href="user_payments.php" class="active">Payments</a>
    <a href="user_attendance.php">Attendance</a>
    <a href="user_notifications.php">Notifications</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<!-- MAIN -->
<div class="main">

    <!-- HEADER -->
    <div class="header">
        <h1>My Payments</h1>
        <div class="header-right">
            <span>Welcome, <?= htmlspecialchars($first_name) ?>!</span>
            <div class="avatar"><?= $initials ?></div>
        </div>
    </div>

    <div class="container">

        <div class="section">
            <h2>Event Balances</h2>
            <p class="note">Only paid events are listed below. Free events do not require payment.</p>

            <?php
            $total_fee     = 0;
            $total_paid    = 0;
            $total_balance = 0;
            $rows = [];

            while ($e = $events->fetch_assoc()) {
                $balance = $e['fee'] - $e['paid'];
                if ($e['paid'] == 0)     { $status = 'Unpaid';  $class = 'unpaid'; }
                elseif ($balance > 0)    { $status = 'Partial'; $class = 'partial'; }
                else                     { $status = 'Paid';    $class = 'paid'; }

                $total_fee     += $e['fee'];
                $total_paid    += $e['paid'];
                $total_balance += $balance;
                $rows[] = compact('e', 'balance', 'status', 'class');
            }
            ?>

            <?php if (!empty($rows)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Fee</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $e = $r['e'];
                ?>
                <tr>
                    <td><?= htmlspecialchars($e['event_name']) ?></td>
                    <td>₱<?= number_format($e['fee'], 2) ?></td>
                    <td>₱<?= number_format($e['paid'], 2) ?></td>
                    <td>₱<?= number_format($r['balance'], 2) ?></td>
                    <td><span class="badge <?= $r['class'] ?>"><?= $r['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td>₱<?= number_format($total_fee, 2) ?></td>
                        <td>₱<?= number_format($total_paid, 2) ?></td>
                        <td>₱<?= number_format($total_balance, 2) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <span style="font-size:14px;display:block;margin-bottom:10px;color:#7aafc8;">No records</span>
                No paid events registered yet.
            </div>
            <?php endif; ?>

        </div>

    </div>
</div>

</body>
</html>