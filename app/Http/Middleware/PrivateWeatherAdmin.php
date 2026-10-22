<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class PrivateWeatherAdmin
{
    public function handle(Request $r, Closure $next)
    {
        $response = $next($r);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
