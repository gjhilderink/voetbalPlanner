<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// CORS is handled by HandleCorsApi middleware — do NOT add raw header() calls here.
// Symfony sends non-Content-Type headers with replace=false, so any header set here
// AND by the middleware would produce duplicate values (e.g. "*, *").

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
