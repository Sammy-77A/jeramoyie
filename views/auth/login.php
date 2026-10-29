<?php
/**
 * Login View — Standalone (no layout).
 * Preserves exact UI from original login.php.
 * Variables: $error, $csrf
 */
$basePath = config('app.base_path', '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Jera Moyie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c7a4b;
            --primary-hover: #1e5a38;
            --accent: #f59e0b;
            --text-dark: #343a40;
            --bg-light: #f3f4f6;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            background-image: radial-gradient(at 0% 0%, rgba(44, 122, 75, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(245, 158, 11, 0.05) 0px, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .login-card {
            background: white;
            border: none;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 420px;
            padding: 2.5rem;
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

        .logo-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 50%;
            padding: 5px;
            border: 2px solid #f0f0f0;
            margin-bottom: 1.5rem;
        }

        h2 {
            font-family: 'Playfair Display', serif;
            color: var(--text-dark);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: #6c757d;
            font-size: 0.95rem;
            margin-bottom: 2rem;
        }

        .form-floating>.form-control {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
        }

        .form-floating>.form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(44, 122, 75, 0.1);
        }

        .btn-login {
            background: var(--primary);
            border: none;
            color: white;
            font-weight: 600;
            padding: 1rem;
            border-radius: 12px;
            width: 100%;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(44, 122, 75, 0.15);
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 5;
        }

        .login-nav {
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .login-nav .nav-brand {
            color: var(--text-dark);
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .login-nav .nav-brand:hover {
            color: var(--primary);
        }

        .login-nav .nav-link-custom {
            color: var(--primary);
            font-weight: 500;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            transition: all 0.2s;
        }

        .login-nav .nav-link-custom:hover {
            background: rgba(44, 122, 75, 0.1);
            color: var(--primary-hover);
        }

        .login-nav .nav-link-custom.btn-reg {
            background: var(--primary);
            color: white !important;
        }

        .login-nav .nav-link-custom.btn-reg:hover {
            background: var(--primary-hover);
            color: white !important;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg fixed-top login-nav py-3">
        <div class="container">
            <a class="nav-brand" href="<?= $basePath ?>/"><i class="bi bi-chevron-left me-1"></i> Home</a>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <a href="<?= $basePath ?>/community/login.php" class="nav-link-custom d-none d-sm-inline-block"><i
                        class="bi bi-people me-1"></i> Community</a>
                <a href="<?= $basePath ?>/signup" class="nav-link-custom btn-reg px-3"><i
                        class="bi bi-person-plus me-1"></i> Create Account</a>
            </div>
        </div>
    </nav>

    <div class="login-card" data-aos="fade-up" style="margin-top: 5rem;">
        <div class="text-center">
            <img src="<?= $basePath ?>/images/jm_logo.jpg" alt="Logo" class="logo-img"
                onerror="this.src='https://ui-avatars.com/api/?name=JM&background=2c7a4b&color=fff&size=80'">
            <h2>Welcome Back</h2>
            <p class="subtitle">Securely login to your Jera Moyie account</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small rounded-3 border-0 bg-danger-subtle text-danger mb-4">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm" action="<?= $basePath ?>/login">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">

            <div class="form-floating mb-3">
                <input type="tel" class="form-control" id="phoneInput" name="phone" placeholder="07..." required>
                <label for="phoneInput">Phone Number</label>
            </div>

            <div class="form-floating mb-2 position-relative">
                <input type="password" class="form-control" id="passwordInput" name="password" placeholder="Password"
                    required>
                <label for="passwordInput">Password</label>
                <i class="bi bi-eye-slash password-toggle" id="togglePassword"></i>
            </div>

            <div class="d-flex justify-content-end mb-4">
                <a href="<?= $basePath ?>/forgot-password"
                    class="text-decoration-none small text-success fw-bold">Forgot Password?</a>
            </div>

            <button type="submit" id="loginBtn" class="btn btn-login">
                <span>Sign In</span>
                <div class="spinner-border text-white spinner-border-sm" id="loginSpinner" role="status"
                    style="display:none;"></div>
            </button>
        </form>

        <div class="text-center mt-4">
            <p class="small text-muted mb-1">New to Jera Moyie?</p>
            <a href="<?= $basePath ?>/signup" class="text-decoration-none fw-bold text-success">Create an Account</a>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#passwordInput');
        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('bi-eye');
            this.classList.toggle('bi-eye-slash');
        });
        document.getElementById('loginForm').onsubmit = function () {
            document.getElementById('loginSpinner').style.display = 'inline-block';
            document.getElementById('loginBtn').classList.add('disabled');
        };
    </script>
</body>

</html>