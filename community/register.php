<?php
require_once __DIR__ . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/mpesa_verify.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/validators.php';
require_once __DIR__ . '/models/CommunityCustomer.php';

if (isset($_SESSION['community_customer_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$customerModel = new CommunityCustomer($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Session expired. Please try again.';
    } else {
        $full_name = sanitize_string($_POST['full_name'] ?? '');
        $rawPhone = trim($_POST['phone'] ?? '');
        $phone = validate_phone($rawPhone);
        $national_id = validate_national_id($_POST['national_id'] ?? '');
        $email_raw = trim($_POST['email'] ?? '');
        $email = $email_raw ? validate_email($email_raw) : null;
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        // Validation
        if (!$full_name || strlen($full_name) < 3)
            $error = 'Please enter a valid full name (at least 3 characters).';
        elseif (!$phone)
            $error = 'Please enter a valid Kenyan phone number (07xxxxxxxx).';
        elseif (!$national_id)
            $error = 'Please enter a valid National ID (7-10 digits).';
        elseif ($email_raw && !$email)
            $error = 'Please enter a valid email address.';
        elseif (strlen($password) < 6)
            $error = 'Password must be at least 6 characters.';
        elseif ($password !== $password_confirm)
            $error = 'Passwords do not match.';
        elseif ($customerModel->phoneOrIdExists($phone, $national_id))
            $error = 'Phone number or National ID already registered.';
        else {
            // Normalize phone from 07xx to 2547xx for M-PESA
            $mpesaPhone = preg_replace('/^0/', '254', $rawPhone);

            // Clear any old pending for same phone/ID
            $pdo->prepare("DELETE FROM community_pending_registrations WHERE phone_number = ? OR national_id = ?")->execute([$phone, $national_id]);

            // Insert into community_pending_registrations
            $token = bin2hex(random_bytes(16));
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO community_pending_registrations (token, full_name, phone_number, national_id, email, password_hash) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$token, $full_name, $phone, $national_id, $email, $hash]);

            // ========================================================
            //  INLINE STK PUSH — adapted from stk_initiate.php
            // ========================================================
            date_default_timezone_set('Africa/Nairobi');

            // Load M-PESA credentials
            $envFile = __DIR__ . '/../.env.php';
            if (file_exists($envFile))
                require_once $envFile;

            $consumerKey = getenv('MPESA_CONSUMER_KEY');
            $consumerSecret = getenv('MPESA_CONSUMER_SECRET');
            $BusinessShortCode = getenv('MPESA_SHORTCODE');
            $Passkey = getenv('MPESA_PASSKEY');
            $Amount = (float)(getenv('MPESA_REG_FEE') ?: 200);
            $CallBackURL = 'https://jeramy1.top/callback_url.php';

            $stkError = null; // Track STK errors

            if (!$consumerKey || !$consumerSecret) {
                $stkError = 'System configuration error. Please contact admin.';
            }

            // Step 1: Get OAuth Access Token
            if (!$stkError) {
                $tokenUrl = mpesa_base_url() . '/oauth/v1/generate?grant_type=client_credentials';
                $ch = curl_init($tokenUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_USERPWD => "$consumerKey:$consumerSecret",
                    CURLOPT_TIMEOUT => 30,
                ]);
                $tokenResp = curl_exec($ch);
                $tokenCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr = curl_error($ch);
                curl_close($ch);

                if ($curlErr || $tokenCode !== 200) {
                    $stkError = 'M-PESA connection failed. Please try again later.';
                    error_log("Community STK Token Error: HTTP $tokenCode, Err: $curlErr");
                } else {
                    $access_token = json_decode($tokenResp, true)['access_token'] ?? null;
                    if (!$access_token) {
                        $stkError = 'M-PESA authentication failed. Please try again.';
                    }
                }
            }

            // Step 2: Send STK Push
            if (!$stkError) {
                $Timestamp = date('YmdHis');
                $Password = base64_encode($BusinessShortCode . $Passkey . $Timestamp);

                $payload = [
                    'BusinessShortCode' => $BusinessShortCode,
                    'Password' => $Password,
                    'Timestamp' => $Timestamp,
                    'TransactionType' => 'CustomerPayBillOnline',
                    'Amount' => $Amount,
                    'PartyA' => $mpesaPhone,
                    'PartyB' => $BusinessShortCode,
                    'PhoneNumber' => $mpesaPhone,
                    'CallBackURL' => mpesa_callback_url($CallBackURL, ['token' => $token]),
                    'AccountReference' => 'JERAMOYIE',
                    'TransactionDesc' => 'Community Registration'
                ];

                $stkUrl = mpesa_base_url() . '/mpesa/stkpush/v1/processrequest';
                $ch = curl_init($stkUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $access_token
                    ],
                    CURLOPT_TIMEOUT => 30,
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr = curl_error($ch);
                curl_close($ch);

                if ($curlErr || $httpCode !== 200) {
                    $stkError = 'Failed to send payment request. Please try again.';
                    error_log("Community STK Push Error: HTTP $httpCode, Err: $curlErr");
                } else {
                    $resp = json_decode($response, true);
                    if (!isset($resp['ResponseCode']) || $resp['ResponseCode'] !== '0') {
                        $stkError = 'Payment request failed. Please try again.';
                        error_log("Community STK Response Error: " . json_encode($resp));
                    }
                }
            }

            // Step 3: Handle result
            if ($stkError) {
                // Clean up pending on failure
                $pdo->prepare("DELETE FROM community_pending_registrations WHERE token = ?")->execute([$token]);
                $error = $stkError;
            } else {
                // Success — redirect to pending page
                if (!empty($checkoutRequestId)) {
                    $pdo->prepare("UPDATE community_pending_registrations SET checkout_request_id = ? WHERE token = ?")
                        ->execute([$checkoutRequestId, $token]);
                }
                $_SESSION['community_pending_token'] = $token;
                $_SESSION['community_pending_phone'] = $phone;
                header('Location: pending.php');
                exit;
            }
        }
    }
}
$csrf = regenerate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Community &bull; Jera Moyie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary: #1a5928;
            --primary-hover: #145020;
            --accent: #f59e0b;
            --bg: #f3f4f6
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            background-image: radial-gradient(at 0% 0%, rgba(26, 89, 40, .05) 0, transparent 50%), radial-gradient(at 100% 100%, rgba(245, 158, 11, .05) 0, transparent 50%)
        }

        .home-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #fff;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .05);
            transition: all .3s;
            z-index: 10
        }

        .home-btn:hover {
            background: var(--primary);
            color: #fff;
            transform: translateY(-2px)
        }

        .auth-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .06);
            width: 100%;
            max-width: 500px;
            padding: 2.5rem;
            position: relative;
            overflow: hidden
        }

        .auth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--accent))
        }

        .logo-img {
            width: 70px;
            height: 70px;
            object-fit: contain;
            border-radius: 50%;
            padding: 4px;
            border: 2px solid #f0f0f0;
            margin-bottom: 1rem
        }

        h2 {
            font-family: 'Playfair Display', serif;
            color: #333;
            font-weight: 700
        }

        .form-floating>.form-control {
            border: 1px solid #e0e0e0;
            border-radius: 12px
        }

        .form-floating>.form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(26, 89, 40, .1)
        }

        .form-floating>label {
            color: #999
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 5
        }

        .btn-main {
            background: var(--primary);
            border: none;
            color: #fff;
            font-weight: 600;
            padding: 1rem;
            border-radius: 12px;
            width: 100%;
            transition: all .3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px
        }

        .btn-main:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(26, 89, 40, .15);
            color: #fff
        }

        .fee-badge {
            background: #fff8e1;
            color: #b45309;
            border: 1px solid #fcd34d;
            padding: .5rem 1rem;
            border-radius: 8px;
            font-size: .9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 1.5rem
        }

        .spinner-border {
            width: 1.2rem;
            height: 1.2rem;
            border-width: 2px;
            display: none
        }

        @media(max-width:576px) {
            .auth-card {
                padding: 1.5rem;
                border-radius: 18px
            }

            .form-floating>.form-control {
                font-size: .9rem
            }

            h2 {
                font-size: 1.5rem
            }

            .fee-badge {
                font-size: .8rem
            }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg fixed-top bg-white border-bottom shadow-sm py-2">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center text-dark" href="../index.html"><i
                    class="bi bi-chevron-left me-2"></i>Home</a>
            <div class="ms-auto d-flex align-items-center gap-2">
                <a href="login.php" class="btn btn-sm btn-outline-success rounded-pill px-3">Back to Login</a>
            </div>
        </div>
    </nav>
    <div style="padding-top: 4rem;">
        <a href="../index.html" class="home-btn d-none" title="Back to Home"><i class="bi bi-arrow-left"></i></a>

        <div class="auth-card" data-aos="fade-up" data-aos-duration="800">
            <div class="text-center">
                <img src="../images/jeramoyie_snp.png" alt="Logo" class="logo-img"
                    onerror="this.src='https://ui-avatars.com/api/?name=JM&background=2c7a4b&color=fff&size=70'">
                <h2>Join Jera Moyie</h2>
                <p class="text-muted small mb-2">Community Savings &amp; Loans Portal</p>
                <div class="fee-badge"><i class="bi bi-info-circle-fill"></i><span>Registration Fee: <strong>KSh
                            1</strong> via M-PESA</span></div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger small rounded-3 border-0 py-2 mb-3"><i
                        class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-info small rounded-3 border-0 py-2 mb-3"><i
                        class="bi bi-info-circle me-2"></i><?= htmlspecialchars($_GET['msg']) ?></div>
            <?php endif; ?>

            <form id="registerForm" method="POST" class="needs-validation" novalidate>
                <?= csrf_input() ?>

                <div class="form-floating mb-3">
                    <input type="text" class="form-control" name="full_name" id="full_name" placeholder="Full Name"
                        value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                    <label for="full_name">Full Name</label>
                    <div class="invalid-feedback">Please enter your full name.</div>
                </div>

                <div class="form-floating mb-3">
                    <input type="tel" class="form-control" name="phone" id="phoneInput" placeholder="07..."
                        value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                    <label for="phoneInput">Phone Number (Safaricom)</label>
                    <div class="invalid-feedback">Please enter a valid Kenyan number (07...).</div>
                </div>

                <div class="form-floating mb-3">
                    <input type="text" class="form-control" name="national_id" id="national_id" placeholder="12345678"
                        value="<?= htmlspecialchars($_POST['national_id'] ?? '') ?>" required>
                    <label for="national_id">National ID</label>
                </div>

                <div class="form-floating mb-3">
                    <input type="email" class="form-control" name="email" id="email" placeholder="Email"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <label for="email">Email (Optional)</label>
                </div>

                <div class="row g-2 mb-4">
                    <div class="col-6">
                        <div class="form-floating position-relative">
                            <input type="password" class="form-control" name="password" id="password" placeholder="Pass"
                                minlength="6" required>
                            <label for="password">Password</label>
                            <i class="bi bi-eye-slash password-toggle" onclick="togglePass('password', this)"></i>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-floating position-relative">
                            <input type="password" class="form-control" name="password_confirm" id="password_confirm"
                                placeholder="Confirm" required>
                            <label for="password_confirm">Confirm</label>
                            <i class="bi bi-eye-slash password-toggle"
                                onclick="togglePass('password_confirm', this)"></i>
                            <div class="invalid-feedback">Passwords do not match.</div>
                        </div>
                    </div>
                </div>

                <button type="submit" id="submitBtn" class="btn btn-main">
                    <span>Create Account &amp; Pay KSh 1</span>
                    <div class="spinner-border text-white" role="status"></div>
                </button>
            </form>

            <div class="text-center mt-4">
                <p class="small text-muted">Already have an account? <a href="login.php"
                        class="text-decoration-none fw-bold" style="color:var(--primary)">Sign In</a></p>
            </div>

            <div class="text-center mt-2">
                <small class="text-muted" style="font-size:.7rem"><i
                        class="bi bi-shield-lock-fill me-1 text-success"></i>Payments secured by M-PESA</small>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
        <script>
            AOS.init();
            function togglePass(id, icon) { const i = document.getElementById(id); i.type = i.type === 'password' ? 'text' : 'password'; icon.classList.toggle('bi-eye'); icon.classList.toggle('bi-eye-slash'); }

            const form = document.getElementById('registerForm');
            const phoneInput = document.getElementById('phoneInput');
            const pass1 = document.getElementById('password');
            const pass2 = document.getElementById('password_confirm');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = submitBtn.querySelector('span');
            const spinner = submitBtn.querySelector('.spinner-border');

            phoneInput.addEventListener('input', function () {
                let val = this.value.replace(/\D/g, '');
                if (val.startsWith('254')) val = '0' + val.substring(3);
                const isValid = /^0(7|1)\d{8}$/.test(val);
                if (isValid) { this.classList.remove('is-invalid'); this.classList.add('is-valid'); this.setCustomValidity(''); }
                else { this.classList.remove('is-valid'); this.classList.add('is-invalid'); this.setCustomValidity('Invalid phone'); }
            });

            function checkPasswords() {
                if (pass2.value && pass1.value !== pass2.value) { pass2.classList.add('is-invalid'); pass2.setCustomValidity('Mismatch'); }
                else { pass2.classList.remove('is-invalid'); pass2.setCustomValidity(''); }
            }
            pass1.addEventListener('input', checkPasswords);
            pass2.addEventListener('input', checkPasswords);

            form.addEventListener('submit', function (e) {
                checkPasswords();
                if (!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
                else { submitBtn.classList.add('disabled'); btnText.textContent = 'Processing Payment...'; spinner.style.display = 'block'; }
                form.classList.add('was-validated');
            });
        </script>
    </div>
</body>

</html>