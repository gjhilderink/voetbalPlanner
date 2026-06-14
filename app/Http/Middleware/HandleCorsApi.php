<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCorsApi
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Preflight: stuur direct CORS headers terug zonder de rest van de
        // pipeline (auth/throttle) te raken. Belangrijk voor multipart POST.
        if ($request->getMethod() === 'OPTIONS') {
            return $this->withCorsHeaders(response('', Response::HTTP_NO_CONTENT), $request);
        }

        $response = $next($request);
        return $this->withCorsHeaders($response, $request);
    }

    private function withCorsHeaders($response, Request $request)
    {
        $origin = $request->headers->get('Origin', '*');

        // Vermijd duplicate headers — als Apache mod_headers (of een ander
        // niveau) deze al heeft gezet, vervangen we ze gewoon i.p.v. toevoegen.
        // Browsers blokkeren responses met meerdere Access-Control-Allow-Origin
        // headers stilletjes ('Failed to fetch' zonder verdere uitleg).
        $response->headers->remove('Access-Control-Allow-Origin');
        $response->headers->remove('Access-Control-Allow-Methods');
        $response->headers->remove('Access-Control-Allow-Headers');
        $response->headers->remove('Access-Control-Max-Age');
        $response->headers->remove('Access-Control-Allow-Credentials');

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Vary', 'Origin');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set(
            'Access-Control-Allow-Headers',
            'Authorization, Content-Type, Accept, X-Requested-With, X-XSRF-TOKEN, X-CSRF-TOKEN, Origin'
        );
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }
}
