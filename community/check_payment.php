<?php
session_start();
require_once __DIR__ . '/includes/db.php';
header('Content-Type: application/json');

$token = $_GET['token'] ?? null;
$paid = false;
$pending_exists = false;

if ($token && $token === ($_SESSION['community_pending_token'] ?? '')) {
    // Check if pending record still exists
    $stmt = $pdo->prepare("SELECT id, checkout_request_id FROM community_pending_registrations WHERE token = ?");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $pending_exists = true;
        $checkout_request_id = $row['checkout_request_id'] ?? null;
    }
    else {
        // Pending was deleted — check if customer was created (by callback)
        $phone = $_SESSION['community_pending_phone'] ?? null;
        if ($phone) {
            $stmt = $pdo->prepare("SELECT id, full_name FROM community_customers WHERE phone_number = ?");
            $stmt->execute([$phone]);
            if ($customer = $stmt->fetch()) {
                $paid = true;
                // Auto-set session
                session_regenerate_id(true);
                $_SESSION['community_customer_id'] = $customer['id'];
                $_SESSION['community_customer_name'] = $customer['full_name'];
                // Clean up pending session vars
                unset($_SESSION['community_pending_token']);
                unset($_SESSION['community_pending_phone']);
            }
        }
    }
}

echo json_encode(['paid' => $paid, 'pending_exists' => $pending_exists, 'checkout_request_id' => $checkout_request_id ?? null]);
exit;