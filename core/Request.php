<?php
/**
 * Simple HTTP Request wrapper.
 */

namespace Core;

class Request
{
    private array $get;
    private array $post;
    private array $server;
    private array $files;
    private array $cookies;

    public function __construct()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->files = $_FILES;
        $this->cookies = $_COOKIE;
    }

    /**
     * Get a value from POST, then GET.
     */
    public function input(string $key, $default = null)
    {
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }

    /**
     * Get all POST data.
     */
    public function all(): array
    {
        return array_merge($this->get, $this->post);
    }

    /**
     * Get POST data only.
     */
    public function post(string $key = null, $default = null)
    {
        if ($key === null)
            return $this->post;
        return $this->post[$key] ?? $default;
    }

    /**
     * Get GET data only.
     */
    public function query(string $key = null, $default = null)
    {
        if ($key === null)
            return $this->get;
        return $this->get[$key] ?? $default;
    }

    /**
     * Get uploaded file.
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Get the HTTP method.
     */
    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Get the request URI (without query string).
     */
    public function uri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH);

        // Strip the base path (e.g., /jeramy1) from the URI
        $basePath = config('app.base_path', '');
        if ($basePath && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }

        return '/' . ltrim($uri, '/');
    }

    /**
     * Check if this is a POST request.
     */
    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /**
     * Check if this is an AJAX request.
     */
    public function isAjax(): bool
    {
        return ($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    /**
     * Get raw POST body (for JSON/webhook payloads).
     */
    public function rawBody(): string
    {
        return file_get_contents('php://input') ?: '';
    }

    /**
     * Get a server variable.
     */
    public function server(string $key, $default = null)
    {
        return $this->server[$key] ?? $default;
    }

    /**
     * Get client IP address.
     */
    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get user agent.
     */
    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? 'Unknown';
    }
}
