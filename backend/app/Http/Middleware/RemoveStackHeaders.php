<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RNF-05: no header may reveal the framework, language or server.
 */
class RemoveStackHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        header_remove('X-Powered-By');
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
