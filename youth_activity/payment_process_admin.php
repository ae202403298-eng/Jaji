<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}

// APPROVE PAYMENT REQUEST
if (isset($_GET['approve'])) {

    $id = (int)$_GET['approve'];

    // get request
    $req = $conn->query("SELECT * FROM payment_requests WHERE id=$id")->fetch_assoc();

    if ($req && $req['status'] == 'pending') {

        // 1. insert to payments table
        $stmt = $conn->prepare("
            INSERT INTO payments (user_id, event_id, amount_paid, payment_date, payment_method)
            VALUES (?, ?, ?, CURDATE(), ?)
        ");
        $stmt->bind_param(
            "iids",
            $req['user_id'],
            $req['event_id'],
            $req['amount'],
            $req['method']
        );
        $stmt->execute();

        // 2. update request status
        $conn->query("UPDATE payment_requests SET status='approved' WHERE id=$id");
    }

    header("Location: payment_requests_admin.php");
    exit();
}


// REJECT PAYMENT REQUEST
if (isset($_GET['reject'])) {

    $id = (int)$_GET['reject'];

    $conn->query("
        UPDATE payment_requests 
        SET status='rejected' 
        WHERE id=$id
    ");

    header("Location: payment_requests_admin.php");
    exit();
}