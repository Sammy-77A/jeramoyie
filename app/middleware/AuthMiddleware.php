<?php
/**
 * Authentication Middleware.
 * Requires a logged-in native member.
 */

namespace App\Middleware;

use Core\Middleware;
use Core\Request;
use Core\Response;

class AuthMiddleware extends Middleware
{
    public function handle(Request $request): void
    {
        if (!isset($_SESSION['member_id']) || !isset($_SESSION['paid']) || $_SESSION['paid'] !== 1) {
            Response::redirect('/login');
        }
    }
}
