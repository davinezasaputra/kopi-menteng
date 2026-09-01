<?php

use App\Http\Middleware\AddRequestId;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequirePlatformAdmin;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\Idempotency;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            require base_path('routes/api_v1.php');
            Route::prefix('api')->group(function (): void {
                require base_path('routes/api_master.php');
                require base_path('routes/api_hrm_automation.php');
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'request.id' => AddRequestId::class,
            'tenant' => ResolveTenantContext::class,
            'permission' => RequirePermission::class,
            'platform.admin' => RequirePlatformAdmin::class,
            'idempotency' => Idempotency::class,
            'security.headers' => SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
