<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function ($schedule) {
        $schedule->command('orders:sync-status')->everyFifteenMinutes();
        $schedule->command('orders:complete-old')->cron('*/20 * * * *');
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Aliases for custom middleware
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);

        // Web middleware group
        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // API middleware group
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class, // 👈 Sanctum middleware for SPA/API auth
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();


    
    


    
