<?php
session_start();
require '../config.php';

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input    = trim($_POST['phone']);
    $password = $_POST['password'] ?? '';

    // 1. Normalize Phone Input
    // Remove non-numeric characters
    $digits = preg_replace('/[^0-9]/', '', $input);

    $zero = '';
    $e164 = '';

    // Logic to generate both 07xx and 2547xx formats for querying
    if (strlen($digits) === 9) { // e.g. 722000000
        $zero = '0' . $digits;
        $e164 = '254' . $digits;
    } elseif (strlen($digits) === 10 && substr($digits, 0, 1) === '0') { // e.g. 0722000000
        $zero = $digits;
        $e164 = '254' . substr($digits, 1);
    } elseif (strlen($digits) === 12 && substr($digits, 0, 3) === '254') { // e.g. 254722000000
        $zero = '0' . substr($digits, 3);
        $e164 = $digits;
    } else {
        $error = "Invalid phone number format.";
        goto show_form;
    }

    // 2. Throttle repeated failures (per session)
    if (($_SESSION['admin_lock_until'] ?? 0) > time()) {
        $error = "Too many failed attempts. Try again in a few minutes.";
        goto show_form;
    }

    // 3. Check Admin Status in DB and verify the admin's own password
    $stmt = $pdo->prepare("SELECT id, name, phone, password_hash FROM members WHERE (phone = ? OR phone = ?) AND is_admin = 1 AND status = 'active' LIMIT 1");
    $stmt->execute([$zero, $e164]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        unset($_SESSION['admin_fails'], $_SESSION['admin_lock_until']);
        session_regenerate_id(true);
        // Success
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id']        = $admin['id'];
        $_SESSION['admin_name']      = $admin['name'];
        $_SESSION['admin_phone']     = $admin['phone'];

        // Log the access
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $pdo->prepare("INSERT INTO admin_logs (member_id, name, phone, ip_address, user_agent, login_time) VALUES (?, ?, ?, ?, ?, NOW())")
            ->execute([$admin['id'], $admin['name'], $admin['phone'], $ip, $ua]);

        header('Location: dashboard.php');
        exit;
    } else {
        $_SESSION['admin_fails'] = ($_SESSION['admin_fails'] ?? 0) + 1;
        if ($_SESSION['admin_fails'] >= 5) {
            $_SESSION['admin_lock_until'] = time() + 15 * 60;
            $_SESSION['admin_fails'] = 0;
        }
        // Same message for unknown phone, non-admin and wrong password
        $error = "Access Denied: Incorrect phone number or password.";
    }
}

show_form:
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal • Jera Moyie</title>
    <link rel="icon" href="../images/jm.ico">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        :root {
            --primary: #2c7a4b;
            --primary-dark: #1e5a38;
            --accent: #f59e0b;
            --bg-dark: #111827;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            background-image: radial-gradient(at 0% 0%, rgba(44, 122, 75, 0.3) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(245, 158, 11, 0.1) 0px, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            color: #fff;
        }

        .home-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .home-btn:hover {
            background: var(--primary);
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            color: #333;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }

        .admin-icon {
            width: 70px;
            height: 70px;
            background: #e8f5e9;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.5rem;
            box-shadow: inset 0 0 0 4px rgba(44, 122, 75, 0.1);
        }

        h2 {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
        }

        /* Floating Inputs */
        .form-floating>.form-control {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            background: #f9fafb;
        }

        .form-floating>.form-control:focus {
            background: white;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(44, 122, 75, 0.1);
        }

        .form-floating>label {
            color: #6c757d;
        }

        /* Password Toggle */
        .password-toggle {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 5;
        }

        /* Button */
        .btn-login {
            background: var(--primary-dark);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 1rem;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login:hover {
            background: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(44, 122, 75, 0.2);
        }

        .spinner-border {
            width: 1.2rem;
            height: 1.2rem;
            border-width: 2px;
            display: none;
        }
    </style>
</head>

<body>

    <a href="../index.html" class="home-btn" title="Back to Website">
        <i class="bi bi-arrow-left"></i>
    </a>

    <div class="login-card" data-aos="zoom-in" data-aos-duration="600">

        <div class="text-center">
            <div class="admin-icon">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h2>Admin Portal</h2>
            <p class="text-muted small mb-4">Restricted Access • Authorized Personnel Only</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center py-2 small rounded-3 border-0 bg-danger-subtle text-danger mb-4">
                <i class="bi bi-shield-slash-fill me-2 fs-5"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">

            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="phoneInput" name="phone" placeholder="07..." required>
                <label for="phoneInput">Admin Phone Number</label>
            </div>

            <div class="form-floating mb-4 position-relative">
                <input type="password" class="form-control" id="passwordInput" name="password" placeholder="Password" required>
                <label for="passwordInput">Your Password</label>
                <i class="bi bi-eye-slash password-toggle" onclick="togglePass()"></i>
            </div>

            <button type="submit" class="btn btn-login" id="loginBtn">
                <span>Authenticate</span>
                <div class="spinner-border text-white" role="status"></div>
            </button>

        </form>

        <div class="text-center mt-4">
            <small class="text-muted" style="font-size: 0.75rem;">
                <i class="bi bi-lock-fill me-1 text-success"></i> Connection Encrypted
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();

        // Password Toggle
        function togglePass() {
            const input = document.getElementById('passwordInput');
            const icon = document.querySelector('.password-toggle');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            }
        }

        // Form Loading State
        const form = document.getElementById('loginForm');
        const btn = document.getElementById('loginBtn');
        const btnText = btn.querySelector('span');
        const spinner = btn.querySelector('.spinner-border');

        form.addEventListener('submit', function() {
            btn.classList.add('disabled');
            btnText.textContent = 'Verifying Credentials...';
            spinner.style.display = 'block';
        });
    </script>
</body>

</html>