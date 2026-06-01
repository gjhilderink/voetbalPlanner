<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HandleCorsApi
{
    public function handle(Request $request, Closure $next): mixed
    {
        // OPTIONS is answered at the Apache level (see public/.htaccess).
        // For all other methods, just pass through — Apache's mod_headers
        // already sets Access-Control-Allow-Origin on every response.
        return $next($request);
    }
}
