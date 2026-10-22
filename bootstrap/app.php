<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\VerifyRetellSignature;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::prefix('retell')
                ->name('retell.')
                ->middleware(VerifyRetellSignature::class)
                ->group(base_path('routes/retell.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Query exceptions embed bound values (patient names, birth dates) in
        // their message. Log only the error code and connection.
        $exceptions->report(function (QueryException $e) {
            Log::error('Database query failed', ['code' => $e->getCode(), 'connection' => $e->connectionName]);

            return false;
        });
    })->create();
