<?php
/**
 * Community Module — Database Bootstrap
 * Reuses the main system's config.php for PDO connection.
 * Hardens session configuration for public-facing portal.
 */

// Harden session cookie settings BEFORE session_start()
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', 3600); // 1 hour
    ini_set('session.cookie_lifetime', 0); // session cookie (expires on browser close)
    ini_set('session.cookie_secure', 1); // HTTPS only in production
    session_start();
}
require_once __DIR__ . '/../../config.php';
