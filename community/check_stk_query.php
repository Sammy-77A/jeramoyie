<?php
/**
 * STK Query Fallback — polls Safaricom STK Query API when callback is delayed
 * Called by deposit_pending.php via AJAX after ~30 seconds of waiting
 *
 * CRITICAL: When STK query confirms success, this script PROCESSES the deposit
 * directly (creates the savings record and deletes the pending row), because
 * the callback may never arrive in sandbox/localhost environments.
 */
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/json');

$type = $_GET['type'] ?? ''; // 'deposit', 'repay', or 'register'
$token = $_GET['token'] ?? '';

if (!$token || !in_array($type, ['deposit', 'repay', 'register'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Look up the pending record and its CheckoutRequestID
$checkoutRequestId = null;
$pendingRecord = null;

if ($type === 'deposit' && $token === ($_SESSION['community_deposit_token'] ?? '')) {
    $stmt = $pdo->prepare("SELECT * FROM community_pending_deposits WHERE token = ?");
    $stmt->execute([$token]);
    $pendingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    $checkoutRequestId = $pendingRecord['checkout_request_id'] ?? null;
}
elseif ($type === 'repay' && $token === ($_SESSION['community_repay_token'] ?? '')) {
    $stmt = $pdo->prepare("SELECT checkout_request_id FROM community_pending_repayments WHERE token = ?");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $checkoutRequestId = $row['checkout_request_id'] ?? null;
}
elseif ($type === 'register' && $token === ($_SESSION['community_pending_token'] ?? '')) {
    $stmt = $pdo->prepare("SELECT checkout_request_id FROM community_pending_registrations WHERE token = ?");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $checkoutRequestId = $row['checkout_request_id'] ?? null;
}

if (!$checkoutRequestId) {
    echo json_encode(['queried' => false, 'reason' => 'No checkout ID found']);
    exit;
}

// Query Safaricom STK API
$result = query_stk_status($checkoutRequestId);

// If STK query confirms SUCCESS, process the transaction directly
// This handles the case where callbacks never arrive (sandbox/localhost)
if ($result['success']) {
    try {
        if ($type === 'deposit' && $pendingRecord) {
            // Check if not already processed (idempotency)
            $check = $pdo->prepare("SELECT id FROM community_pending_deposits WHERE token = ?");
            $check->execute([$token]);
            if ($check->fetch()) {
                // Still pending — process the deposit now
                $ref = generate_reference_code('DEP');
                $pdo->prepare("INSERT INTO community_savings (customer_id, product_id, amount, transaction_type, reference_code) VALUES (?,?,?,'deposit',?)")
                    ->execute([$pendingRecord['customer_id'], $pendingRecord['product_id'], $pendingRecord['amount'], $ref]);

                // Audit log
                $pdo->prepare("INSERT INTO community_security_log (event_type, customer_id, details) VALUES ('deposit_confirmed_stk_query', ?, ?)")
                    ->execute([$pendingRecord['customer_id'], "Amount: {$pendingRecord['amount']}, Ref: $ref, Token: $token (via STK Query)"]);

                // Delete pending record (same as callback would do)
                $pdo->prepare("DELETE FROM community_pending_deposits WHERE token = ?")->execute([$token]);

                $result['deposit_processed'] = true;
            }
        }
        elseif ($type === 'repay') {
            // Check if not already processed
            $check = $pdo->prepare("SELECT * FROM community_pending_repayments WHERE token = ?");
            $check->execute([$token]);
            $repay = $check->fetch(PDO::FETCH_ASSOC);
            if ($repay) {
                // Still pending — process the repayment now
                $ref = generate_reference_code('REP');
                $pdo->prepare("INSERT INTO community_loan_repayments (loan_id, amount, payment_method, reference_code) VALUES (?,?,'mpesa',?)")
                    ->execute([$repay['loan_id'], $repay['amount'], $ref]);

                // Update loan status if fully paid
                $loanStmt = $pdo->prepare("SELECT total_repayable, (SELECT COALESCE(SUM(amount),0) FROM community_loan_repayments WHERE loan_id = ?) as total_paid FROM community_loans WHERE id = ?");
                $loanStmt->execute([$repay['loan_id'], $repay['loan_id']]);
                $loanData = $loanStmt->fetch();
                if ($loanData && $loanData['total_paid'] >= $loanData['total_repayable']) {
                    $pdo->prepare("UPDATE community_loans SET status = 'completed' WHERE id = ?")->execute([$repay['loan_id']]);
                }

                // Audit log
                $pdo->prepare("INSERT INTO community_security_log (event_type, customer_id, details) VALUES ('repayment_confirmed_stk_query', ?, ?)")
                    ->execute([$repay['customer_id'], "Loan: {$repay['loan_id']}, Amount: {$repay['amount']}, Ref: $ref, Token: $token (via STK Query)"]);

                // Delete pending record
                $pdo->prepare("DELETE FROM community_pending_repayments WHERE token = ?")->execute([$token]);

                $result['repay_processed'] = true;
            }
        }
        elseif ($type === 'register') {
            // Check if not already processed
            $check = $pdo->prepare("SELECT * FROM community_pending_registrations WHERE token = ?");
            $check->execute([$token]);
            $reg = $check->fetch(PDO::FETCH_ASSOC);
            if ($reg) {
                // Still pending — process the registration now
                $stmt = $pdo->prepare("INSERT INTO community_customers (full_name, phone_number, national_id, email, password, status) VALUES (?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$reg['full_name'], $reg['phone_number'], $reg['national_id'], $reg['email'], $reg['password_hash']]);

                // Audit log
                $pdo->prepare("INSERT INTO community_security_log (event_type, details) VALUES ('registration_confirmed_stk_query', ?)")
                    ->execute(["Customer: {$reg['full_name']}, Phone: {$reg['phone_number']}, Token: $token (via STK Query)"]);

                // Delete pending record
                $pdo->prepare("DELETE FROM community_pending_registrations WHERE token = ?")->execute([$token]);

                $result['register_processed'] = true;
            }
        }    }    catch (Exception $e) {
        error_log("STK Query processing error: " . $e->getMessage());
    }
} // end if ($result['success'])

echo json_encode([
    'queried' => true,
    'success' => $result['success'],
    'result_code' => $result['result_code'],
    'result_desc' => $result['result_desc'],
    'deposit_processed' => $result['deposit_processed'] ?? false,
    'repay_processed' => $result['repay_processed'] ?? false,
    'register_processed' => $result['register_processed'] ?? false
]);
