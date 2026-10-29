<?php
/**
 * Lightweight Router.
 * Supports GET and POST routes with middleware and route parameters.
 */

namespace Core;

class Router
{
    private array $routes = [];
    private array $middlewareAliases = [];
    private string $groupPrefix = '';
    private array $groupMiddleware = [];

    /**
     * Register a GET route.
     */
    public function get(string $uri, string $action, array $middleware = []): void
    {
        $this->addRoute('GET', $uri, $action, $middleware);
    }

    /**
     * Register a POST route.
     */
    public function post(string $uri, string $action, array $middleware = []): void
    {
        $this->addRoute('POST', $uri, $action, $middleware);
    }

    /**
     * Register both GET and POST for the same URI.
     */
    public function any(string $uri, string $action, array $middleware = []): void
    {
        $this->addRoute('GET', $uri, $action, $middleware);
        $this->addRoute('POST', $uri, $action, $middleware);
    }

    /**
     * Group routes with a common prefix and middleware.
     */
    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $prevPrefix = $this->groupPrefix;
        $prevMiddleware = $this->groupMiddleware;

        $this->groupPrefix = $prevPrefix . $prefix;
        $this->groupMiddleware = array_merge($prevMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix = $prevPrefix;
        $this->groupMiddleware = $prevMiddleware;
    }

    /**
     * Register a middleware alias.
     */
    public function aliasMiddleware(string $name, string $class): void
    {
        $this->middlewareAliases[$name] = $class;
    }

    /**
     * Internal: add a route to the routes table.
     */
    private function addRoute(string $method, string $uri, string $action, array $middleware): void
    {
        $fullUri = $this->groupPrefix . $uri;
        $fullMiddleware = array_merge($this->groupMiddleware, $middleware);

        $this->routes[] = [
            'method' => $method,
            'uri' => $fullUri,
            'action' => $action,          // e.g., "AuthController@login"
            'middleware' => $fullMiddleware,
        ];
    }

    /**
     * Match the current request to a route, run middleware, and dispatch.
     */
    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $uri = $request->uri();

        // Normalize: remove trailing slash (except for root)
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchUri($route['uri'], $uri);
            if ($params !== false) {
                // Run middleware pipeline
                $this->runMiddleware($route['middleware'], $request);

                // Dispatch to controller
                $this->callAction($route['action'], $request, $params);
                return;
            }
        }

        // No route matched — 404
        $this->handle404($request);
    }

    /**
     * Match a route pattern against an actual URI.
     * Supports simple parameters like {id}.
     * Returns array of params on match, false otherwise.
     */
    private function matchUri(string $routeUri, string $requestUri): array|false
    {
        // Exact match
        if ($routeUri === $requestUri) {
            return [];
        }

        // Check for parameterized routes (e.g., /admin/member/{id})
        $pattern = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $routeUri);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $requestUri, $matches)) {
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            return $params;
        }

        return false;
    }

    /**
     * Run the middleware stack.
     */
    private function runMiddleware(array $middlewareList, Request $request): void
    {
        foreach ($middlewareList as $name) {
            $class = $this->middlewareAliases[$name] ?? $name;

            if (!class_exists($class)) {
                // Try loading from App\Middleware namespace
                $fqcn = 'App\\Middleware\\' . $class;
                if (!class_exists($fqcn)) {
                    error_log("Middleware not found: {$class}");
                    continue;
                }
                $class = $fqcn;
            }

            $middleware = new $class();
            $middleware->handle($request);
        }
    }

    /**
     * Call a controller action (e.g., "AuthController@login").
     */
    private function callAction(string $action, Request $request, array $params): void
    {
        [$controllerName, $method] = explode('@', $action);

        $controllerClass = 'App\\Controllers\\' . $controllerName;

        if (!class_exists($controllerClass)) {
            error_log("Controller not found: {$controllerClass}");
            $this->handle404($request);
            return;
        }

        $controller = new $controllerClass();
        $controller->setRequest($request);

        if (!method_exists($controller, $method)) {
            error_log("Method not found: {$controllerClass}@{$method}");
            $this->handle404($request);
            return;
        }

        // Call with route params
        call_user_func_array([$controller, $method], $params);
    }

    /**
     * Handle 404 — no matching route.
     */
    private function handle404(Request $request): void
    {
        http_response_code(404);

        $errorView = BASE_PATH . '/views/errors/404.php';
        if (file_exists($errorView)) {
            include $errorView;
        } else {
            echo '<h1>404 — Page Not Found</h1>';
        }
        exit;
    }
}
