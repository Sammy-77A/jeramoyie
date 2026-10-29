<?php
/**
 * AuthController — Login, Signup, Registration, Password Reset.
 * Extracted from: login.php, signup.php, register.php, forgot-password.php, reset-password.php, pending.php
 */

namespace App\Controllers;

use Core\Controller;
use App\Services\AuthService;
use App\Services\MpesaService;

class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->authService = new AuthService();
    }

    /**
     * GET /login — Show login form.
     */
    public function loginForm(): void
    {
        if (isset($_SESSION['member_id'])) {
            $this->redirect('/profile');
        }

        $_SESSION['csrf'] = bin2hex(random_bytes(16));

        $this->viewStandalone('auth/login', [
            'error' => null,
            'csrf' => $_SESSION['csrf'],
        ]);
    }

    /**
     * POST /login — Authenticate user.
     */
    public function authenticate(): void
    {
        $rawPhone = trim($this->request->post('phone', ''));
        $password = $this->request->post('password', '');
        $token = $this->request->post('csrf', '');

        // Normalize phone
        $phone = preg_replace('/^0/', '254', $rawPhone);

        if ($token !== ($_SESSION['csrf'] ?? '')) {
            $error = "Session expired. Please refresh.";
        } elseif (!preg_match('/^(07[0-9]{8}|2547[0-9]{8})$/', $rawPhone)) {
            $error = "Invalid phone number format.";
        } else {
            $member = $this->authService->authenticate($phone, $password);

            if ($member) {
                $this->authService->setSession($member);

                if ($_SESSION['is_native'] === 1) {
                    $this->redirect('/profile');
                } else {
                    $this->redirect('/community/dashboard');
                }
                return;
            } else {
                $error = "Incorrect phone number or password.";
            }
        }

        $_SESSION['csrf'] = bin2hex(random_bytes(16));

        $this->viewStandalone('auth/login', [
            'error' => $error ?? null,
            'csrf' => $_SESSION['csrf'],
        ]);
    }

    /**
     * GET /signup — Show signup form.
     */
    public function signupForm(): void
    {
        if (isset($_SESSION['member_id'])) {
            $this->redirect('/profile');
        }

        $this->viewStandalone('auth/signup', [
            'msg' => $this->request->query('msg', ''),
        ]);
    }

    /**
     * POST /register — Process registration.
     */
    public function register(): void
    {
        if (!$this->request->isPost()) {
            $this->redirect('/signup');
            return;
        }

        $phone = $this->authService->normalizePhone($this->request->post('phone', ''));
        $name = trim($this->request->post('name', ''));
        $nationalId = trim($this->request->post('national_id', ''));
        $password = $this->request->post('password', '');

        // Validation
        if (strlen($nationalId) < 7) {
            $this->redirect('/error?msg=' . urlencode('National ID must be at least 7 digits.'));
            return;
        }
        if (!$phone) {
            $this->redirect('/error?msg=' . urlencode('Phone must be a valid Kenyan number.'));
            return;
        }
        if ($this->authService->isDuplicate($nationalId, $phone)) {
            $this->redirect('/error?msg=' . urlencode('National ID or Phone already registered.'));
            return;
        }

        // Create pending registration
        $token = $this->authService->createPendingRegistration($name, $nationalId, $phone, $password);


        // Initiate M-Pesa STK push for registration fee
        $mpesa = new MpesaService();
        require_once BASE_PATH . '/includes/mpesa_verify.php';
        $callbackUrl = mpesa_callback_url(getenv('MPESA_CALLBACK_URL') ?: '', ['token' => $token]);
        $result = $mpesa->initiateSTKPush($phone, (float)(getenv('MPESA_REG_FEE') ?: 200), 'JERAMOYIE', 'Jeramoyie Membership', $callbackUrl);

        if ($result['success']) {
            $_SESSION['pending_token'] = $token;
            $_SESSION['pending_phone'] = $phone;
            $this->redirect('/pending');
        } else {
            $this->pdo->prepare("DELETE FROM pending_registrations WHERE token = ?")->execute([$token]);
            $this->redirect('/error?msg=' . urlencode($result['error'] ?? 'STK push failed.'));
        }
    }

    /**
     * GET /logout — Destroy session.
     */
    public function logout(): void
    {
        $this->authService->logout();
        $this->redirect('/login');
    }

    /**
     * GET /pending — Show payment pending page.
     */
    public function pending(): void
    {
        $this->viewStandalone('auth/pending', [
            'token' => $_SESSION['pending_token'] ?? '',
            'phone' => $_SESSION['pending_phone'] ?? '',
        ]);
    }

    /**
     * GET /check-status — Check registration payment status.
     */
    public function checkStatus(): void
    {
        $token = $_SESSION['pending_token'] ?? '';

        if (!$token) {
            $this->json(['status' => 'error', 'message' => 'No pending registration']);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT token FROM pending_registrations WHERE token = ?");
        $stmt->execute([$token]);

        if ($stmt->fetch()) {
            $this->json(['status' => 'pending']);
        } else {
            // Check if member was created
            $phone = $_SESSION['pending_phone'] ?? '';
            $stmt = $this->pdo->prepare("SELECT id FROM members WHERE phone = ?");
            $stmt->execute([$phone]);

            if ($stmt->fetch()) {
                unset($_SESSION['pending_token'], $_SESSION['pending_phone']);
                $this->json(['status' => 'success']);
            } else {
                $this->json(['status' => 'failed']);
            }
        }
    }

    /**
     * GET /forgot-password — Show forgot password form.
     */
    public function forgotPasswordForm(): void
    {
        $this->viewStandalone('auth/forgot-password');
    }

    /**
     * POST /forgot-password — Process forgot password.
     */
    public function forgotPassword(): void
    {
        // Delegate to original logic
        require BASE_PATH . '/forgot-password.php';
    }

    /**
     * GET /reset-password — Show reset password form.
     */
    public function resetPasswordForm(): void
    {
        $this->viewStandalone('auth/reset-password', [
            'token' => $this->request->query('token', ''),
            'user' => $this->request->query('user', ''),
        ]);
    }

    /**
     * POST /reset-password — Process password reset.
     */
    public function resetPassword(): void
    {
        require BASE_PATH . '/reset-password.php';
    }

    /**
     * GET /api/check-reset-status — AJAX check reset status.
     */
    public function checkResetStatus(): void
    {
        require BASE_PATH . '/check_reset_status.php';
    }
}
