<?php
/**
 * Singleton PDO Database wrapper.
 * Maintains a single connection per request.
 */

namespace Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    /**
     * Get the singleton PDO instance.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$instance;
    }

    /**
     * Initialize the database connection from config.
     */
    private static function connect(): void
    {
        $host = config('database.host', 'localhost');
        $dbname = config('database.name', '');
        $user = config('database.user', 'root');
        $pass = config('database.pass', '');
        $charset = config('database.charset', 'utf8mb4');

        try {
            self::$instance = new PDO(
                "mysql:host={$host};dbname={$dbname};charset={$charset}",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            if (config('app.debug', false)) {
                die("DB connection failed: " . $e->getMessage());
            }
            die("Database connection error. Please try again later.");
        }
    }

    /**
     * Prevent cloning.
     */
    private function __clone()
    {
    }

    /**
     * Convenience: get PDO (alias).
     */
    public static function pdo(): PDO
    {
        return self::getInstance();
    }
}
