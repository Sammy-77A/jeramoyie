<?php
/**
 * PaymentController — M-Pesa payments, callbacks, payment status checks.
 * Extracted from: process_payment.php, callback_handler.php, callback_url.php,
 *                 check_payment.php, payment_pending.php, process_deposit.php
 */

namespace App\Controllers;

use Core\Controller;
use App\Services\MpesaService;

class PaymentController extends Controller
{
    /**
     * POST /process-payment — Initiate M-Pesa STK push for member payment.
     */
    public function processPayment(): void
    {
        $member_id = $_SESSION['member_id'];
        $type = $_POST['type'] ?? '';
        $user_amount = floatval($_POST['amount'] ?? 0);
        $phone = $_POST['phone'] ?? '';

        $mpesa = new MpesaService();
        $phone = $mpesa->normalizePhone($phone);

        if ($user_amount < 1) {
            $this->redirect('/profile?error=' . urlencode("Amount must be at least KSh 1."));
            return;
        }
        if (!preg_match('/^254[71][0-9]{8}$/', $phone)) {
            $this->redirect('/profile?error=' . urlencode("Invalid phone number format."));
            return;
        }
        if (!$mpesa->isConfigured()) {
            $this->redirect('/profile?error=' . urlencode("M-Pesa is not configured correctly. Please contact support."));
            return;
        }

        // Build callback URL
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        $callbackURL = $scheme . '://' . $host . $basePath . '/api/callback-handler';

        // Fix: if we're in /public/, adjust to root-relative
        $callbackURL = str_replace('/public/api/', '/api/', $callbackURL);

        $result = $mpesa->initiateSTKPush(
            $phone,
            $user_amount,
            strtoupper(substr($type, 0, 5)) . $member_id,
            'Jera ' . $type,
            $callbackURL
        );

        if ($result['success']) {
            $stmt = $this->pdo->prepare("INSERT INTO pending_payments (member_id, amount, payment_type, checkout_request_id, status, phone_number) VALUES (?, ?, ?, ?, 'pending', ?)");
            $stmt->execute([$member_id, $user_amount, $type, $result['CheckoutRequestID'], $phone]);

            $_SESSION['pending_checkout_id'] = $result['CheckoutRequestID'];
            $_SESSION['pending_payment_type'] = $type;
            $_SESSION['pending_payment_amount'] = $user_amount;

            $this->redirect('/payment-pending');
        } else {
            $this->redirect('/profile?error=' . urlencode($result['error']));
        }
    }

    /**
     * GET /payment-pending — Show payment pending page.
     */
    public function pending(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/payment_pending.php';
    }

    /**
     * GET /check-payment — AJAX check payment status.
     */
    public function checkPayment(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/check_payment.php';
    }

    /**
     * POST /process-deposit — Process savings deposit.
     */
    public function processDeposit(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/process_deposit.php';
    }

    /**
     * POST /api/callback — M-Pesa callback (registration).
     * Backward-compatible with callback_url.php
     */
    public function callback(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/callback_url.php';
    }

    /**
     * POST /api/callback-handler — M-Pesa callback (payments).
     * Backward-compatible with callback_handler.php
     */
    public function callbackHandler(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/callback_handler.php';
    }

    /**
     * GET /api/check-payment — AJAX check payment status (API).
     */
    public function apiCheckPayment(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/check_payment.php';
    }

    /**
     * GET /api/check-status — AJAX check status (API).
     */
    public function apiCheckStatus(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/check_status.php';
    }
}
