<?php
session_start();
$is_admin = isset($_SESSION['admin_id']);
session_unset();
session_destroy();
if ($is_admin) {
    header("Location: adminlogin.php");
} else {
    header("Location: user_login.php");
}

exit();
?>