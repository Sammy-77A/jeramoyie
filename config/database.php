<?php
/**
 * Database configuration.
 * Reads from .env via .env.php when available, falls back to defaults.
 */

return [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'name' => getenv('DB_NAME') ?: 'jeramoyie1',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4',
];
