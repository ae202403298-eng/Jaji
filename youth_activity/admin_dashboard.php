<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "database.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}

$events       = $conn->query("SELECT COUNT(*) AS total FROM events")->fetch_assoc();
$participants = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='member'")->fetch_assoc();
$payments     = $conn->query("SELECT COALESCE(SUM(amount_paid),0) AS total FROM payments")->fetch_assoc();
$attendance   = $conn->query("SELECT COUNT(*) AS total FROM attendance WHERE status='present'")->fetch_assoc();

$search        = trim($_GET['search'] ?? '');
$filter_date   = $_GET['filter_date'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_method = $_GET['filter_method'] ?? '';

$where_parts = ["1=1"];
$bind_params = [];
$bind_types  = '';
if ($search !== '') {
    $where_parts[] = "(e.event_name LIKE ? OR e.location LIKE ?)";
    $bind_params[] = "%$search%"; $bind_params[] = "%$search%";
    $bind_types   .= 'ss';
}
if ($filter_date !== '') {
    $where_parts[] = "DATE(e.event_date) = ?";
    $bind_params[] = $filter_date; $bind_types .= 's';
}
$where_sql = implode(' AND ', $where_parts);

$event_query = "
    SELECT e.id, e.event_name, e.event_date, e.location, e.fee,
        CASE WHEN e.event_date > NOW() THEN 'Upcoming'
             WHEN DATE(e.event_date) = CURDATE() THEN 'Ongoing' ELSE 'Completed' END AS status,
        COUNT(DISTINCT r.id) AS registered_count
    FROM events e LEFT JOIN registrations r ON r.event_id = e.id
    WHERE $where_sql GROUP BY e.id ORDER BY e.event_date ASC LIMIT 10";
if (!empty($bind_params)) {
    $stmt = $conn->prepare($event_query); $stmt->bind_param($bind_types, ...$bind_params);
    $stmt->execute(); $event_list = $stmt->get_result();
} else { $event_list = $conn->query($event_query); }

$pay_where_sql = $filter_method !== '' ? "p.payment_method = '".addslashes($filter_method)."'" : "1=1";
$payment_overview = $conn->query("
    SELECT COALESCE((SELECT SUM(e.fee) FROM events e JOIN registrations r ON r.event_id=e.id WHERE e.is_free=0),0) AS expected,
           COALESCE(SUM(p.amount_paid),0) AS collected,
           COALESCE((SELECT SUM(e.fee) FROM events e JOIN registrations r ON r.event_id=e.id WHERE e.is_free=0),0) - COALESCE(SUM(p.amount_paid),0) AS remaining
    FROM payments p WHERE $pay_where_sql")->fetch_assoc();

$status_q = "
SELECT 
COUNT(CASE WHEN ps.payment_status COLLATE utf8mb4_general_ci = 'Paid' THEN 1 END) AS paid,
COUNT(CASE WHEN ps.payment_status COLLATE utf8mb4_general_ci = 'Partial' THEN 1 END) AS partial,
COUNT(CASE WHEN ps.payment_status COLLATE utf8mb4_general_ci = 'Unpaid' THEN 1 END) AS unpaid
FROM payment_status ps";
if ($filter_status !== '') 
    $status_q .= " WHERE ps.payment_status COLLATE utf8mb4_general_ci='".addslashes($filter_status)."'";
$status_breakdown = $conn->query($status_q)->fetch_assoc();

$ps_list = null;
if ($filter_status !== '' || $search !== '') {
    $ps_where = "1=1";

    if ($filter_status !== '') {
        $ps_where .= " AND ps.payment_status COLLATE utf8mb4_general_ci='".addslashes($filter_status)."'";
    }

    if ($search !== '') {
        $ps_where .= " AND (
            ps.name COLLATE utf8mb4_general_ci LIKE '%".addslashes($search)."%' 
            OR ps.event_name COLLATE utf8mb4_general_ci LIKE '%".addslashes($search)."%'
        )";
    }

    $ps_list = $conn->query("
        SELECT ps.*, u.email 
        FROM payment_status ps 
        JOIN users u ON u.id=ps.user_id 
        WHERE $ps_where 
        LIMIT 20
    ");
}

$att = $conn->query("
    SELECT e.event_name, COUNT(CASE WHEN a.status='present' THEN 1 END) AS present,
           COUNT(DISTINCT r.id) AS total_registered
    FROM events e LEFT JOIN attendance a ON a.event_id=e.id LEFT JOIN registrations r ON r.event_id=e.id
    GROUP BY e.id ORDER BY e.event_date DESC LIMIT 1")->fetch_assoc();

$notifs = [];
$un = $conn->query("SELECT event_name, DATEDIFF(event_date, NOW()) AS days_until FROM events WHERE event_date >= NOW() ORDER BY event_date ASC LIMIT 3");
while ($n = $un->fetch_assoc()) {
    $d = (int)$n['days_until'];
    if ($d===0) $notifs[] = ['urgent',"<strong>{$n['event_name']}</strong> is TODAY!"];
    elseif ($d===1) $notifs[] = ['warn',"<strong>{$n['event_name']}</strong> starts tomorrow — prepare attendance list."];
    elseif ($d<=7)  $notifs[] = ['info',"<strong>{$n['event_name']}</strong> is in {$d} days."];
}
if (empty($notifs)) $notifs[] = ['info',"No urgent notifications at this time."];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="admin_dashboard.css">
<style>
/* ── Layout ───────────────────────────────────────────── */
.main { padding: 0; background: #eaf3fb; }
.container { padding: 24px 32px; max-width: 100%; }

/* ── Header: rounded card style ──────────────────────── */
.header {
    background: #fff;
    margin: 20px 32px 0 32px;
    border-radius: 14px;
    border: 1px solid #dde8f0;
    padding: 16px 24px;
    position: static;
    box-shadow: none;
}
.header h1 { font-size: 16px; }

/* ── Filter bar ───────────────────────────────────────── */
.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 24px;
    align-items: center;
}
.filter-bar input[type=text],
.filter-bar input[type=date],
.filter-bar select {
    padding: 9px 13px;
    border: 1px solid #c8e3f3;
    border-radius: 8px;
    font-size: 13px;
    color: #334;
    background: #f5f9fd;
    outline: none;
    transition: border-color 0.2s;
}
.filter-bar input[type=text] { flex: 1; min-width: 180px; }
.filter-bar input[type=text]:focus,
.filter-bar input[type=date]:focus,
.filter-bar select:focus { border-color: #2980b9; background: #fff; }
.filter-bar select { min-width: 160px; }
.btn-clear { background: #95a5a6; font-size: 13px; }
.btn-clear:hover { background: #7f8c8d; }

.active-filters {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 20px;
}
.active-filter {
    background: #eaf7fb;
    border: 1px solid #2980b9;
    border-radius: 7px;
    padding: 5px 11px;
    font-size: 12px;
    color: #1e5080;
}

/* ── Notifications section ────────────────────────────── */
.section.notifications {
    border-left: 4px solid #2980b9;
    margin-bottom: 24px;
}
.section.notifications h2 {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 13px 0;
    border-bottom: 1px solid #eaf3fb;
    font-size: 13px;
    color: #445;
    line-height: 1.5;
}
.notif-item:first-of-type { padding-top: 0; }
.notif-item:last-child { border-bottom: none; padding-bottom: 0; }

.notif-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
}
.notif-icon.urgent { background: #fde8e8; }
.notif-icon.warn   { background: #fef3cd; }
.notif-icon.info   { background: #daeef8; }

/* ── Stat cards ───────────────────────────────────────── */
.cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
.card {
    background: #fff;
    padding: 20px 22px;
    border-radius: 12px;
    border: 1px solid #c8e3f3;
    transition: transform 0.2s, box-shadow 0.2s;
}
.card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(41,128,185,0.10); }
.card h3 { font-size: 11px; font-weight: 600; color: #7aafc8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
.card p  { font-size: 28px; font-weight: 700; color: #1a3a5c; }

/* ── Section overrides ────────────────────────────────── */
.section {
    background: #fff;
    padding: 22px 26px;
    border-radius: 12px;
    margin-bottom: 20px;
    border: 1px solid #c8e3f3;
}
.section h2 {
    font-size: 14.5px;
    font-weight: 600;
    color: #1a3a5c;
    margin-bottom: 18px;
}
.section p {
    font-size: 13.5px;
    color: #445;
    margin-bottom: 8px;
}

/* ── Table ────────────────────────────────────────────── */
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.data-table thead tr { border-bottom: 2px solid #daeef8; }
.data-table th {
    padding: 10px 12px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    color: #7aafc8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.data-table td {
    padding: 14px 12px;
    border-bottom: 1px solid #f0f6fb;
    color: #334;
    vertical-align: middle;
}
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: #f8fbfd; }

/* ── Status pills ─────────────────────────────────────── */
.status-pill {
    display: inline-block;
    padding: 4px 11px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
}
.status-pill.Upcoming  { background: #daeef8; color: #1e5080; }
.status-pill.Ongoing   { background: #d5f5e3; color: #1a7a44; }
.status-pill.Completed { background: #e2e3e5; color: #555; }

.pill {
    display: inline-block;
    padding: 4px 11px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 700;
}
.pill-paid    { background: #d5f5e3; color: #1a7a44; }
.pill-partial { background: #fef3cd; color: #7d5a00; }
.pill-unpaid  { background: #fde8e8; color: #9b1c1c; }

/* ── Edit button ──────────────────────────────────────── */
.btn-edit {
    padding: 6px 14px;
    font-size: 12px;
    background: #2980b9;
    color: #fff;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    transition: background 0.15s;
}
.btn-edit:hover { background: #1e5080; }

/* ── Bottom grid ──────────────────────────────────────── */
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

/* ── Progress bar ─────────────────────────────────────── */
.progress-bar { height: 6px; background: #daeef8; border-radius: 3px; margin-top: 10px; }
.progress-fill { height: 6px; background: linear-gradient(90deg, #2980b9, #87ceef); border-radius: 3px; }
</style>
</head>
<body>

<div class="sidebar">
    <h2>ADMIN</h2>
    <a href="admin_dashboard.php" class="active">Dashboard</a>
    <a href="events.php">Events</a>
    <a href="attendance.php">Attendance</a>
    <a href="payments.php">Payments</a>
    <a href="reports.php">Reports</a>
    <a href="notifications.php">Notifications</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="main">
<div class="header">
    <h1>Dashboard Overview</h1>
    <div class="header-right">
        <span>Welcome back, Admin</span>
        <div class="avatar">A</div>
    </div>
</div>
<div class="container">

    <!-- FILTER BAR -->
    <form method="GET" class="filter-bar">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search events, members...">
        <input type="date" name="filter_date" value="<?= htmlspecialchars($filter_date) ?>" title="Filter by event date">
        <select name="filter_status">
            <option value="">All Payment Statuses</option>
            <option value="Paid"    <?= $filter_status==='Paid'    ? 'selected':'' ?>>Paid</option>
            <option value="Partial" <?= $filter_status==='Partial' ? 'selected':'' ?>>Partial</option>
            <option value="Unpaid"  <?= $filter_status==='Unpaid'  ? 'selected':'' ?>>Unpaid</option>
        </select>
        <select name="filter_method">
            <option value="">All Payment Methods</option>
            <option value="cash"  <?= $filter_method==='cash'  ? 'selected':'' ?>>Cash</option>
            <option value="gcash" <?= $filter_method==='gcash' ? 'selected':'' ?>>GCash</option>
            <option value="bank"  <?= $filter_method==='bank'  ? 'selected':'' ?>>Bank Transfer</option>
        </select>
        <button type="submit">Filter</button>
        <a href="admin_dashboard.php"><button type="button" class="btn-clear">Clear</button></a>
    </form>

    <?php if ($search || $filter_date || $filter_status || $filter_method): ?>
    <div class="active-filters">
        <span style="font-size:12px;color:#7aafc8;">Active filters:</span>
        <?php if ($search):     ?><span class="active-filter">Search: "<?= htmlspecialchars($search) ?>"</span><?php endif; ?>
        <?php if ($filter_date):?><span class="active-filter">Date: <?= $filter_date ?></span><?php endif; ?>
        <?php if ($filter_status):?><span class="active-filter">Status: <?= $filter_status ?></span><?php endif; ?>
        <?php if ($filter_method):?><span class="active-filter">Method: <?= strtoupper($filter_method) ?></span><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- NOTIFICATIONS -->
    <div class="section notifications">
        <h2>
            Notifications
            <a href="notifications.php" style="font-size:11px;font-weight:500;color:#2980b9;text-decoration:none;">View all →</a>
        </h2>
        <?php
        $icons = ['urgent'=>'!','warn'=>'!','info'=>'i'];
        foreach ($notifs as $n): ?>
        <div class="notif-item">
            <div class="notif-icon <?= $n[0] ?>"><?= $icons[$n[0]] ?? 'i' ?></div>
            <span><?= $n[1] ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- STAT CARDS -->
    <div class="cards">
        <div class="card">
            <h3>Total Events</h3>
            <p><?= $events['total'] ?></p>
        </div>
        <div class="card">
            <h3>Total Participants</h3>
            <p><?= $participants['total'] ?></p>
        </div>
        <div class="card">
            <h3>Total Payments</h3>
            <p>₱<?= number_format($payments['total'], 2) ?></p>
        </div>
        <div class="card">
            <h3>Total Attendance</h3>
            <p><?= $attendance['total'] ?></p>
        </div>
    </div>

    <!-- EVENTS TABLE -->
    <div class="section">
        <h2>Upcoming Events <?php if ($filter_date || $search): ?><span style="font-size:11px;color:#7aafc8;font-weight:400;">(filtered)</span><?php endif; ?></h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Date</th>
                    <th>Location</th>
                    <th>Registered</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($event_list && $event_list->num_rows > 0): while ($e = $event_list->fetch_assoc()): ?>
            <tr>
                <td><strong><?= htmlspecialchars($e['event_name']) ?></strong></td>
                <td><?= date('M d, Y', strtotime($e['event_date'])) ?></td>
                <td><?= htmlspecialchars($e['location'] ?? '—') ?></td>
                <td><?= $e['registered_count'] ?></td>
                <td><span class="status-pill <?= $e['status'] ?>"><?= $e['status'] ?></span></td>
                <td><button class="btn-edit" onclick="location.href='edit_event.php?id=<?= $e['id'] ?>'">Edit</button></td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="6" style="text-align:center;color:#aaa;padding:24px;">No events found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- FILTERED PAYMENT STATUS -->
    <?php if ($ps_list && $ps_list->num_rows > 0): ?>
    <div class="section">
        <h2>Members — <?= htmlspecialchars($filter_status ?: 'All') ?> Payment Status</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Member</th><th>Email</th><th>Event</th>
                    <th>Fee</th><th>Paid</th><th>Balance</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $pill_map = ['Paid'=>'pill-paid','Partial'=>'pill-partial','Unpaid'=>'pill-unpaid'];
            while ($ps = $ps_list->fetch_assoc()): ?>
            <tr>
                <td><strong><?= htmlspecialchars($ps['name']) ?></strong></td>
                <td style="color:#7aafc8;"><?= htmlspecialchars($ps['email']) ?></td>
                <td><?= htmlspecialchars($ps['event_name']) ?></td>
                <td>₱<?= number_format($ps['fee'], 2) ?></td>
                <td>₱<?= number_format($ps['total_paid'], 2) ?></td>
                <td style="color:#e05a2b;font-weight:600;">₱<?= number_format($ps['balance'], 2) ?></td>
                <td><span class="pill <?= $pill_map[$ps['payment_status']] ?? '' ?>"><?= $ps['payment_status'] ?></span></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- BOTTOM GRID -->
    <div class="grid-2">
        <div class="section">
            <h2>Attendance Summary</h2>
            <?php if ($att):
                $pct = $att['total_registered'] > 0 ? round(($att['present'] / $att['total_registered']) * 100) : 0; ?>
            <p><?= htmlspecialchars($att['event_name']) ?>: <strong><?= $att['present'] ?></strong> / <?= $att['total_registered'] ?> members</p>
            <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%;"></div></div>
            <small style="color:#7aafc8;font-size:11.5px;display:block;margin-top:6px;"><?= $pct ?>% attendance rate</small>
            <?php else: ?><p style="color:#aaa;">No events yet.</p><?php endif; ?>
            <a href="attendance.php" style="display:inline-block;margin-top:14px;font-size:12.5px;color:#2980b9;text-decoration:none;">View full attendance →</a>
        </div>

        <div class="section">
            <h2>Payment Overview <?php if ($filter_method): ?><span style="font-size:11px;color:#7aafc8;font-weight:400;">(<?= strtoupper($filter_method) ?>)</span><?php endif; ?></h2>
            <p>Total Expected: <strong>₱<?= number_format($payment_overview['expected'] ?? 0, 2) ?></strong></p>
            <p>Total Collected: <strong style="color:#2980b9;">₱<?= number_format($payment_overview['collected'] ?? 0, 2) ?></strong></p>
            <p>Remaining: <strong style="color:#e05a2b;">₱<?= number_format($payment_overview['remaining'] ?? 0, 2) ?></strong></p>
            <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;">
                <span class="pill pill-paid"><?= $status_breakdown['paid'] ?? 0 ?> Paid</span>
                <span class="pill pill-partial"><?= $status_breakdown['partial'] ?? 0 ?> Partial</span>
                <span class="pill pill-unpaid"><?= $status_breakdown['unpaid'] ?? 0 ?> Unpaid</span>
            </div>
            <a href="payments.php" style="display:inline-block;margin-top:14px;font-size:12.5px;color:#2980b9;text-decoration:none;">View full payments →</a>
        </div>
    </div>

</div>
</div>
</body>
</html>