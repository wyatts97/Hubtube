<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        \App\Providers\Filament\AdminPanelProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: [
            'age_verified',
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'age.verified' => \App\Http\Middleware\AgeVerification::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle CSRF token mismatch for Inertia requests
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() === 419 || str_contains($e->getMessage(), 'CSRF')) {
                if ($request->inertia()) {
                    return Inertia::render('Error', [
                        'status' => 419,
                        'message' => 'Your session has expired. Please refresh the page.',
                    ])->toResponse($request)->setStatusCode(419);
                }
                
                // For non-Inertia requests, redirect back with error
                return redirect()->back()->withErrors([
                    'session' => 'Your session has expired. Please try again.',
                ]);
            }
        });
    })->create();
