<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Vercel read-only filesystem fix
$storagePath = '/tmp/storage';
if (!is_dir($storagePath)) {
    mkdir($storagePath . '/framework/cache/data', 0777, true);
    mkdir($storagePath . '/framework/sessions', 0777, true);
    mkdir($storagePath . '/framework/views', 0777, true);
    mkdir($storagePath . '/logs', 0777, true);
    mkdir($storagePath . '/bootstrap/cache', 0777, true);
}
putenv('VIEW_COMPILED_PATH=' . $storagePath . '/framework/views');
putenv('APP_CONFIG_CACHE=' . $storagePath . '/bootstrap/cache/config.php');
putenv('APP_EVENTS_CACHE=' . $storagePath . '/bootstrap/cache/events.php');
putenv('APP_PACKAGES_CACHE=' . $storagePath . '/bootstrap/cache/packages.php');
putenv('APP_ROUTES_CACHE=' . $storagePath . '/bootstrap/cache/routes.php');
putenv('APP_SERVICES_CACHE=' . $storagePath . '/bootstrap/cache/services.php');

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->useStoragePath($storagePath);

$app->handleRequest(Request::capture());
