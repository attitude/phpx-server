<?php declare(strict_types=1);

// Dev router for `php -S`: serve real files (assets, /dist/*) straight from
// disk, route everything else to the app entry.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

require __DIR__ . '/index.php';
