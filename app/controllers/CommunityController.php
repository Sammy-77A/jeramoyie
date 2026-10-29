<?php
/**
 * CommunityController — Community/Okoa Portal.
 * Wraps the /community/ section pages.
 */

namespace App\Controllers;

use Core\Controller;

class CommunityController extends Controller
{
    /**
     * GET /community/login
     */
    public function loginForm(): void
    {
        require BASE_PATH . '/community/login.php';
    }

    /**
     * POST /community/login
     */
    public function authenticate(): void
    {
        require BASE_PATH . '/community/login.php';
    }

    /**
     * GET /community/register
     */
    public function registerForm(): void
    {
        require BASE_PATH . '/community/register.php';
    }

    /**
     * POST /community/register
     */
    public function register(): void
    {
        require BASE_PATH . '/community/register.php';
    }

    /**
     * GET /community/logout
     */
    public function logout(): void
    {
        require BASE_PATH . '/community/logout.php';
    }

    /**
     * GET /community/dashboard
     */
    public function dashboard(): void
    {
        require BASE_PATH . '/community/dashboard.php';
    }

    /**
     * GET /community/savings
     */
    public function savings(): void
    {
        require BASE_PATH . '/community/savings.php';
    }

    /**
     * GET /community/apply-loan
     */
    public function applyLoanForm(): void
    {
        require BASE_PATH . '/community/apply-loan.php';
    }

    /**
     * POST /community/apply-loan
     */
    public function applyLoan(): void
    {
        require BASE_PATH . '/community/apply-loan.php';
    }

    /**
     * GET /community/transactions
     */
    public function transactions(): void
    {
        require BASE_PATH . '/community/transactions.php';
    }

    /**
     * GET /community/download-statement
     */
    public function downloadStatement(): void
    {
        require BASE_PATH . '/community/download-statement.php';
    }

    /**
     * GET /community/forgot-password
     */
    public function forgotPassword(): void
    {
        require BASE_PATH . '/community/forgot-password.php';
    }

    /**
     * POST /community/forgot-password
     */
    public function processForgotPassword(): void
    {
        require BASE_PATH . '/community/forgot-password.php';
    }
}
