<?php
/**
 * Simple HTTP Response helper.
 */

namespace Core;

class Response
{
    /**
     * Send a JSON response.
     */
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Redirect to a URL.
     */
    public static function redirect(string $url, int $statusCode = 302): void
    {
        // If URL starts with /, prepend the base path
        if ($url[0] === '/' && !preg_match('#^https?://#', $url)) {
            $basePath = config('app.base_path', '');
            $url = $basePath . $url;
        }

        http_response_code($statusCode);
        header('Location: ' . $url);
        exit;
    }

    /**
     * Set HTTP status code.
     */
    public static function setStatusCode(int $code): void
    {
        http_response_code($code);
    }

    /**
     * Send a plain text response.
     */
    public static function text(string $text, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/plain');
        echo $text;
        exit;
    }
}
