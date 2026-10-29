<?php
require_once __DIR__ . '/../includes/guard.php';
require_cli();
/**
 * Foundation setup script — creates tables, security module, and navbar
 * Run once: php _foundation_setup.php
 */
$base = __DIR__;
require_once $base . '/includes/db.php';

// ============================================================
// 1. DATABASE TABLES
// ============================================================

$tables = [
    "CREATE TABLE IF NOT EXISTS community_pending_deposits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token VARCHAR(64) NOT NULL UNIQUE,
        customer_id INT NOT NULL,
        product_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_customer (customer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS community_pending_repayments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token VARCHAR(64) NOT NULL UNIQUE,
        customer_id INT NOT NULL,
        loan_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_customer (customer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS community_security_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_type VARCHAR(50) NOT NULL,
        customer_id INT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_event (event_type),
        INDEX idx_customer (customer_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS community_rate_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        action_type VARCHAR(50) NOT NULL,
        identifier VARCHAR(100) NOT NULL,
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_action_id (action_type, identifier),
        INDEX idx_time (attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($tables as $sql) {
    $pdo->exec($sql);
    preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $m);
    echo "✓ Table: {$m[1]}\n";
}

// ============================================================
// 2. SECURITY MODULE — community/includes/security.php
// ============================================================

$security_php = <<<'SECPHP'
<?php
/**
 * Community Security Module
 * Rate limiting, transaction velocity, IP logging, session hardening
 */

/**
 * Set security headers on every page
 */
function set_security_headers() {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data: https://ui-avatars.com;");
}

/**
 * Get client IP address reliably
 */
function get_client_ip() {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Rate limiter — returns TRUE if rate limit exceeded
 */
function rate_limit_exceeded($pdo, $action, $identifier, $max_attempts = 5, $window_minutes = 60) {
    // Clean old entries
    $pdo->prepare("DELETE FROM community_rate_limits WHERE action_type = ? AND identifier = ? AND attempted_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)")
        ->execute([$action, $identifier, $window_minutes]);

    // Count recent attempts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM community_rate_limits WHERE action_type = ? AND identifier = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->execute([$action, $identifier, $window_minutes]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= $max_attempts) return true;

    // Log this attempt
    $pdo->prepare("INSERT INTO community_rate_limits (action_type, identifier) VALUES (?, ?)")
        ->execute([$action, $identifier]);

    return false;
}

/**
 * Log security event
 */
function log_security_event($pdo, $event_type, $details = '', $customer_id = null) {
    $pdo->prepare("INSERT INTO community_security_log (event_type, customer_id, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?)")
        ->execute([
            $event_type,
            $customer_id,
            get_client_ip(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $details
        ]);
}

/**
 * Validate transaction velocity — prevent rapid-fire transactions
 * Returns error message or null if OK
 */
function check_transaction_velocity($pdo, $customer_id, $action_type, $max_per_hour = 5) {
    $identifier = "cust_{$customer_id}";
    if (rate_limit_exceeded($pdo, $action_type, $identifier, $max_per_hour, 60)) {
        log_security_event($pdo, 'velocity_exceeded', "Action: $action_type, Max: $max_per_hour/hr", $customer_id);
        return "Too many requests. Please wait before trying again (max $max_per_hour per hour).";
    }
    return null;
}

/**
 * Check for duplicate pending transaction (prevents double-tapping)
 */
function has_pending_transaction($pdo, $table, $customer_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE customer_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $stmt->execute([$customer_id]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Session fingerprinting — verify session integrity
 */
function verify_session_fingerprint() {
    $fingerprint = md5(($_SERVER['HTTP_USER_AGENT'] ?? '') . 'jm_salt_2024');

    if (!isset($_SESSION['_fingerprint'])) {
        $_SESSION['_fingerprint'] = $fingerprint;
    } elseif ($_SESSION['_fingerprint'] !== $fingerprint) {
        // Session hijacking detected
        session_destroy();
        header('Location: login.php?msg=' . urlencode('Session expired. Please log in again.'));
        exit;
    }

    // Regenerate session ID periodically (every 15 minutes)
    if (!isset($_SESSION['_last_regen']) || time() - $_SESSION['_last_regen'] > 900) {
        session_regenerate_id(true);
        $_SESSION['_last_regen'] = time();
    }
}

/**
 * Perform inline STK push — returns ['success' => bool, 'error' => string|null]
 */
function perform_stk_push($phone, $amount, $token, $description = 'Community Payment') {
    date_default_timezone_set('Africa/Nairobi');

    $envFile = dirname(__DIR__, 2) . '/.env.php';
    if (file_exists($envFile)) require_once $envFile;

    $consumerKey       = getenv('MPESA_CONSUMER_KEY');
    $consumerSecret    = getenv('MPESA_CONSUMER_SECRET');
    $BusinessShortCode = getenv('MPESA_SHORTCODE');
    $Passkey           = getenv('MPESA_PASSKEY');
    $CallBackURL       = getenv('MPESA_CALLBACK_URL') ?: 'https://jeramy1.top/callback_url.php';

    if (!$consumerKey || !$consumerSecret) {
        return ['success' => false, 'error' => 'System configuration error. Contact admin.'];
    }

    // Normalize phone: 07xx/01xx → 254...
    $mpesaPhone = preg_replace('/[\s\-]/', '', $phone);
    $mpesaPhone = ltrim($mpesaPhone, '+');
    if (preg_match('/^0[71][0-9]{8}$/', $mpesaPhone)) {
        $mpesaPhone = '254' . substr($mpesaPhone, 1);
    }
    if (!preg_match('/^254[71][0-9]{8}$/', $mpesaPhone)) {
        return ['success' => false, 'error' => 'Invalid phone number format.'];
    }

    // Step 1: Get OAuth token
    $ch = curl_init('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_USERPWD        => "$consumerKey:$consumerSecret",
        CURLOPT_TIMEOUT        => 30,
    ]);
    $tokenResp = curl_exec($ch);
    $tokenCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr   = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $tokenCode !== 200) {
        error_log("Community STK Token Error: HTTP $tokenCode, Err: $curlErr");
        return ['success' => false, 'error' => 'M-PESA connection failed. Try again later.'];
    }

    $access_token = json_decode($tokenResp, true)['access_token'] ?? null;
    if (!$access_token) {
        return ['success' => false, 'error' => 'M-PESA authentication failed.'];
    }

    // Step 2: Send STK Push
    $Timestamp = date('YmdHis');
    $Password  = base64_encode($BusinessShortCode . $Passkey . $Timestamp);

    $payload = [
        'BusinessShortCode' => $BusinessShortCode,
        'Password'          => $Password,
        'Timestamp'         => $Timestamp,
        'TransactionType'   => 'CustomerPayBillOnline',
        'Amount'            => (int)$amount,
        'PartyA'            => $mpesaPhone,
        'PartyB'            => $BusinessShortCode,
        'PhoneNumber'       => $mpesaPhone,
        'CallBackURL'       => $CallBackURL . '?token=' . $token,
        'AccountReference'  => 'JERAMOYIE',
        'TransactionDesc'   => $description
    ];

    $ch = curl_init('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $httpCode !== 200) {
        error_log("Community STK Push Error: HTTP $httpCode, Err: $curlErr");
        return ['success' => false, 'error' => 'Payment request failed. Try again later.'];
    }

    $resp = json_decode($response, true);
    if (!isset($resp['ResponseCode']) || $resp['ResponseCode'] !== '0') {
        error_log("Community STK Response: " . json_encode($resp));
        return ['success' => false, 'error' => 'Payment request was rejected. Try again.'];
    }

    return ['success' => true, 'error' => null];
}
SECPHP;

$secPath = $base . '/includes/security.php';
file_put_contents($secPath, $security_php);
echo "✓ Security module: " . filesize($secPath) . " bytes\n";

// ============================================================
// 3. SHARED NAVBAR — community/includes/navbar.php
// ============================================================

$navbar_php = <<<'NAVPHP'
<?php
/**
 * Community Portal — Shared Navigation Bar
 * Usage: set $active_page before including. e.g. $active_page = 'dashboard';
 */
if (!isset($active_page)) $active_page = '';
$_nav_name = $_SESSION['community_customer_name'] ?? 'User';
$_nav_first = explode(' ', $_nav_name)[0];
?>
<nav class="navbar navbar-expand-lg border-bottom fixed-top shadow-sm" style="background:rgba(255,255,255,.97);backdrop-filter:blur(10px)">
<div class="container">
    <a class="navbar-brand fw-bold text-success d-flex align-items-center gap-2" href="dashboard.php">
        <div class="d-flex align-items-center justify-content-center rounded-circle bg-success text-white" style="width:32px;height:32px;font-size:.8rem"><i class="bi bi-people-fill"></i></div>
        <span class="d-none d-sm-inline">COMMUNITY</span>
    </a>

    <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#communityNav">
        <i class="bi bi-list fs-4"></i>
    </button>

    <div class="collapse navbar-collapse" id="communityNav">
        <ul class="navbar-nav mx-auto gap-1 mt-2 mt-lg-0">
            <li class="nav-item">
                <a class="nav-link rounded-pill px-3 <?=$active_page==='dashboard'?'active fw-bold text-white bg-success':''?>" href="dashboard.php">
                    <i class="bi bi-speedometer2 me-1"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-pill px-3 <?=$active_page==='savings'?'active fw-bold text-white bg-success':''?>" href="savings.php">
                    <i class="bi bi-piggy-bank me-1"></i>Savings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-pill px-3 <?=$active_page==='loans'?'active fw-bold text-white bg-success':''?>" href="apply-loan.php">
                    <i class="bi bi-cash-stack me-1"></i>Loans
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-pill px-3 <?=$active_page==='transactions'?'active fw-bold text-white bg-success':''?>" href="transactions.php">
                    <i class="bi bi-receipt me-1"></i>History
                </a>
            </li>
        </ul>

        <div class="d-flex align-items-center gap-2 mt-2 mt-lg-0">
            <a href="../index.html" class="btn btn-sm btn-outline-secondary rounded-pill px-3 d-none d-lg-inline-flex align-items-center" title="Main Website">
                <i class="bi bi-house me-1"></i>Home
            </a>
            <div class="dropdown">
                <button class="btn btn-outline-dark btn-sm rounded-pill px-3 dropdown-toggle shadow-sm" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i><span class="d-none d-sm-inline"><?=htmlspecialchars($_nav_first)?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg" style="min-width:200px">
                    <li class="px-3 py-2 border-bottom">
                        <small class="text-muted">Signed in as</small><br>
                        <strong class="small"><?=htmlspecialchars($_nav_name)?></strong>
                    </li>
                    <li><a class="dropdown-item py-2" href="dashboard.php"><i class="bi bi-speedometer2 me-2 text-success"></i>Dashboard</a></li>
                    <li><a class="dropdown-item py-2" href="transactions.php"><i class="bi bi-receipt me-2 text-primary"></i>Transactions</a></li>
                    <li><a class="dropdown-item py-2" href="download-statement.php"><i class="bi bi-file-pdf me-2 text-danger"></i>Download Statement</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2 d-lg-none" href="../index.html"><i class="bi bi-house me-2"></i>Main Website</a></li>
                    <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
</nav>
NAVPHP;

$navPath = $base . '/includes/navbar.php';
file_put_contents($navPath, $navbar_php);
echo "✓ Navbar: " . filesize($navPath) . " bytes\n";

echo "\n✅ Foundation setup complete!\n";
