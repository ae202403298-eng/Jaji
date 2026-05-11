<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}

/* =========================
   PARTICIPANTS PER EVENT
   ========================= */
$event_stats = $conn->query("
    SELECT 
        e.id,
        e.event_name,
        e.fee,
        e.is_free,
        COUNT(DISTINCT r.id) AS total_registered,
        COUNT(DISTINCT CASE WHEN a.status='present' THEN a.user_id END) AS total_present,
        COUNT(DISTINCT CASE WHEN a.status='absent' THEN a.user_id END) AS total_absent,
        CASE WHEN e.is_free = 1 THEN NULL ELSE COALESCE(SUM(p.amount_paid), 0) END AS total_collected,
        CASE WHEN e.is_free = 1 THEN NULL ELSE e.fee * COUNT(DISTINCT r.id) END AS total_expected
    FROM events e
    LEFT JOIN registrations r ON r.event_id = e.id
    LEFT JOIN attendance a ON a.event_id = e.id
    LEFT JOIN payments p ON p.event_id = e.id AND e.is_free = 0
    GROUP BY e.id, e.event_name, e.fee, e.is_free
    ORDER BY e.event_date DESC
");

/* =========================
   MOST ACTIVE MEMBERS
   ========================= */
$active_members = $conn->query("
    SELECT 
        u.id,
        u.name,
        u.email,
        COUNT(CASE WHEN a.status='present' THEN 1 END) AS times_present,
        COUNT(DISTINCT r.event_id) AS events_registered
    FROM users u
    LEFT JOIN attendance a ON a.user_id = u.id AND a.status = 'present'
    LEFT JOIN registrations r ON r.user_id = u.id
    WHERE u.role = 'member'
    GROUP BY u.id, u.name, u.email
    ORDER BY times_present DESC, events_registered DESC
    LIMIT 10
");

/* =========================
   PAYMENT SUMMARY
   ========================= */
$payment_summary = $conn->query("
    SELECT 
        COUNT(CASE WHEN ps.payment_status = 'Paid' THEN 1 END) AS paid_count,
        COUNT(CASE WHEN ps.payment_status = 'Partial' THEN 1 END) AS partial_count,
        COUNT(CASE WHEN ps.payment_status = 'Unpaid' THEN 1 END) AS unpaid_count,
        COALESCE(SUM(ps.total_paid), 0) AS total_collected,
        COALESCE(SUM(ps.fee), 0) AS total_expected,
        COALESCE(SUM(ps.balance), 0) AS total_remaining
    FROM payment_status ps
    JOIN events e ON e.id = ps.event_id
    WHERE e.is_free = 0
")->fetch_assoc();

/* =========================
   PAYMENT METHOD BREAKDOWN
   ========================= */
$method_breakdown = $conn->query("
    SELECT 
        payment_method,
        COUNT(*) AS count,
        SUM(amount_paid) AS total
    FROM payments
    GROUP BY payment_method
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reports – Admin</title>
<link rel="stylesheet" href="admin_dashboard.css">
<style>
.stat-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:18px; }
.stat-card { background:#fff; border:1px solid #c8e3f3; border-radius:10px; padding:16px 18px; }
.stat-card .label { font-size:11px; font-weight:600; color:#7aafc8; text-transform:uppercase; letter-spacing:.5px; }
.stat-card .value { font-size:28px; font-weight:700; color:#1a3a5c; margin:6px 0 2px; }
.stat-card .sub { font-size:12px; color:#2980b9; }
.rank-badge { display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; border-radius:50%; background:#daeef8; color:#1e5080; font-weight:700; font-size:12px; }
.rank-badge.gold { background:#fff3cd; color:#856404; }
.rank-badge.silver { background:#e2e3e5; color:#495057; }
.rank-badge.bronze { background:#fde8d8; color:#7d3c11; }
.progress-bar { height:8px; background:#eaf3fb; border-radius:4px; overflow:hidden; margin-top:4px; }
.progress-fill { height:100%; background:linear-gradient(90deg,#2980b9,#87ceef); border-radius:4px; }
.pill-paid { background:#d5f5e3; color:#1a7a44; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
.pill-partial { background:#fef3cd; color:#7d5a00; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
.pill-unpaid { background:#fde8e8; color:#9b1c1c; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
.export-bar { display:flex; gap:10px; margin-bottom:16px; }
.btn-export { background:#27ae60; font-size:12px; padding:7px 14px; }
.btn-export:hover { background:#1e8449; }
.btn-export-excel { background:#217346; font-size:12px; padding:7px 14px; }
.section-title { font-size:14px; font-weight:600; color:#1a3a5c; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
</style>
</head>
<body>

<div class="sidebar">
    <h2>ADMIN</h2>
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="events.php">Events</a>
    <a href="attendance.php">Attendance</a>
    <a href="payments.php">Payments</a>
    <a href="reports.php" class="active">Reports</a>
    <a href="notifications.php">Notifications</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="main">
<div class="header">
    <h1> Reports</h1>
    <div class="header-right">
        <span>Welcome, Admin</span>
        <div class="avatar">A</div>
    </div>
</div>
<div class="container">

<!-- EXPORT BUTTONS -->
<div class="export-bar">
    <button class="btn-export" onclick="window.print()">Print Report</button>
    <button class="btn-export" onclick="exportTableToCSV('report_export.csv')">Export CSV</button>
</div>

<!-- PAYMENT SUMMARY CARDS -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="label">Total Collected</div>
        <div class="value">₱<?= number_format($payment_summary['total_collected'], 2) ?></div>
        <div class="sub">of ₱<?= number_format($payment_summary['total_expected'], 2) ?> expected</div>
    </div>
    <div class="stat-card">
        <div class="label">Remaining Balance</div>
        <div class="value" style="color:#e05a2b;">₱<?= number_format($payment_summary['total_remaining'], 2) ?></div>
        <div class="sub">across all registrations</div>
    </div>
    <div class="stat-card">
        <div class="label">Payment Status</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
            <span class="pill-paid">Paid: <?= $payment_summary['paid_count'] ?></span>
            <span class="pill-partial">Partial: <?= $payment_summary['partial_count'] ?></span>
            <span class="pill-unpaid">Unpaid: <?= $payment_summary['unpaid_count'] ?></span>
        </div>
    </div>
</div>

<!-- EVENT STATS TABLE -->
<div class="section">
    <div class="section-title">Event Summary</div>
    <table id="event-table">
        <tr>
            <th>Event</th>
            <th>Fee</th>
            <th>Registered</th>
            <th>Present</th>
            <th>Absent</th>
            <th>Attendance %</th>
            <th>Collected</th>
            <th>Expected</th>
            <th>Collection %</th>
        </tr>
        <?php while($ev = $event_stats->fetch_assoc()):
            $att_pct = $ev['total_registered'] > 0
                ? round(($ev['total_present'] / $ev['total_registered']) * 100) : 0;
            $is_free = !empty($ev['is_free']);
            $pay_pct = (!$is_free && $ev['total_expected'] > 0)
                ? round(($ev['total_collected'] / $ev['total_expected']) * 100) : 0;
        ?>
        <tr>
            <td>
                <strong><?= htmlspecialchars($ev['event_name']) ?></strong>
                <?php if ($is_free): ?>
                    <span style="background:#d5f5e3;color:#1a7a44;padding:1px 7px;border-radius:10px;font-size:10px;font-weight:600;margin-left:5px;">FREE</span>
                <?php endif; ?>
            </td>
            <td><?= $is_free ? '<span style="color:#27ae60;font-weight:600;">Free</span>' : '₱'.number_format($ev['fee'], 2) ?></td>
            <td><?= $ev['total_registered'] ?></td>
            <td><?= $ev['total_present'] ?></td>
            <td><?= $ev['total_absent'] ?></td>
            <td>
                <?= $att_pct ?>%
                <div class="progress-bar"><div class="progress-fill" style="width:<?= $att_pct ?>%;"></div></div>
            </td>
            <td><?= $is_free ? '<span style="color:#aaa;">N/A</span>' : '₱'.number_format($ev['total_collected'], 2) ?></td>
            <td><?= $is_free ? '<span style="color:#aaa;">N/A</span>' : '₱'.number_format($ev['total_expected'], 2) ?></td>
            <td>
                <?php if ($is_free): ?>
                    <span style="color:#aaa;font-size:12px;">N/A</span>
                <?php else: ?>
                    <?= $pay_pct ?>%
                    <div class="progress-bar"><div class="progress-fill" style="width:<?= $pay_pct ?>%; background:linear-gradient(90deg,#27ae60,#82e0aa);"></div></div>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

<!-- MOST ACTIVE MEMBERS -->
<div class="section">
    <div class="section-title">Most Active Youth Members</div>
    <table id="members-table">
        <tr>
            <th>Rank</th>
            <th>Name</th>
            <th>Email</th>
            <th>Events Registered</th>
            <th>Times Present</th>
            <th>Activity</th>
        </tr>
        <?php $rank = 1; while($m = $active_members->fetch_assoc()):
            $activity = $m['events_registered'] > 0
                ? round(($m['times_present'] / $m['events_registered']) * 100) : 0;
            $badge_class = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : ''));
        ?>
        <tr>
            <td><span class="rank-badge <?= $badge_class ?>"><?= $rank ?></span></td>
            <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
            <td><?= htmlspecialchars($m['email']) ?></td>
            <td><?= $m['events_registered'] ?></td>
            <td><?= $m['times_present'] ?></td>
            <td>
                <?= $activity ?>%
                <div class="progress-bar"><div class="progress-fill" style="width:<?= $activity ?>%;background:linear-gradient(90deg,#8e44ad,#c39bd3);"></div></div>
            </td>
        </tr>
        <?php $rank++; endwhile; ?>
    </table>
</div>

<!-- PAYMENT METHOD BREAKDOWN -->
<div class="section">
    <div class="section-title">Payment Method Breakdown</div>
    <table>
        <tr>
            <th>Method</th>
            <th>Transactions</th>
            <th>Total Amount</th>
        </tr>
        <?php while($pm = $method_breakdown->fetch_assoc()): ?>
        <tr>
            <td>
                <?php
                echo strtoupper($pm['payment_method']);
                ?>
            </td>
            <td><?= $pm['count'] ?></td>
            <td>₱<?= number_format($pm['total'], 2) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

</div>
</div>

<script>
function exportTableToCSV(filename) {
    var csv = [];
    // Collect both tables
    var tables = document.querySelectorAll('table[id]');
    tables.forEach(function(table) {
        var rows = table.querySelectorAll('tr');
        rows.forEach(function(row) {
            var cols = row.querySelectorAll('td, th');
            var rowData = Array.from(cols).map(function(col) {
                return '"' + col.innerText.replace(/"/g, '""').replace(/\n/g, ' ') + '"';
            });
            csv.push(rowData.join(','));
        });
        csv.push(''); // blank line between tables
    });

    var blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
}
</script>
</body>
</html>