<?php
/**
 * LoanController — Loan requests, loan statements.
 * Extracted from: loan_request.php, loan_statement.php, request_loan.php
 */

namespace App\Controllers;

use Core\Controller;

class LoanController extends Controller
{
    /**
     * GET /loan-request — Show loan request form.
     */
    public function requestForm(): void
    {
        $pdo = $this->pdo;
        $member_id = $_SESSION['member_id'];
        require BASE_PATH . '/loan_request.php';
    }

    /**
     * POST /loan-request — Submit loan request.
     */
    public function submitRequest(): void
    {
        $pdo = $this->pdo;
        $member_id = $_SESSION['member_id'];
        require BASE_PATH . '/loan_request.php';
    }

    /**
     * GET /loan-statement — View loan statement.
     */
    public function statement(): void
    {
        $pdo = $this->pdo;
        $member_id = $_SESSION['member_id'];
        require BASE_PATH . '/loan_statement.php';
    }
}
