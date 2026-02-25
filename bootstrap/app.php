<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Force file sessions during installation so CSRF works before Redis/DB is configured
if (!file_exists(dirname(__DIR__) . '/storage/installed')) {
    $_ENV['SESSION_DRIVER'] = 'file';
    $_SERVER['SESSION_DRIVER'] = 'file';
    putenv('SESSION_DRIVER=file');
}

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders(array_filter([
        \App\Providers\Filament\AdminPanelProvider::class,
        class_exists(\Sentry\Laravel\ServiceProvider::class) ? \Sentry\Laravel\ServiceProvider::class : null,
    ]))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust Cloudflare proxy IPs for correct client IP detection and HTTPS
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
                     Request::HEADER_X_FORWARDED_HOST |
                     Request::HEADER_X_FORWARDED_PORT |
                     Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->encryptCookies(except: [
            'age_verified',
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\CheckMaintenanceMode::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\AddSecurityHeaders::class,
        ]);

        $middleware->alias([
            'age.verified' => \App\Http\Middleware\AgeVerification::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'installed' => \App\Http\Middleware\CheckInstalled::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->statefulApi();

        $middleware->throttleApi('60,1');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Report all unhandled exceptions to Sentry (if DSN is configured)
        if (class_exists(\Sentry\Laravel\Integration::class)) {
            \Sentry\Laravel\Integration::handles($exceptions);
        }

        // Log major errors to the activity log for admin visibility
        $exceptions->report(function (\Throwable $e) {
            // Only log server errors and critical exceptions, skip 4xx client errors
            $skipClasses = [
                \Illuminate\Auth\AuthenticationException::class,
                \Illuminate\Validation\ValidationException::class,
                \Illuminate\Session\TokenMismatchException::class,
                \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
                \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
            ];

            foreach ($skipClasses as $class) {
                if ($e instanceof $class) {
                    return false; // Let Laravel handle normally, don't double-log
                }
            }

            // Skip 4xx HTTP exceptions
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException && $e->getStatusCode() < 500) {
                return false;
            }

            try {
                \App\Services\AdminLogger::error(
                    class_basename($e) . ': ' . \Illuminate\Support\Str::limit($e->getMessage(), 200),
                    [
                        'exception' => get_class($e),
                        'file' => $e->getFile() . ':' . $e->getLine(),
                        'url' => request()->fullUrl(),
                    ]
                );
            } catch (\Throwable) {
                // Silently fail — don't let logging break the app
            }

            return false; // Don't stop other reporters (Sentry, log files)
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            $status = $e->getStatusCode();

            // Handle CSRF token mismatch (skip for admin)
            $isAdmin = $request->is('admin/*') || $request->is('admin') || $request->is('livewire/*');
            if ($status === 419 || str_contains($e->getMessage(), 'CSRF')) {
                if ($isAdmin) {
                    return null;
                }

                if ($request->inertia()) {
                    return Inertia::render('Error', [
                        'status' => 419,
                        'message' => 'Your session has expired. Please refresh the page.',
                    ])->toResponse($request)->setStatusCode(419);
                }
                
                return redirect()->back()->withErrors([
                    'session' => 'Your session has expired. Please try again.',
                ]);
            }

            // Render 404, 403, 500, 503 via Inertia Error page (skip for admin)
            if (in_array($status, [404, 403, 500, 503])) {
                if ($isAdmin) {
                    return null;
                }

                return Inertia::render('Error', [
                    'status' => $status,
                    'message' => $e->getMessage() ?: null,
                ])->toResponse($request)->setStatusCode($status);
            }
        });
    })->create();
