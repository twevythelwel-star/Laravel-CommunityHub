<?php

/**
 * Router for PHP's built-in web server.
 *
 * `php artisan serve` cannot bind on this machine — Herd installs `php` as a
 * .bat wrapper, and Symfony Process loses the child, so Laravel reports
 * "Failed to listen on 127.0.0.1:<port> (reason: ?)" on every port. Raw
 * `php -S` binds fine, so .claude/launch.json runs that against this router
 * instead. Laravel shipped exactly this file before the slim skeleton dropped
 * it.
 *
 *     php -S 127.0.0.1:8000 -t public server.php
 */
$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// Let the built-in server serve real files (assets, build output) as-is.
if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
