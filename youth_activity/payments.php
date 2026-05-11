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

// ADD PAYMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $user_id        = (int)$_POST['user_id'];
    $event_id       = (int)$_POST['event_id'];
    $amount_paid    = floatval($_POST['amount_paid']);
    $payment_date   = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'];

    if ($user_id <= 0 || $event_id <= 0 || $amount_paid <= 0 || $payment_date === '') {
        $error = "All fields are required and amount must be > 0.";
    } else {
        $chk = $conn->query("SELECT id FROM registrations WHERE user_id=$user_id AND event_id=$event_id");
        if ($chk->num_rows == 0) {
            $error = "This user is not registered for the selected event.";
        } else {
            $stmt = $conn->prepare("INSERT INTO payments (user_id, event_id, amount_paid, payment_date, payment_method) VALUES (?,?,?,?,?)");
            $stmt->bind_param("iidss", $user_id, $event_id, $amount_paid, $payment_date, $payment_method);
            if ($stmt->execute()) $success = "Payment recorded successfully!";
            else $error = "Failed to save payment: " . $conn->error;
            $stmt->close();
        }
    }
}

// DELETE PAYMENT
if (isset($_GET['delete'])) {
    $pid = (int)$_GET['delete'];
    $conn->query("DELETE FROM payments WHERE id = $pid");
    $success = "Payment deleted.";
}

// FILTER
$filter_event  = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$filter_status = $_GET['filter_status'] ?? '';
$filter_method = $_GET['filter_method'] ?? '';
$search        = trim($_GET['search'] ?? '');

// EVENTS & USERS — only paid events in dropdowns
$event_list = $conn->query("SELECT id, event_name FROM events WHERE is_free = 0 ORDER BY event_date DESC");
$user_list  = $conn->query("
    SELECT DISTINCT u.id, u.name 
    FROM users u
    JOIN registrations r ON r.user_id = u.id
    WHERE u.role='member'
    ORDER BY u.name ASC
");

// FILTER QUERY
$where_parts = ["1=1"];
if ($filter_event > 0)    $where_parts[] = "e.id = $filter_event";
if ($filter_method !== '') 
    $where_parts[] = "EXISTS (
        SELECT 1 
        FROM payments p
        WHERE p.user_id = r.user_id
        AND p.event_id = r.event_id
        AND p.payment_method = '".addslashes($filter_method)."'
    )";
if ($search !== '')        $where_parts[] = "u.name LIKE '%".addslashes($search)."%'";
$where_sql = implode(" AND ", $where_parts);

// SUMMARY QUERY
$summary_query = "
    WITH payment_totals AS (
        SELECT user_id, event_id, 
               SUM(amount_paid) AS total_paid,
               COUNT(*) AS payment_count,
               MAX(payment_date) AS last_payment
        FROM payments
        GROUP BY user_id, event_id
    )
    SELECT 
        u.id AS user_id, u.name, u.email,
        e.id AS event_id, e.event_name, e.fee, e.is_free,
        COALESCE(pt.total_paid, 0) AS total_paid,
        e.fee - COALESCE(pt.total_paid, 0) AS balance,
        COALESCE(pt.payment_count, 0) AS payment_count,
        pt.last_payment,
        CASE 
            WHEN pt.total_paid IS NULL OR pt.total_paid = 0 THEN 'Unpaid'
            WHEN pt.total_paid < e.fee THEN 'Partial'
            ELSE 'Paid'
        END AS payment_status
    FROM registrations r
    JOIN users u ON u.id = r.user_id
    JOIN events e ON e.id = r.event_id
    LEFT JOIN payment_totals pt ON pt.user_id = r.user_id AND pt.event_id = r.event_id
    WHERE $where_sql AND e.is_free = 0
    ORDER BY u.name, e.event_name
";
$summary = $conn->query($summary_query);

// HISTORY
$history = $conn->query("
    SELECT p.*, u.name AS user_name, e.event_name
    FROM payments p
    JOIN users u ON u.id = p.user_id
    JOIN events e ON e.id = p.event_id
    ORDER BY p.payment_date DESC
");

// TOTALS
$totals = $conn->query("
    SELECT 
        COUNT(DISTINCT CONCAT(r.user_id,'-',r.event_id)) AS total_entries,
        SUM(e.fee) AS total_expected,
        COALESCE(SUM(p.amount_paid), 0) AS total_collected,
        SUM(e.fee) - COALESCE(SUM(p.amount_paid), 0) AS total_balance
    FROM registrations r
    JOIN events e ON e.id = r.event_id AND e.is_free = 0
    LEFT JOIN payments p ON p.user_id = r.user_id AND p.event_id = r.event_id
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payments</title>
<link rel="stylesheet" href="admin_dashboard.css">
<style>
/* ── Payments-specific only ── */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)) auto;
    gap: 10px;
    align-items: end;
}
.form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.form-group label {
    font-size: 11px;
    font-weight: 600;
    color: #7aafc8;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.form-group select,
.form-group input {
    padding: 9px 12px;
    border: 1px solid #c8e3f3;
    border-radius: 8px;
    font-size: 13px;
    color: #334;
    background: #f5f9fd;
    outline: none;
    transition: border-color 0.2s, background 0.2s;
    font-family: inherit;
}
.form-group select:focus,
.form-group input:focus {
    border-color: #2980b9;
    background: #fff;
}
.status-paid    { background: #e6f4ea; color: #2d7a45; }
.status-partial { background: #fff3e0; color: #b36200; }
.status-unpaid  { background: #fdecea; color: #c0392b; }
.method-badge {
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    background: #e8f4fd;
    color: #1e5080;
    display: inline-block;
}
a.view-link {
    color: #2980b9;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
}
a.view-link:hover { text-decoration: underline; }
.btn-danger { background: #e05a2b; }
.btn-danger:hover { background: #b84520; }
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>ADMIN</h2>
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="events.php">Events</a>
    <a href="attendance.php">Attendance</a>
    <a href="payments.php" class="active">Payments</a>
    <a href="reports.php">Reports</a>
    <a href="notifications.php">Notifications</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<!-- MAIN -->
<div class="main">

<!-- HEADER -->
<div class="header">
    <h1>Payment Management</h1>
    <div class="header-right">
        <span>Welcome back, Admin</span>
        <div class="avatar">A</div>
    </div>
</div>

<div class="container">

<?php if ($success): ?>
<div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- SUMMARY CARDS -->
<div class="cards">
    <div class="card">
        <h3>Total Entries</h3>
        <p><?= number_format(floatval($totals['total_entries'])) ?></p>
        <div class="card-sub">Registered participants</div>
    </div>
    <div class="card">
        <h3>Expected</h3>
        <p>₱<?= number_format(floatval($totals['total_expected']), 2) ?></p>
        <div class="card-sub">Total fees due</div>
    </div>
    <div class="card">
        <h3>Collected</h3>
        <p style="color:#2980b9;">₱<?= number_format(floatval($totals['total_collected']), 2) ?></p>
        <div class="card-sub">Total payments received</div>
    </div>
    <div class="card">
        <h3>Remaining</h3>
        <p style="color:#e05a2b;">₱<?= number_format(floatval($totals['total_balance']), 2) ?></p>
        <div class="card-sub">Outstanding balance</div>
    </div>
</div>

<!-- ADD PAYMENT FORM -->
<div class="section">
    <h2>Add Payment</h2>
    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label>User</label>
                <select name="user_id" required>
                    <option value="">Select User</option>
                    <?php while($u = $user_list->fetch_assoc()): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Event</label>
                <select name="event_id" required>
                    <option value="">Select Event</option>
                    <?php while($e = $event_list->fetch_assoc()): ?>
                    <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['event_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Amount (₱)</label>
                <input type="number" name="amount_paid" placeholder="0.00" step="0.01" min="0.01" required>
            </div>
            <div class="form-group">
                <label>Payment Date</label>
                <input type="date" name="payment_date" required>
            </div>
            <div class="form-group">
                <label>Method</label>
                <select name="payment_method" required>
                    <option value="cash">Cash</option>
                    <option value="gcash">GCash</option>
                    <option value="bank">Bank</option>
                </select>
            </div>
            <div class="form-group" style="justify-content:flex-end;align-self:flex-end;">
                <button type="submit" name="add_payment" style="width:100%;padding:9px 16px;">Add Payment</button>
            </div>
        </div>
    </form>
</div>

<!-- PAYMENT SUMMARY TABLE -->
<div class="section">
    <div class="flex">
        <h2>Payment Summary</h2>
        <!-- Filter Form -->
        <form method="GET" style="display:flex;gap:10px;align-items:center;">
            <div class="search-bar" style="margin-bottom:0;">
                <input type="text" name="search" placeholder="Search by name…" value="<?= htmlspecialchars($search) ?>">
                <select name="filter_method">
                    <option value="">All Methods</option>
                    <option value="cash"  <?= $filter_method==='cash'  ? 'selected':'' ?>>Cash</option>
                    <option value="gcash" <?= $filter_method==='gcash' ? 'selected':'' ?>>GCash</option>
                    <option value="bank"  <?= $filter_method==='bank'  ? 'selected':'' ?>>Bank</option>
                </select>
                <button type="submit">Filter</button>
                <?php if ($search || $filter_method): ?>
                <a href="payments.php" class="btn btn-danger" style="padding:8px 14px;">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Event</th>
                <th>Fee</th>
                <th>Paid</th>
                <th>Balance</th>
                <th>Status</th>
                <th>Receipt</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $summary->fetch_assoc()):
            $statusClass = match($row['payment_status']) {
                'Paid'    => 'status-paid',
                'Partial' => 'status-partial',
                default   => 'status-unpaid',
            };
        ?>
        <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['event_name']) ?></td>
            <td>₱<?= number_format($row['fee'], 2) ?></td>
            <td>₱<?= number_format($row['total_paid'], 2) ?></td>
            <td>₱<?= number_format($row['balance'], 2) ?></td>
            <td>
                <span class="status-pill <?= $statusClass ?>">
                    <?= $row['payment_status'] ?>
                </span>
            </td>
            <td>
                <?php if ($row['payment_status'] === 'Paid'): ?>
                <a class="view-link" href="receipt.php?user_id=<?= $row['user_id'] ?>&event_id=<?= $row['event_id'] ?>" target="_blank">View</a>
                <?php else: ?>
                <span style="color:#bbb;">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- TRANSACTION HISTORY -->
<div class="section">
    <h2>Transaction History</h2>
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Event</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php while($h = $history->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($h['user_name']) ?></td>
            <td><?= htmlspecialchars($h['event_name']) ?></td>
            <td>₱<?= number_format($h['amount_paid'], 2) ?></td>
            <td><span class="method-badge"><?= ucfirst($h['payment_method']) ?></span></td>
            <td><?= htmlspecialchars($h['payment_date']) ?></td>
            <td>
                <a href="payments.php?delete=<?= $h['id'] ?>"
                   class="btn btn-danger"
                   style="font-size:12px;padding:5px 12px;"
                   onclick="return confirm('Delete this payment record?')">
                   Delete
                </a>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

</div><!-- /container -->
</div><!-- /main -->

</body>
</html>