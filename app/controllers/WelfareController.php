<?php
/**
 * WelfareController — Welfare contributions and claims.
 * Extracted from: welfare.php, welfare_book.php, process_welfare.php
 */

namespace App\Controllers;

use Core\Controller;

class WelfareController extends Controller
{
    /**
     * GET /welfare — Welfare dashboard.
     */
    public function index(): void
    {
        $member_id = $_SESSION['member_id'];

        try {
            $stmt = $this->pdo->prepare("SELECT welfare_balance FROM member_financial_summary WHERE id = ?");
            $stmt->execute([$member_id]);
            $fin = $stmt->fetch(\PDO::FETCH_ASSOC);

            $stmt = $this->pdo->prepare("SELECT * FROM welfare_contributions WHERE member_id = ? ORDER BY contribution_date DESC");
            $stmt->execute([$member_id]);
            $contributions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $stmt = $this->pdo->prepare("SELECT * FROM welfare_requests WHERE member_id = ? ORDER BY created_at DESC");
            $stmt->execute([$member_id]);
            $requests = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $stmt = $this->pdo->prepare("SELECT SUM(amount) FROM welfare_contributions WHERE member_id = ?");
            $stmt->execute([$member_id]);
            $lifetime_contrib = $stmt->fetchColumn() ?: 0;

        } catch (\PDOException $e) {
            die("Database Error: " . $e->getMessage());
        }

        $this->view('welfare/index', [
            'fin' => $fin,
            'contributions' => $contributions,
            'requests' => $requests,
            'lifetime_contrib' => $lifetime_contrib,
        ]);
    }

    /**
     * GET /welfare-book — Welfare book detail.
     */
    public function book(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/welfare_book.php';
    }

    /**
     * POST /process-welfare — Process welfare request.
     */
    public function processRequest(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/process_welfare.php';
    }
}
