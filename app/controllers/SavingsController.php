<?php
/**
 * SavingsController — Normal savings, Table banking books.
 * Extracted from: savings.php, normal_book.php, table_banking_book.php
 */

namespace App\Controllers;

use Core\Controller;

class SavingsController extends Controller
{
    /**
     * GET /savings — Savings dashboard with transaction history.
     */
    public function index(): void
    {
        $member_id = $_SESSION['member_id'];

        $stmt = $this->pdo->prepare("SELECT name FROM members WHERE id = ?");
        $stmt->execute([$member_id]);
        $member = $stmt->fetch();

        $stmt = $this->pdo->prepare("SELECT * FROM savings_transactions WHERE member_id = ? ORDER BY date ASC, id ASC");
        $stmt->execute([$member_id]);
        $raw_transactions = $stmt->fetchAll();

        $balance = 0;
        $history = [];
        $total_deposits = 0;
        $total_withdrawals = 0;

        foreach ($raw_transactions as $t) {
            $amt = (float) $t['amount'];
            if (in_array($t['type'], ['deposit', 'repayment'])) {
                $balance += $amt;
                if ($t['type'] === 'deposit')
                    $total_deposits += $amt;
            } else {
                $balance -= $amt;
                $total_withdrawals += $amt;
            }
            $t['running_balance'] = $balance;
            $history[] = $t;
        }
        $history = array_reverse($history);

        $this->view('savings/index', [
            'member' => $member,
            'history' => $history,
            'balance' => $balance,
            'total_deposits' => $total_deposits,
            'total_withdrawals' => $total_withdrawals,
        ]);
    }

    /**
     * GET /normal-book — Normal savings book. 
     */
    public function normalBook(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/normal_book.php';
    }

    /**
     * GET /table-banking-book — Table banking book.
     */
    public function tableBankingBook(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/table_banking_book.php';
    }
}
