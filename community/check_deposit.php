<?php
/**
 * check_deposit.php — Polled by deposit_pending.php every 3 seconds
 * 
 * Mirrors the same pattern as check_payment.php (registration):
 * When the pending record disappears, VERIFY the deposit was actually 
 * recorded before reporting success. This prevents false confirmations
 * when the callback deletes the pending record due to a failed payment.
 */
session_start();
require_once __DIR__ . '/includes/db.php';
header('Content-Type: application/json');

$token = $_GET['token'] ?? null;
$paid = false;
$pending_exists = false;

if ($token && $token === ($_SESSION['community_deposit_token'] ?? '')) {
    $customer_id = $_SESSION['community_customer_id'] ?? null;

    $stmt = $pdo->prepare("SELECT id FROM community_pending_deposits WHERE token = ?");
    $stmt->execute([$token]);

    if ($stmt->fetch()) {
        // Pending record still exists — still waiting for callback
        $pending_exists = true;
    }
    else {
        // Pending record is gone — but was it success or failure?
        // Check if a savings deposit was actually created recently for this customer
        if ($customer_id) {
            $check = $pdo->prepare(
                "SELECT id FROM community_savings 
                 WHERE customer_id = ? AND transaction_type = 'deposit' 
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                 ORDER BY id DESC LIMIT 1"
            );
            $check->execute([$customer_id]);
            if ($check->fetch()) {
                // Deposit was actually recorded — genuine success
                $paid = true;
                unset($_SESSION['community_deposit_token']);
                unset($_SESSION['community_deposit_amount']);
            }
        // If no recent deposit found: paid=false, pending_exists=false
        // Frontend will show "Payment Failed" which is correct
        }
    }
}

echo json_encode(['paid' => $paid, 'pending_exists' => $pending_exists]);