<?php
/**
 * Community Security Module
 * Rate limiting, transaction velocity, IP logging, session hardening,
 * M-PESA STK Push, and STK Query fallback.
 */
require_once dirname(__DIR__, 2) . '/includes/mpesa_verify.php';

/**
 * Set security headers on every page
 */
function set_security_headers()
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data: https://ui-avatars.com;");
}

/**
 * Get client IP address reliably
 */
function get_client_ip()
{
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP))
                return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Rate limiter — returns TRUE if rate limit exceeded
 */
function rate_limit_exceeded($pdo, $action, $identifier, $max_attempts = 5, $window_minutes = 60)
{
    // Clean old entries
    $pdo->prepare("DELETE FROM community_rate_limits WHERE action_type = ? AND identifier = ? AND attempted_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)")
        ->execute([$action, $identifier, $window_minutes]);

    // Count recent attempts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM community_rate_limits WHERE action_type = ? AND identifier = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->execute([$action, $identifier, $window_minutes]);
    $count = (int) $stmt->fetchColumn();

    if ($count >= $max_attempts)
        return true;

    // Log this attempt
    $pdo->prepare("INSERT INTO community_rate_limits (action_type, identifier) VALUES (?, ?)")
        ->execute([$action, $identifier]);

    return false;
}

/**
 * Log security event
 */
function log_security_event($pdo, $event_type, $details = '', $customer_id = null)
{
    try {
        $pdo->prepare("INSERT INTO community_security_log (event_type, customer_id, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?)")
            ->execute([
                $event_type,
                $customer_id,
                get_client_ip(),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                $details
            ]);
    } catch (Exception $e) {
        error_log("Security log failed: " . $e->getMessage());
    }
}

/**
 * Validate transaction velocity — prevent rapid-fire transactions
 * Returns error message or null if OK
 */
function check_transaction_velocity($pdo, $customer_id, $action_type, $max_per_hour = 5)
{
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
function has_pending_transaction($pdo, $table, $customer_id)
{
    $allowed = ['community_pending_deposits', 'community_pending_repayments'];
    if (!in_array($table, $allowed))
        return false; // Whitelist table names
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE customer_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $stmt->execute([$customer_id]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Session fingerprinting — verify session integrity
 */
function verify_session_fingerprint()
{
    $fingerprint = md5(($_SERVER['HTTP_USER_AGENT'] ?? '') . 'jm_salt_2024');

    if (!isset($_SESSION['_fingerprint'])) {
        $_SESSION['_fingerprint'] = $fingerprint;
    } elseif ($_SESSION['_fingerprint'] !== $fingerprint) {
        // Session hijacking detected
        log_security_event($GLOBALS['pdo'] ?? null, 'session_hijack_detected', 'Fingerprint mismatch');
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
 * Check IP-based rate limiting for login
 * Returns error message or null if OK
 */
function check_ip_rate_limit($pdo, $max = 20, $window = 60)
{
    $ip = get_client_ip();
    if (rate_limit_exceeded($pdo, 'login_ip', $ip, $max, $window)) {
        log_security_event($pdo, 'ip_rate_limit', "IP: $ip blocked after $max login attempts");
        return 'Too many login attempts from this location. Please wait.';
    }
    return null;
}

/**
 * Perform inline STK push — returns ['success' => bool, 'error' => string|null, 'checkout_request_id' => string|null]
 */
function perform_stk_push($phone, $amount, $token, $description = 'Community Payment')
{
    date_default_timezone_set('Africa/Nairobi');

    $envFile = dirname(__DIR__, 2) . '/.env.php';
    if (file_exists($envFile))
        require_once $envFile;

    $consumerKey = getenv('MPESA_CONSUMER_KEY');
    $consumerSecret = getenv('MPESA_CONSUMER_SECRET');
    $BusinessShortCode = getenv('MPESA_SHORTCODE');
    $Passkey = getenv('MPESA_PASSKEY');
    require_once dirname(__DIR__, 2) . '/includes/mpesa_verify.php';
    $CallBackURL = getenv('MPESA_CALLBACK_URL') ?: 'https://jeramy1.top/callback_url.php';

    if (!$consumerKey || !$consumerSecret) {
        return ['success' => false, 'error' => 'System configuration error. Contact admin.', 'checkout_request_id' => null];
    }

    // Normalize phone: 07xx/01xx → 254...
    $mpesaPhone = preg_replace('/[\s\-]/', '', $phone);
    $mpesaPhone = ltrim($mpesaPhone, '+');
    if (preg_match('/^0[71][0-9]{8}$/', $mpesaPhone)) {
        $mpesaPhone = '254' . substr($mpesaPhone, 1);
    }
    if (!preg_match('/^254[71][0-9]{8}$/', $mpesaPhone)) {
        return ['success' => false, 'error' => 'Invalid phone number format.', 'checkout_request_id' => null];
    }

    // Step 1: Get OAuth token
    $ch = curl_init(mpesa_base_url() . '/oauth/v1/generate?grant_type=client_credentials');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_USERPWD => "$consumerKey:$consumerSecret",
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $tokenResp = curl_exec($ch);
    $tokenCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $tokenCode !== 200) {
        error_log("Community STK Token Error: HTTP $tokenCode, Err: $curlErr");
        return ['success' => false, 'error' => 'M-PESA connection failed. Try again later.', 'checkout_request_id' => null];
    }

    $access_token = json_decode($tokenResp, true)['access_token'] ?? null;
    if (!$access_token) {
        return ['success' => false, 'error' => 'M-PESA authentication failed.', 'checkout_request_id' => null];
    }

    // Step 2: Send STK Push
    $Timestamp = date('YmdHis');
    $Password = base64_encode($BusinessShortCode . $Passkey . $Timestamp);

    $payload = [
        'BusinessShortCode' => $BusinessShortCode,
        'Password' => $Password,
        'Timestamp' => $Timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => (int) $amount,
        'PartyA' => $mpesaPhone,
        'PartyB' => $BusinessShortCode,
        'PhoneNumber' => $mpesaPhone,
        'CallBackURL' => mpesa_callback_url($CallBackURL, ['token' => $token]),
        'AccountReference' => 'JERAMOYIE',
        'TransactionDesc' => $description
    ];

    $ch = curl_init(mpesa_base_url() . '/mpesa/stkpush/v1/processrequest');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $httpCode !== 200) {
        error_log("Community STK Push Error: HTTP $httpCode, Err: $curlErr");
        return ['success' => false, 'error' => 'Payment request failed. Try again later.', 'checkout_request_id' => null];
    }

    $resp = json_decode($response, true);
    if (!isset($resp['ResponseCode']) || $resp['ResponseCode'] !== '0') {
        error_log("Community STK Response: " . json_encode($resp));
        return ['success' => false, 'error' => 'Payment request was rejected. Try again.', 'checkout_request_id' => null];
    }

    $checkoutRequestId = $resp['CheckoutRequestID'] ?? null;

    return ['success' => true, 'error' => null, 'checkout_request_id' => $checkoutRequestId];
}

/**
 * Query STK transaction status via Safaricom API
 * Returns ['success' => bool, 'result_code' => int|null, 'result_desc' => string]
 */
function query_stk_status($checkoutRequestId)
{
    date_default_timezone_set('Africa/Nairobi');

    $envFile = dirname(__DIR__, 2) . '/.env.php';
    if (file_exists($envFile))
        require_once $envFile;

    $consumerKey = getenv('MPESA_CONSUMER_KEY');
    $consumerSecret = getenv('MPESA_CONSUMER_SECRET');
    $BusinessShortCode = getenv('MPESA_SHORTCODE');
    $Passkey = getenv('MPESA_PASSKEY');

    if (!$consumerKey || !$consumerSecret || !$checkoutRequestId) {
        return ['success' => false, 'result_code' => null, 'result_desc' => 'Missing configuration'];
    }

    // Get OAuth token
    $ch = curl_init(mpesa_base_url() . '/oauth/v1/generate?grant_type=client_credentials');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_USERPWD => "$consumerKey:$consumerSecret",
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $tokenResp = curl_exec($ch);
    $tokenCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($tokenCode !== 200)
        return ['success' => false, 'result_code' => null, 'result_desc' => 'Token fetch failed'];

    $access_token = json_decode($tokenResp, true)['access_token'] ?? null;
    if (!$access_token)
        return ['success' => false, 'result_code' => null, 'result_desc' => 'No token'];

    // STK Query
    $Timestamp = date('YmdHis');
    $Password = base64_encode($BusinessShortCode . $Passkey . $Timestamp);

    $payload = [
        'BusinessShortCode' => $BusinessShortCode,
        'Password' => $Password,
        'Timestamp' => $Timestamp,
        'CheckoutRequestID' => $checkoutRequestId
    ];

    $ch = curl_init(mpesa_base_url() . '/mpesa/stkpushquery/v1/query');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200)
        return ['success' => false, 'result_code' => null, 'result_desc' => 'Query API error'];

    $resp = json_decode($response, true);
    $resultCode = $resp['ResultCode'] ?? null;
    $resultDesc = $resp['ResultDesc'] ?? 'Unknown';

    return [
        'success' => ($resultCode !== null && $resultCode == 0),
        'result_code' => $resultCode,
        'result_desc' => $resultDesc
    ];
}