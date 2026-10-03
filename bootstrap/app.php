<?php

use App\Http\Middleware\CoordinateDemoRequests;
use App\Http\Middleware\EnsureKitchenDisplayUnlocked;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureSystemUnlocked;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(CoordinateDemoRequests::class);
        $middleware->alias([
            'permission' => EnsurePermission::class,
            'system.lock' => EnsureSystemUnlocked::class,
            'kitchen.pin' => EnsureKitchenDisplayUnlocked::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['pin', 'pin_confirmation']);
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->isMethod('GET') || $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException ||
                $e instanceof AuthenticationException ||
                $e instanceof TokenMismatchException ||
                $e instanceof HttpException) {
                return null;
            }

            return back()->withInput($request->except(['pin', 'pin_confirmation', 'password', 'password_confirmation']))->withErrors('System Error: '.$e->getMessage());
        });
    })->create();

$ensureWritableCompiledViewsPath = static function (ConfigRepository $config): void {
    $configuredPath = (string) $config->get('view.compiled', storage_path('framework/views'));

    if (! is_dir($configuredPath)) {
        @mkdir($configuredPath, 0755, true);
    }

    if (is_dir($configuredPath) && is_writable($configuredPath)) {
        return;
    }

    $fallbackPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hotel-pos-views-'.md5(base_path());

    if (! is_dir($fallbackPath)) {
        @mkdir($fallbackPath, 0755, true);
    }

    $config->set('view.compiled', is_writable($fallbackPath) ? $fallbackPath : sys_get_temp_dir());
};

$app->afterBootstrapping(LoadConfiguration::class, static function (Application $app) use ($ensureWritableCompiledViewsPath): void {
    $ensureWritableCompiledViewsPath($app->make('config'));
});

return $app;
