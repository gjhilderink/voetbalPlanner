<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCorsApi
{
    public function __construct(private ExceptionHandler $exceptions) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->isMethod('OPTIONS')) {
            return response()->noContent()
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With')
                ->header('Access-Control-Max-Age', '86400');
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Exceptions thrown deeper in the pipeline (e.g. ValidationException from
            // Auth::attempt) would otherwise bypass this middleware's return path and
            // produce a response without CORS headers, causing browsers to report
            // "Failed to fetch". Render the exception ourselves so we can annotate it.
            $response = $this->exceptions->render($request, $e);
        }

        return $this->addCorsHeaders($response);
    }

    private function addCorsHeaders(Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With');
        return $response;
    }
}
