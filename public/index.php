<?php
/**
 * Front Controller — Single entry point for the application.
 *
 * All HTTP requests (except static files and index.html) are routed here
 * by .htaccess. This file bootstraps the framework and dispatches the request.
 */

// Define the base path (project root)
define('BASE_PATH', dirname(__DIR__));

// Load the autoloader
require_once BASE_PATH . '/core/Autoloader.php';
\Core\Autoloader::registerDefaults();

// Boot and run the application
$app = new \Core\App();
$app->run();
