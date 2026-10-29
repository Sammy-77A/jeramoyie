<?php
/**
 * Lightweight PSR-4-style Autoloader
 * Maps namespace prefixes to directories.
 */

namespace Core;

class Autoloader
{
    private static array $prefixes = [];

    /**
     * Register the autoloader with spl_autoload_register.
     */
    public static function register(): void
    {
        spl_autoload_register([self::class, 'loadClass']);
    }

    /**
     * Add a namespace prefix → base directory mapping.
     */
    public static function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        self::$prefixes[$prefix] = $baseDir;
    }

    /**
     * Attempt to load a class file for a fully-qualified class name.
     */
    public static function loadClass(string $class): bool
    {
        foreach (self::$prefixes as $prefix => $baseDir) {
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                continue;
            }

            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }
        return false;
    }

    /**
     * Register default namespace mappings for the Jera Moyie app.
     */
    public static function registerDefaults(): void
    {
        self::register();

        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);

        self::addNamespace('Core', $base . '/core');
        self::addNamespace('App\\Controllers', $base . '/app/controllers');
        self::addNamespace('App\\Models', $base . '/app/models');
        self::addNamespace('App\\Services', $base . '/app/services');
        self::addNamespace('App\\Middleware', $base . '/app/middleware');
        self::addNamespace('App\\Helpers', $base . '/app/helpers');
    }
}
