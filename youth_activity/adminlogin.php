<?php
session_start();
require_once "database.php";

// REDIRECT IF ALREADY LOGGED IN
if (isset($_SESSION['admin_id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// LOGIN LOGIC
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if ($password === $user['password']) {

            // IMPORTANT FIX
            session_unset();

            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_name'] = $user['name'];

            header("Location: admin_dashboard.php");
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "Admin not found!";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link rel="stylesheet" href="adminlogin.css">
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">

        <div class="login-logo">
            <div class="logo-icon">A</div>
            <h2>Admin Login</h2>
            <p>Sign in to your dashboard</p>
        </div>

        <?php if (isset($error)) { ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php } ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="admin@example.com" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit" name="login" class="login-btn">Login</button>
        </form>

        <div class="login-footer">Admin access only</div>

    </div>
</div>

</body>
</html>