<?php
/**
 * Base Controller.
 * All controllers extend this class.
 */

namespace Core;

class Controller
{
    protected Request $request;
    protected \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * Set the request object (called by Router).
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * Render a view file inside the layout.
     *
     * @param string $view   View path relative to /views (e.g., 'auth/login')
     * @param array  $data   Data to pass to the view
     * @param string|null $layout Layout name (null = 'main', false = no layout)
     */
    protected function view(string $view, array $data = [], $layout = 'main'): void
    {
        // Extract data so variables are available in the view
        extract($data);

        // Make common variables available
        $basePath = config('app.base_path', '');

        // Render the view content into a buffer
        $viewFile = BASE_PATH . '/views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewFile)) {
            error_log("View not found: {$viewFile}");
            echo "View not found: {$view}";
            return;
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // If no layout, output directly
        if ($layout === false || $layout === null) {
            echo $content;
            return;
        }

        // Wrap in layout
        $layoutFile = BASE_PATH . '/views/layouts/' . $layout . '.php';
        if (file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            // No layout file — output content directly
            echo $content;
        }
    }

    /**
     * Render a view WITHOUT a layout (for standalone pages like login/signup).
     */
    protected function viewStandalone(string $view, array $data = []): void
    {
        $this->view($view, $data, false);
    }

    /**
     * Redirect to a URL.
     */
    protected function redirect(string $url): void
    {
        Response::redirect($url);
    }

    /**
     * Send a JSON response.
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        Response::json($data, $statusCode);
    }

    /**
     * Generate the full URL for a path.
     */
    protected function url(string $path): string
    {
        $basePath = config('app.base_path', '');
        return $basePath . '/' . ltrim($path, '/');
    }

    /**
     * Get the PDO instance.
     */
    protected function db(): \PDO
    {
        return $this->pdo;
    }

    /**
     * Get a session value.
     */
    protected function session(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a session value.
     */
    protected function setSession(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Flash a message into the session (available on next request).
     */
    protected function flash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    /**
     * Get and clear a flash message.
     */
    protected function getFlash(string $key): ?string
    {
        $msg = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $msg;
    }
}
