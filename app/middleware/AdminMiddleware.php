<?php
/**
 * Admin Middleware.
 * Requires the user to be an admin (is_admin = 1).
 */

namespace App\Middleware;

use Core\Middleware;
use Core\Request;
use Core\Response;

class AdminMiddleware extends Middleware
{
    public function handle(Request $request): void
    {
        if (!isset($_SESSION['member_id']) || ($_SESSION['is_admin'] ?? 0) != 1) {
            Response::redirect('/profile');
        }
    }
}
