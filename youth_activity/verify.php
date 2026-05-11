<?php
require_once 'database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

// Make sure session data exists
if (!isset($_SESSION['verification']) || !isset($_SESSION['pin_code'])) {
    header("Location: user_register.php");
    exit;
}

$input_pin = $_POST['pin_code'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($input_pin == $_SESSION['pin_code']) {

        // HASH PASSWORD
        $hash = password_hash($_SESSION['password'], PASSWORD_DEFAULT);

        // Build full name for the name column
        $mi = $_SESSION['middle_initial'];
        $full_name = $_SESSION['first_name']
            . ($mi !== '' ? ' ' . $mi . '.' : '')
            . ' ' . $_SESSION['surname'];

        $stmt = $conn->prepare("
            INSERT INTO users
            (first_name, middle_initial, surname, name, email, password, email_verified)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");

        $stmt->bind_param(
            "ssssss",
            $_SESSION['first_name'],
            $_SESSION['middle_initial'],
            $_SESSION['surname'],
            $full_name,
            $_SESSION['email'],
            $hash
        );

        if ($stmt->execute()) {

            // CLEAR SESSION
            session_unset();
            session_destroy();

            // REDIRECT TO LOGIN
            header("Location: user_login.php?success=verified");
            exit();

        } else {
            $errors[] = "Database error: " . $stmt->error;
        }

    } else {
        $errors[] = "Incorrect PIN code.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Email Verification</title>
<link rel="stylesheet" href="verify.css">
</head>
<body>

<div class="verify-wrapper">
    <div class="verify-card">

        <div class="verify-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <polyline points="22,6 12,13 2,6"/>
            </svg>
        </div>

        <h2>Verify Your Email</h2>
        <p class="subtitle">We sent a 6-digit PIN to your email.<br>Enter it below to complete registration.</p>

        <?php if (!empty($errors)): ?>
            <?php foreach($errors as $e): ?>
                <div class="error-msg">
                    <?php echo htmlspecialchars($e); ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="pin_code">Verification PIN</label>
                <input type="text" id="pin_code" name="pin_code" placeholder="000000" maxlength="6" required>
            </div>

            <button type="submit" class="verify-btn">Verify</button>
        </form>

        <div class="verify-links">
            <p>Back to <a href="user_register.php">Register</a></p>
        </div>

    </div>
</div>

</body>
</html>
