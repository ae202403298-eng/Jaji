<?php
require_once 'database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    } else {

        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!$user) {
            $errors[] = 'Invalid email or password.';
        } else {

            if ($user['email_verified'] == 0) {
                $errors[] = 'Please verify your email first.';
            } else {

                if (!password_verify($password, $user['password'])) {
                    $errors[] = 'Invalid email or password.';
                } else {

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['surname'];

                    header('Location: user_dashboard.php');
                    exit;
                }
            }
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Login</title>
    <link rel="stylesheet" href="user_login.css">
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">

        <div class="login-logo">
            <div class="logo-icon">U</div>
            <h2>Welcome Back</h2>
            <p>Sign in to your account</p>
        </div>

        <?php if (isset($_GET['success']) && $_GET['success'] === 'verified'): ?>
            <div class="success-msg">Account verified! You can now login.</div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <ul class="error-list">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="login-btn">Login</button>
        </form>

        <div class="login-links">
            <p>Don't have an account? <a href="user_register.php">Register</a></p>
            <p>Admin? <a href="adminlogin.php">Login here</a></p>
        </div>

    </div>
</div>

</body>
</html>