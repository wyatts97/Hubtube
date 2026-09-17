<?php

use App\Services\SeoService;
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
            \App\Http\Middleware\TrackVisitor::class,
            // Signs out accounts banned or suspended while they had a session.
            \App\Http\Middleware\EnsureUserIsNotBanned::class,
        ]);

        $middleware->alias([
            'age.verified' => \App\Http\Middleware\AgeVerification::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'installed' => \App\Http\Middleware\CheckInstalled::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
            'verified.if-required' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'registration.open' => \App\Http\Middleware\EnsureRegistrationOpen::class,
        ]);

        $middleware->statefulApi();

        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
            'ccbill/webhook',
        ]);

        $middleware->throttleApi('60,1');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Report all unhandled exceptions to Sentry (if DSN is configured)
        if (class_exists(\Sentry\Laravel\Integration::class)) {
            \Sentry\Laravel\Integration::handles($exceptions);
        }


        /**
         * Render the SPA error page with the application's normal shared props.
         *
         * Inertia's shared data is registered by HandleInertiaRequests::handle(),
         * and most errors are raised before that middleware runs: a failed
         * route-model binding comes from SubstituteBindings, which sits ahead of
         * it, and an unmatched URL never reaches the web group at all. Rendering
         * straight from here therefore produced a page carrying nothing but
         * `status`, `message` and `seo` — so every label in the layout rendered
         * as its raw translation key ("nav.home", "auth.login"), and the site's
         * own name and logo were replaced by the built-in fallback.
         *
         * share() is re-run rather than duplicated, so the error page cannot
         * drift from the rest of the app. It reads $request->user() and
         * csrf_token() eagerly, and both need a session; when there is none, the
         * session-free subset still gives the page its translations and its
         * branding. If even that fails — a database that is down is exactly the
         * sort of thing that lands you here — the page renders bare rather than
         * throwing a second exception on the way out.
         */
        $errorPage = function (Request $request, int $status, ?string $message, ?string $seoTitle = null) {
            $shared = app(\App\Http\Middleware\HandleInertiaRequests::class);

            foreach (['share', 'shareForErrorPage'] as $method) {
                try {
                    Inertia::share($shared->{$method}($request));
                    break;
                } catch (\Throwable) {
                    Inertia::flushShared();
                }
            }

            $props = [
                'status' => $status,
                'message' => $message,
                'seo' => app(SeoService::class)->forPrivatePage($seoTitle ?? (string) $status, alwaysNoindex: true),
            ];

            try {
                return Inertia::render('Error', $props)->toResponse($request)->setStatusCode($status);
            } catch (\Throwable) {
                // A shared prop threw while it was being resolved. Without them
                // the page is plain, but it is still the right status code and
                // still a page rather than a stack trace.
                Inertia::flushShared();

                return Inertia::render('Error', $props)->toResponse($request)->setStatusCode($status);
            }
        };

        /**
         * The part of an HttpException's message a visitor should see.
         *
         * abort(451, 'This video is not available in your country') is written
         * to be read. The text Laravel builds for a failed route-model binding
         * is not: "No query results for model [App\Models\Video] muscle-boy"
         * names an internal class and the id that was tried, and it was being
         * printed on the 404 page. Anything naming a class, or coming from that
         * binding failure, is dropped so the page falls back to its own copy.
         */
        $visitorMessage = function (HttpException $e): ?string {
            $message = trim($e->getMessage());

            if ($message === '' || str_contains($message, '\\') || str_starts_with($message, 'No query results')) {
                return null;
            }

            return \Illuminate\Support\Str::limit($message, 200);
        };

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

        // Handle CSRF / session expiry for Livewire (admin panel polling pages).
        // Livewire sends XHR requests; when the session expires these get 419.
        // Returning a redirect forces Livewire to do a full page reload which
        // regenerates the session + CSRF token — no more browser "page expired" popups.
        $exceptions->render(function (TokenMismatchException $e, Request $request) use ($errorPage) {
            // Livewire requests: return a 409 with redirect header so Livewire
            // triggers a full page reload instead of showing a JS confirm dialog.
            if ($request->hasHeader('X-Livewire')) {
                return response('', 409, [
                    'X-Livewire-Redirect' => $request->header('Referer', url('/admin')),
                ]);
            }

            // Admin panel (non-Livewire): redirect back to refresh the page
            if ($request->is('admin/*') || $request->is('admin')) {
                return redirect($request->fullUrl());
            }

            // Inertia (frontend)
            if ($request->inertia()) {
                return $errorPage($request, 419, null, 'Session Expired');
            }

            return redirect()->back()->withErrors([
                'session' => 'Your session has expired. Please try again.',
            ]);
        });

        $exceptions->render(function (HttpException $e, Request $request) use ($errorPage, $visitorMessage) {
            $status = $e->getStatusCode();

            // 419 HttpException (secondary path — primary is TokenMismatchException above)
            $isAdmin = $request->is('admin/*') || $request->is('admin') || $request->is('livewire/*');
            if ($status === 419 || str_contains($e->getMessage(), 'CSRF')) {
                if ($isAdmin || $request->hasHeader('X-Livewire')) {
                    return redirect($request->header('Referer', url('/admin')));
                }

                if ($request->inertia()) {
                    return $errorPage($request, 419, null, 'Session Expired');
                }

                return redirect()->back()->withErrors([
                    'session' => 'Your session has expired. Please try again.',
                ]);
            }

            // Render 404, 403, 451, 500, 503 via Inertia Error page (skip for admin)
            if (in_array($status, [404, 403, 451, 500, 503])) {
                if ($isAdmin) {
                    return null;
                }

                return $errorPage($request, $status, $visitorMessage($e));
            }
        });
    })->create();
