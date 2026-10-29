<?php
/**
 * AdminController — Admin dashboard and management pages.
 * Extracted from: admin_dashboard.php, admin_members.php, admin_loans.php, etc.
 */

namespace App\Controllers;

use Core\Controller;

class AdminController extends Controller
{
    /**
     * Permission matrix — same as original admin_dashboard.php
     */
    private array $permissions = [
        'Chairperson' => ['members', 'loans', 'attendance', 'finance', 'reports', 'welfare', 'sharing', 'penalties', 'community'],
        'Assistant Chairperson' => ['members', 'loans', 'attendance', 'finance', 'welfare'],
        'Treasury' => ['finance', 'reports', 'welfare', 'sharing', 'penalties', 'community'],
        'Secretary' => ['members', 'attendance', 'reports'],
        'Assistant Secretary' => ['members', 'attendance'],
        'Organizer' => ['attendance'],
        'Loan Chairperson' => ['loans', 'finance', 'welfare', 'sharing', 'penalties', 'community'],
        'Trustee' => ['reports', 'finance', 'sharing'],
        'dev' => ['members', 'loans', 'attendance', 'finance', 'reports', 'settings', 'welfare', 'sharing', 'penalties', 'community'],
    ];

    private function canAccess(string $module): bool
    {
        $role = $_SESSION['role'] ?? '';
        if (!isset($this->permissions[$role]))
            return false;
        return in_array($module, $this->permissions[$role]);
    }

    /**
     * GET /admin-dashboard — Admin console landing page.
     */
    public function dashboard(): void
    {
        $this->view('admin/dashboard', [
            'admin_name' => $_SESSION['name'],
            'admin_role' => $_SESSION['role'],
            'permissions' => $this->permissions,
        ]);
    }

    /**
     * GET /admin-members — Member registry.
     */
    public function members(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/admin_members.php';
    }

    /**
     * GET /admin-member-profile/{id}
     */
    public function memberProfile(string $id): void
    {
        $pdo = $this->pdo;
        $_GET['id'] = $id;
        require BASE_PATH . '/admin_member_profile.php';
    }

    /**
     * POST /admin-member-profile/{id}
     */
    public function updateMemberProfile(string $id): void
    {
        $pdo = $this->pdo;
        $_GET['id'] = $id;
        require BASE_PATH . '/admin_member_profile.php';
    }

    /**
     * GET /admin-loans — Loan approvals.
     */
    public function loans(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/admin_loans.php';
    }

    /**
     * POST /admin-loans — Process loan action.
     */
    public function processLoan(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/admin_loans.php';
    }

    /**
     * GET /admin-finance — Financial books.
     */
    public function finance(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/admin_finance.php';
    }

    /**
     * POST /admin-finance
     */
    public function processFinance(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/admin_finance.php';
    }

    /**
     * GET /admin-welfare — Welfare vetting.
     */
    public function welfare(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/admin_welfare.php';
    }

    /**
     * POST /admin-welfare
     */
    public function processWelfare(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/admin_welfare.php';
    }

    /**
     * GET /admin-attendance — Meeting logs.
     */
    public function attendance(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/admin_attendance.php';
    }

    /**
     * POST /admin-attendance
     */
    public function processAttendance(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/admin_attendance.php';
    }

    /**
     * GET /admin-announcements — Announcements manager.
     */
    public function announcements(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/admin_announcements.php';
    }

    /**
     * POST /admin-announcements
     */
    public function createAnnouncement(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/admin_announcements.php';
    }

    /**
     * GET /admin-sharing — Dividend sharing.
     */
    public function sharing(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/admin_sharing.php';
    }

    /**
     * POST /admin-sharing
     */
    public function processSharing(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/admin_sharing.php';
    }

    /**
     * GET /admin-audit-log — Audit trail.
     */
    public function auditLog(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/admin_audit_log.php';
    }
}
