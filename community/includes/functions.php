<?php
/**
 * Community Module — Helper Functions
 */

function community_format_currency($amount) {
    return 'KSh ' . number_format((float)$amount, 2);
}

function generate_csrf_token() {
    if (empty($_SESSION['community_csrf'])) {
        $_SESSION['community_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['community_csrf'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['community_csrf']) && hash_equals($_SESSION['community_csrf'], $token);
}

function regenerate_csrf_token() {
    $_SESSION['community_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['community_csrf'];
}

function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token()) . '">';
}

function generate_reference_code($prefix = 'TXN') {
    return $prefix . strtoupper(date('ymd')) . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

function log_community_audit($pdo, $user_type, $user_id, $action, $details = '') {
    try {
        $stmt = $pdo->prepare("INSERT INTO community_audit_logs (user_type, user_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_type, $user_id, $action, $details, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
    } catch (PDOException $e) {
        error_log("Community Audit Log Failed: " . $e->getMessage());
    }
}

function community_flash($key, $value = null) {
    if ($value !== null) {
        $_SESSION['community_flash'][$key] = $value;
    } else {
        $msg = $_SESSION['community_flash'][$key] ?? '';
        unset($_SESSION['community_flash'][$key]);
        return $msg;
    }
}