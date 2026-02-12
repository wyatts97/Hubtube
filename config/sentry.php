<?php

return [

    // Sentry Laravel SDK Configuration
    // https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/

    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // Capture unhandled exceptions and errors
    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),

    // Performance Monitoring
    // Set traces_sample_rate to a value between 0.0 and 1.0
    // 0.0 = no transactions traced, 1.0 = all transactions traced
    // Recommended: 0.1 (10%) for production, 1.0 for development
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),

    // Profiling (requires traces to be enabled)
    // Set profiles_sample_rate relative to traces_sample_rate
    // If traces = 0.1 and profiles = 0.5, then 5% of requests are profiled
    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.0),

    // Release tracking — auto-detected from git, or set manually
    'release' => env('SENTRY_RELEASE'),

    // Environment tag (auto-detected from APP_ENV)
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    // Exceptions to never report to Sentry
    'ignore_exceptions' => [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
    ],

];
