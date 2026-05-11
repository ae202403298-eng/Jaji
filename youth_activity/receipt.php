<?php
require_once 'database.php';

$user_id = (int)($_GET['user_id'] ?? 0);
$event_id = (int)($_GET['event_id'] ?? 0);

$data = $conn->query("
    SELECT u.name, e.event_name, e.fee,
           SUM(p.amount_paid) AS total_paid
    FROM payments p
    JOIN users u ON u.id = p.user_id
    JOIN events e ON e.id = p.event_id
    WHERE p.user_id = $user_id AND p.event_id = $event_id
")->fetch_assoc();

$payments = $conn->query("
    SELECT * FROM payments
    WHERE user_id = $user_id AND event_id = $event_id
    ORDER BY payment_date ASC
");

$balance = $data['fee'] - $data['total_paid'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Receipt</title>
<style>
body { font-family: Arial; padding:30px; background:#f5f5f5; }
.receipt {
    background:#fff;
    border:1px solid #ccc;
    padding:20px;
    max-width:500px;
    margin:auto;
    border-radius:10px;
}
h2 { text-align:center; }
table { width:100%; margin-top:10px; border-collapse:collapse; }
td, th { padding:8px; border-bottom:1px solid #ddd; text-align:left; }
.total { font-weight:bold; }
.print-btn {
    margin-top:15px;
    width:100%;
    padding:10px;
    background:#27ae60;
    color:#fff;
    border:none;
    border-radius:5px;
    cursor:pointer;
}
</style>
</head>
<body>

<div class="receipt">
<h2>Payment Receipt</h2>

<p><strong>Name:</strong> <?= htmlspecialchars($data['name']) ?></p>
<p><strong>Event:</strong> <?= htmlspecialchars($data['event_name']) ?></p>

<hr>

<table>
<tr><th>Date</th><th>Method</th><th>Amount</th></tr>

<?php while($p = $payments->fetch_assoc()): ?>
<tr>
    <td><?= $p['payment_date'] ?></td>
    <td><?= strtoupper($p['payment_method']) ?></td>
    <td>₱<?= number_format($p['amount_paid'],2) ?></td>
</tr>
<?php endwhile; ?>

<tr class="total">
    <td colspan="2">Total Paid</td>
    <td>₱<?= number_format($data['total_paid'],2) ?></td>
</tr>

<tr class="total">
    <td colspan="2">Balance</td>
    <td>₱<?= number_format($balance,2) ?></td>
</tr>

<tr class="total">
    <td colspan="2">Status</td>
    <td><?= $balance <= 0 ? 'PAID' : 'PARTIAL' ?></td>
</tr>
</table>

<button onclick="window.print()" class="print-btn">Print Receipt</button>

</div>

</body>
</html>