<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isLocked() && ! $request->user()?->can('system_tools.view')) {
            abort(503);
        }

        return $next($request);
    }

    private function isLocked(): bool
    {
        static $locked;

        if ($locked !== null) {
            return $locked;
        }

        if (! Schema::hasTable('settings')) {
            return $locked = false;
        }

        return $locked = filter_var(
            DB::table('settings')->where('group', 'system_tools')->where('key', 'maintenance_enabled')->value('value') ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
