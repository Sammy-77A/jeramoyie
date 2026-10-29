<?php
/**
 * Global helper functions.
 * Loaded early in the bootstrap — available everywhere.
 */

/**
 * Get a configuration value using dot notation.
 * e.g., config('database.host') reads config/database.php['host']
 */
function config(string $key, $default = null)
{
    static $configs = [];

    $parts = explode('.', $key, 2);
    $file = $parts[0];
    $subKey = $parts[1] ?? null;

    if (!isset($configs[$file])) {
        $path = BASE_PATH . '/config/' . $file . '.php';
        if (file_exists($path)) {
            $configs[$file] = require $path;
        } else {
            $configs[$file] = [];
        }
    }

    if ($subKey === null) {
        return $configs[$file] ?: $default;
    }

    return $configs[$file][$subKey] ?? $default;
}

/**
 * Escape output for HTML (XSS protection).
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a full URL from a path.
 */
function url(string $path = ''): string
{
    $basePath = config('app.base_path', '');
    return $basePath . '/' . ltrim($path, '/');
}

/**
 * Generate a CSRF token input field.
 */
function csrf_field(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

/**
 * Get the current CSRF token.
 */
function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF token.
 */
function verify_csrf(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Format a number as Kenyan currency.
 */
function format_currency($amount): string
{
    return 'KSh ' . number_format((float) $amount, 2);
}

/**
 * Check if the current user is logged in (native member).
 */
function is_logged_in(): bool
{
    return isset($_SESSION['member_id']) && isset($_SESSION['paid']) && $_SESSION['paid'] === 1;
}

/**
 * Check if the current user is an admin.
 */
function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['is_admin'] ?? 0) == 1;
}

/**
 * Get session value with optional default.
 */
function session_get(string $key, $default = null)
{
    return $_SESSION[$key] ?? $default;
}

/**
 * Redirect helper (standalone, outside controller context).
 */
function redirect(string $path): void
{
    \Core\Response::redirect($path);
}

/**
 * Get asset URL (relative to public/).
 */
function asset(string $path): string
{
    return url('public/' . ltrim($path, '/'));
}

/**
 * Log admin activity (preserved from original config.php).
 */
function log_admin_activity(\PDO $pdo, string $action, string $details): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['member_id'])) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO admin_logs (member_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $_SESSION['member_id'],
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        } catch (\PDOException $e) {
            error_log("Audit Log Failed: " . $e->getMessage());
        }
    }
}
