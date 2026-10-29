<?php
/**
 * CommunityAdminController — Community admin portal.
 */

namespace App\Controllers;

use Core\Controller;

class CommunityAdminController extends Controller
{
    public function dashboard(): void
    {
        require BASE_PATH . '/community/admin/community-dashboard.php';
    }
    public function customers(): void
    {
        require BASE_PATH . '/community/admin/community-customers.php';
    }
    public function loans(): void
    {
        require BASE_PATH . '/community/admin/community-loans.php';
    }
    public function processLoan(): void
    {
        require BASE_PATH . '/community/admin/community-loans.php';
    }
    public function products(): void
    {
        require BASE_PATH . '/community/admin/community-products.php';
    }
    public function processProduct(): void
    {
        require BASE_PATH . '/community/admin/community-products.php';
    }
    public function reports(): void
    {
        require BASE_PATH . '/community/admin/community-reports.php';
    }
}
