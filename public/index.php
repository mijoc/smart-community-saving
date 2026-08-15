<?php

use Illuminate\Http\Request;

// When this file is used as the router for PHP's built-in dev server
// (`php -S 0.0.0.0:5000 -t public public/index.php`), every request — even
// for real static files — is handed to this script. Returning false here
// tells the built-in server to serve the file directly. Without this, URLs
// like `/storage/avatars/foo.jpg` (the symlinked uploaded avatars) never
// reach the filesystem and get routed through Laravel instead.
if (PHP_SAPI === 'cli-server') {
    $uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
    $path = __DIR__ . $uri;
    if ($uri !== '/' && file_exists($path) && is_file($path)) {
        return false;
    }
}

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
