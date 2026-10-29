<?php
/**
 * Application Bootstrap.
 * Loads config, autoloader, session, router, and dispatches the request.
 */

namespace Core;

class App
{
    private Router $router;
    private Request $request;

    public function __construct()
    {
        // Set timezone
        date_default_timezone_set('Africa/Nairobi');

        // Load .env variables
        $envPhp = BASE_PATH . '/.env.php';
        if (file_exists($envPhp)) {
            require_once $envPhp;
        }

        // Load helper functions (config(), e(), url(), etc.)
        require_once BASE_PATH . '/app/helpers/functions.php';

        // Session configuration — same secure settings as original config.php
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_only_cookies', 1);
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', 1);
            }
            session_start();
        }

        $this->request = new Request();
        $this->router = new Router();

        // Register middleware aliases
        $this->router->aliasMiddleware('auth', 'App\\Middleware\\AuthMiddleware');
        $this->router->aliasMiddleware('admin', 'App\\Middleware\\AdminMiddleware');
        $this->router->aliasMiddleware('csrf', 'App\\Middleware\\CsrfMiddleware');

        // Load route definitions
        $router = $this->router;
        require BASE_PATH . '/routes/web.php';
    }

    /**
     * Run the application — dispatch the request to the router.
     */
    public function run(): void
    {
        $this->router->dispatch($this->request);
    }
}
