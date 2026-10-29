<?php
/**
 * Community Auth Guard
 * Include at the top of any community-user-only page.
 */
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['community_customer_id'])) {
    header('Location: login.php');
    exit;
}

// Check if account is still active
$_auth_stmt = $pdo->prepare("SELECT status FROM community_customers WHERE id = ?");
$_auth_stmt->execute([$_SESSION['community_customer_id']]);
$_auth_customer = $_auth_stmt->fetch(PDO::FETCH_ASSOC);

if (!$_auth_customer || $_auth_customer['status'] !== 'active') {
    session_destroy();
    header('Location: login.php?msg=' . urlencode('Your account has been suspended.'));
    exit;
}
