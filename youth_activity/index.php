<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Youth Activity Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            background: #f4f7fb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1a3a5c;
        }

        .landing-container {
            text-align: center;
            padding: 40px 24px;
            width: 100%;
            max-width: 680px;
        }

        /* ── Header ── */
        .landing-logo {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #2980b9, #6bb3d9);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .landing-logo svg {
            width: 28px;
            height: 28px;
            fill: #fff;
        }

        .landing-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a3a5c;
            margin-bottom: 6px;
        }

        .landing-subtitle {
            font-size: 14px;
            color: #7aafc8;
            margin-bottom: 32px;
        }

        /* ── Description ── */
        .landing-desc {
            max-width: 520px;
            margin: 0 auto 40px;
            font-size: 14px;
            line-height: 1.7;
            color: #5a7a90;
            padding: 0 8px;
        }

        .features {
            display: flex;
            justify-content: center;
            gap: 24px;
            flex-wrap: wrap;
            margin-top: 16px;
        }

        .feature-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 500;
            color: #2980b9;
            background: #eaf3fb;
            padding: 6px 14px;
            border-radius: 20px;
        }

        .feature-tag svg {
            width: 14px;
            height: 14px;
            fill: #2980b9;
        }

        /* ── Cards grid ── */
        .cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .card {
            background: #fff;
            border: 1px solid #dce8f0;
            border-radius: 14px;
            padding: 40px 24px 36px;
            text-decoration: none;
            color: inherit;
            transition: border-color 0.25s, box-shadow 0.25s, transform 0.15s;
        }

        .card:hover {
            border-color: #2980b9;
            box-shadow: 0 4px 20px rgba(41, 128, 185, 0.1);
            transform: translateY(-2px);
        }

        .card:active {
            transform: translateY(0);
        }

        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .card-icon svg {
            width: 24px;
            height: 24px;
        }

        .card-icon.user {
            background: #eaf3fb;
        }

        .card-icon.user svg {
            fill: #2980b9;
        }

        .card-icon.admin {
            background: #f0eafb;
        }

        .card-icon.admin svg {
            fill: #7c3aed;
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .card-desc {
            font-size: 13px;
            color: #8ea4b8;
            line-height: 1.5;
        }

        /* ── Footer ── */
        .landing-footer {
            margin-top: 48px;
            font-size: 12px;
            color: #b0c4d4;
        }

        /* ── Responsive ── */
        @media (max-width: 480px) {
            .cards {
                grid-template-columns: 1fr;
            }

            .landing-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>

<div class="landing-container">

    <!-- Logo -->
    <div class="landing-logo">
        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
    </div>

    <h1 class="landing-title">Youth Activity Management</h1>
    <p class="landing-subtitle">Select your portal to continue</p>

    <!-- Description -->
    <div class="landing-desc">
        A streamlined platform for youth organizations to create and manage events,
        track member attendance, and handle payments — all in one place.

        <div class="features">
            <span class="feature-tag">
                <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
                Event Management
            </span>
            <span class="feature-tag">
                <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                Attendance Tracking
            </span>
            <span class="feature-tag">
                <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
                Payment Processing
            </span>
        </div>
    </div>

    <!-- Cards -->
    <div class="cards">

        <a href="user_login.php" class="card" id="card-user">
            <div class="card-icon user">
                <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </div>
            <div class="card-title">User</div>
            <p class="card-desc">Login or register to access events and activities</p>
        </a>

        <a href="adminlogin.php" class="card" id="card-admin">
            <div class="card-icon admin">
                <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
            </div>
            <div class="card-title">Admin</div>
            <p class="card-desc">Manage events, users, and system settings</p>
        </a>

    </div>

    <p class="landing-footer">&copy; <?php echo date('Y'); ?> Youth Activity Management System</p>

</div>

</body>
</html>
