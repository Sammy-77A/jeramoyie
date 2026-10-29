<?php
/**
 * Guards for scripts that must not be run by anonymous web visitors.
 */

/** One-off setup/maintenance scripts: command line only. */
function require_cli(): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }
}

/**
 * Cron-style scripts: command line, or the web with ?key=<CRON_SECRET>.
 * With no CRON_SECRET configured, web access is refused.
 */
function require_cli_or_cron_secret(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    require_once __DIR__ . '/../.env.php';
    $secret = getenv('CRON_SECRET');
    $given = (string)($_GET['key'] ?? '');
    if ($secret === false || $secret === '' || !hash_equals($secret, $given)) {
        http_response_code(403);
        exit;
    }
}
