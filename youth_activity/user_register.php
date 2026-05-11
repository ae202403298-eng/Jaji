<?php
require_once 'database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name     = trim($_POST['first_name'] ?? '');
    $middle_initial = strtoupper(trim($_POST['middle_initial'] ?? ''));
    $surname        = trim($_POST['surname'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $password       = $_POST['password'] ?? '';

    if ($first_name === '' || $surname === '') {
        $errors[] = "First name and surname are required.";
    }

    if ($middle_initial !== '' && !preg_match("/^[A-Z]$/", $middle_initial)) {
        $errors[] = "Middle initial must be one letter only.";
    }

    if ($email === '' || $password === '') {
        $errors[] = "Email and password are required.";
    }

    // CHECK EMAIL
    if (empty($errors)) {

        $stmt = $conn->prepare(
            "SELECT id FROM users WHERE email = ?"
        );

        $stmt->bind_param("s", $email);

        $stmt->execute();

        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = "Email already registered.";
        }

        $stmt->close();
    }

    // SEND EMAIL
    if (empty($errors)) {

        require_once 'Emailverification.php';

        $full_name = $first_name . ' ' . $surname;

        $emailVerification = new EmailVerification();

        $pin_code = '';

        // SEND EMAIL
        $emailVerification->sendVerificationEmail(
            $email,
            $full_name,
            $pin_code
        );

        // STORE SESSION
        $_SESSION['verification'] = true;
        $_SESSION['pin_code'] = $pin_code;

        $_SESSION['first_name'] = $first_name;
        $_SESSION['middle_initial'] = $middle_initial;
        $_SESSION['surname'] = $surname;
        $_SESSION['email'] = $email;
        $_SESSION['password'] = $password;

        // GO TO VERIFICATION PAGE
        header("Location: verification.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Register</title>

    <!-- KEEP CSS -->
    <link rel="stylesheet" href="user_register.css">

</head>
<body>

<div class="register-wrapper">

    <div class="register-card">

        <div class="register-logo">
            <div class="logo-icon">U</div>

            <h2>Create Account</h2>

            <p>Register to get started</p>
        </div>

        <?php if (!empty($errors)): ?>

            <ul class="error-list">

                <?php foreach ($errors as $e): ?>

                    <li><?= htmlspecialchars($e) ?></li>

                <?php endforeach; ?>

            </ul>

        <?php endif; ?>

        <form method="POST">

            <div class="form-row">

                <div class="form-group">

                    <label for="first_name">
                        First Name
                    </label>

                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        placeholder="Juan"
                        value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                        required
                    >

                </div>

                <div class="form-group mi">

                    <label for="middle_initial">
                        M.I.
                    </label>

                    <input
                        type="text"
                        id="middle_initial"
                        name="middle_initial"
                        placeholder="A"
                        maxlength="1"
                        value="<?= htmlspecialchars($_POST['middle_initial'] ?? '') ?>"
                    >

                </div>

            </div>

            <div class="form-group">

                <label for="surname">
                    Surname
                </label>

                <input
                    type="text"
                    id="surname"
                    name="surname"
                    placeholder="Dela Cruz"
                    value="<?= htmlspecialchars($_POST['surname'] ?? '') ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label for="email">
                    Email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="you@example.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    required
                >

            </div>

            <button
                type="submit"
                name="register"
                class="register-btn"
            >
                Register
            </button>

        </form>

        <div class="register-links">

            <p>
                Already have an account?
                <a href="user_login.php">
                    Login
                </a>
            </p>

            <p>
                Admin?
                <a href="adminlogin.php">
                    Login here
                </a>
            </p>

        </div>

    </div>

</div>

</body>
</html>