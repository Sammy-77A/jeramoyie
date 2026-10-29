<?php
/**
 * CSRF protection for the legacy flat-PHP pages (loaded from config.php).
 *
 *  - Every POST must carry the session's token (field "csrf_token" or header X-CSRF-Token).
 *  - The token field is injected into every <form method="post"> in the page output,
 *    so individual pages don't need editing.
 *
 * The community portal keeps its own session key, so its existing
 * csrf_input()/verify_csrf_token() forms keep working unchanged.
 */
(function () {
    if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');

    // Server-to-server endpoints (no session, no browser) and pages with their own token.
    $exempt = [
        'callback.php', 'callback_url.php', 'callback_handler.php', 'callback_reset.php',
        'b2c_callback_url.php', 'b2c_result.php', 'b2c_timeout_url.php', 'B2CResultURL.php',
        'login.php', 'login_backup.php',
    ];
    $isCommunity = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/community/') !== false;
    // The community login keeps its own token; the root and admin login pages are exempt only if they own one.
    if (in_array($script, $exempt, true) && !($script === 'login.php' && strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false)
        && !($script === 'login.php' && $isCommunity)) {
        return;
    }

    $key = $isCommunity ? 'community_csrf' : 'csrf_token';
    if (empty($_SESSION[$key])) {
        $_SESSION[$key] = bin2hex(random_bytes(32));
    }
    $token = $_SESSION[$key];

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $given = (string)($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!hash_equals($token, $given)) {
            http_response_code(403);
            echo '<h1>403 — Session expired</h1><p>Please go back, refresh the page and try again.</p>';
            exit;
        }
    }

    ob_start(function (string $html) use ($token) {
        foreach (headers_list() as $h) {
            if (stripos($h, 'content-type:') === 0 && stripos($h, 'text/html') === false) {
                return $html; // JSON, PDF, CSV... leave untouched
            }
        }
        if (stripos($html, '<form') === false) {
            return $html;
        }
        $field = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
        // Insert right after each POST form's opening tag. A form that already has its own
        // csrf_token field later in the body just overrides this one (same key in the community portal).
        $out = preg_replace(
            '/(<form\b[^>]*\bmethod\s*=\s*["\']?post["\']?[^>]*>)/i',
            '$1' . $field,
            $html
        );
        return $out ?? $html;
    });
})();
