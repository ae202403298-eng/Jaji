<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "youth_activity_db";

$conn = new mysqli($host, $user, $pass, $db);

// CHECK CONNECTION
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$conn->set_charset("utf8mb4");
?>