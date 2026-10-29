<?php
/**
 * CSRF Middleware.
 * Validates CSRF token on POST requests.
 */

namespace App\Middleware;

use Core\Middleware;
use Core\Request;
use Core\Response;

class CsrfMiddleware extends Middleware
{
    public function handle(Request $request): void
    {
        if ($request->method() === 'POST') {
            $token = $request->post('csrf_token') ?? $request->post('csrf') ?? '';

            if (!verify_csrf($token)) {
                http_response_code(403);
                echo '<h1>403 — Session Expired</h1>';
                echo '<p>Your session has expired. Please <a href="javascript:history.back()">go back</a> and try again.</p>';
                exit;
            }
        }

        // Ensure a token exists for forms
        csrf_token();
    }
}
