<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CoordinateDemoRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('demo.enabled')) {
            return $next($request);
        }
        try {
            return Cache::lock('hotel-pos:demo-data-access', 300)->block(15, fn (): Response => $next($request));
        } catch (LockTimeoutException) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Demo data is being refreshed. Please try again shortly.'], 503)->header('Retry-After', '5')
                : response('Demo data is being refreshed. Please try again shortly.', 503)->header('Retry-After', '5');
        }
    }
}
