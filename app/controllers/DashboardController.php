<?php
/**
 * DashboardController — Member profile dashboard.
 * Extracted from: profile.php, edit_profile.php, cast_vote.php
 */

namespace App\Controllers;

use Core\Controller;

class DashboardController extends Controller
{
    /**
     * GET /profile — Member dashboard.
     */
    public function profile(): void
    {
        // Handle logout via query param (backward compat)
        if (isset($_GET['logout']) && $_GET['logout'] == 1) {
            session_destroy();
            $this->redirect('/login');
            return;
        }

        $member_id = $_SESSION['member_id'];
        $success_msg = $_GET['success'] ?? '';
        $error_msg = $_GET['error'] ?? '';

        try {
            // Fetch Member
            $stmt = $this->pdo->prepare("SELECT * FROM members WHERE id = ?");
            $stmt->execute([$member_id]);
            $member = $stmt->fetch();

            // Fetch Financial Summary
            $stmt = $this->pdo->prepare("SELECT * FROM member_financial_summary WHERE id = ?");
            $stmt->execute([$member_id]);
            $financials = $stmt->fetch();

            // Fetch Pending Loans for Voting
            $stmt = $this->pdo->prepare("
                SELECT l.id, l.amount, l.purpose, m.name COLLATE utf8mb4_general_ci as applicant_name, 'normal' COLLATE utf8mb4_general_ci as loan_type 
                FROM normal_loans l JOIN members m ON l.member_id = m.id
                WHERE l.status = 'pending' AND l.id NOT IN (SELECT loan_id FROM loan_votes WHERE voter_id = ? AND loan_type = 'normal')
                UNION
                SELECT l.id, l.amount, l.purpose, m.name COLLATE utf8mb4_general_ci as applicant_name, 'table_banking' COLLATE utf8mb4_general_ci as loan_type 
                FROM table_banking_loans l JOIN members m ON l.member_id = m.id
                WHERE l.status = 'pending' AND l.id NOT IN (SELECT loan_id FROM loan_votes WHERE voter_id = ? AND loan_type = 'table_banking')
                UNION
                SELECT l.id, l.amount, l.purpose, m.name COLLATE utf8mb4_general_ci as applicant_name, 'uwezo' COLLATE utf8mb4_general_ci as loan_type 
                FROM uwezo_loans l JOIN members m ON l.member_id = m.id
                WHERE l.status = 'pending' AND l.id NOT IN (SELECT loan_id FROM loan_votes WHERE voter_id = ? AND loan_type = 'uwezo')
            ");
            $stmt->execute([$member_id, $member_id, $member_id]);
            $pending_votes = $stmt->fetchAll();

            // Fetch Announcements (last 30 days)
            $stmt = $this->pdo->query("SELECT a.*, m.name as author FROM announcements a LEFT JOIN members m ON a.admin_id = m.id WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY a.created_at DESC LIMIT 10");
            $announcements = $stmt->fetchAll();

            $needs_email = empty($member['email']);
            $notif_count = count($pending_votes) + count($announcements) + ($needs_email ? 1 : 0);

        } catch (\PDOException $e) {
            die("System Error: " . $e->getMessage());
        }

        $names = explode(" ", $member['name']);
        $initials = strtoupper(substr($names[0], 0, 1) . (isset($names[1]) ? substr($names[1], 0, 1) : ''));

        $this->view('dashboard/profile', [
            'member' => $member,
            'financials' => $financials,
            'pending_votes' => $pending_votes,
            'announcements' => $announcements,
            'needs_email' => $needs_email,
            'notif_count' => $notif_count,
            'names' => $names,
            'initials' => $initials,
            'success_msg' => $success_msg,
            'error_msg' => $error_msg,
        ]);
    }

    /**
     * POST /profile — Update payout preference.
     */
    public function updatePreference(): void
    {
        if (isset($_POST['update_preference'])) {
            $pref = $_POST['payout_preference'] ?? 'savings';
            try {
                $stmt = $this->pdo->prepare("UPDATE members SET payout_preference = ? WHERE id = ?");
                $stmt->execute([$pref, $_SESSION['member_id']]);
                $this->redirect('/profile?success=' . urlencode('Your payout preference has been updated.'));
            } catch (\PDOException $e) {
                $this->redirect('/profile?error=' . urlencode('Update failed: ' . $e->getMessage()));
            }
        } else {
            $this->redirect('/profile');
        }
    }

    /**
     * GET /edit-profile — Show edit profile form.
     */
    public function editProfileForm(): void
    {
        $pdo = $this->pdo;
        $member_id = $_SESSION['member_id'];
        require BASE_PATH . '/edit_profile.php';
    }

    /**
     * POST /edit-profile — Process profile update.
     */
    public function editProfile(): void
    {
        $pdo = $this->pdo;
        $member_id = $_SESSION['member_id'];
        require BASE_PATH . '/edit_profile.php';
    }

    /**
     * POST /cast-vote — Cast loan approval vote.
     */
    public function castVote(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/cast_vote.php';
    }
}
