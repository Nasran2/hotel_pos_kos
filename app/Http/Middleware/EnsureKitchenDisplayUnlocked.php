<?php

namespace App\Http\Middleware;

use App\Services\KitchenDisplayAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKitchenDisplayUnlocked
{
    public function __construct(private readonly KitchenDisplayAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->access->isUnlocked($request)) {
            $this->access->lock($request);

            return $request->expectsJson()
                ? response()->json(['message' => 'Kitchen display locked. Enter the PIN again.'], 401)->header('Cache-Control', 'no-store')
                : redirect()->route('kod.index');
        }

        return $next($request)->header('Cache-Control', 'no-store');
    }
}
