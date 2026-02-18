<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;

class AuthenticateFilament extends FilamentAuthenticate
{
    protected function redirectTo($request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // Store the admin URL as the intended destination so LoginController
        // redirects back to /admin after successful authentication
        session()->put('url.intended', url('/admin'));

        return route('login');
    }
}
