<?php
/**
 * PageController — Static pages and misc routes.
 * Extracted from: error.php, constitution.html, events.php
 */

namespace App\Controllers;

use Core\Controller;

class PageController extends Controller
{
    /**
     * GET /constitution
     */
    public function constitution(): void
    {
        require BASE_PATH . '/constitution.html';
    }

    /**
     * GET /error — Error page.
     */
    public function error(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/error.php';
    }

    /**
     * GET /events — Events page.
     */
    public function events(): void
    {
        $pdo = $this->pdo;
        require BASE_PATH . '/events.php';
    }
}
