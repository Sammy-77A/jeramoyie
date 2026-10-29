<?php
// .env.php - loads KEY=VALUE lines from .env into the environment.
// Plain line parser (not parse_ini_file) so values may contain ; % # = and other special characters.
$envFile = __DIR__ . '/.env';

if (!file_exists($envFile)) {
    die('.env file not found! Create it in the project root.');
}

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || $line[0] === ';' || strpos($line, '=') === false) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $key = trim($key);
    $value = trim($value);
    // Remove one pair of surrounding quotes
    $value = preg_replace('/^"(.*)"$|^\'(.*)\'$/s', '$1$2', $value);
    putenv("$key=" . $value);
}
