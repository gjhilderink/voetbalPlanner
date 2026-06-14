<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CORS middleware moet ZOWEL globaal als op de api-stack staan, anders
        // krijgen 404's, validation errors en exceptions geen CORS headers en
        // ziet de browser 'Failed to fetch' i.p.v. de echte foutmelding.
        $middleware->prepend(\App\Http\Middleware\HandleCorsApi::class);
        $middleware->api(prepend: [\App\Http\Middleware\HandleCorsApi::class]);
        $middleware->throttleApi('60,1');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
