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

        // Het afrekenen in de ticketshop draait bewust zonder sessie: die winkel
        // hangt in een iframe op de website van een club, en een sessiecookie
        // met SameSite=lax komt op een ander domein niet mee - dan zou elke
        // bestelling stranden op een 419.
        //
        // Wat ervoor in de plaats komt: een limiet van twintig pogingen per
        // minuut op die route, reCAPTCHA als de beheerder dat aan heeft staan,
        // en het feit dat er niets onomkeerbaars gebeurt. Het ergste wat iemand
        // kan aanrichten is een onbetaalde bestelling die na een half uur
        // vanzelf verloopt en zijn kaarten teruggeeft.
        $middleware->validateCsrfTokens(except: [
            '*/ticketshop/*/afrekenen',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // De snelheidsbegrenzer gooit "Too Many Attempts." — Engels, zonder
        // uitleg, en de app zet die tekst rauw op het scherm. Wie een
        // koppelverzoek indient voor zijn tweede kind ziet dat en denkt dat er
        // iets stuk is.
        $exceptions->render(function (
            \Illuminate\Http\Exceptions\ThrottleRequestsException $e,
            \Illuminate\Http\Request $request,
        ) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            $seconden = (int) ($e->getHeaders()['Retry-After'] ?? 60);
            $wacht = $seconden > 60
                ? ceil($seconden / 60) . ' minuten'
                : $seconden . ' seconden';

            return response()->json([
                'success' => false,
                'message' => "Te veel pogingen achter elkaar. Probeer het over {$wacht} opnieuw.",
            ], 429, $e->getHeaders());
        });
    })->create();
