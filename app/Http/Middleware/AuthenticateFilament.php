<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Illuminate\Http\Request;

class AuthenticateFilament extends FilamentAuthenticate
{
    public function handle($request, Closure $next, ...$guards)
    {
        // Livewire sends POST requests to /livewire/update while the admin SPA
        // is open. If the session expires, redirecting to the Inertia login route
        // causes a "405 Method Not Allowed" white overlay inside the SPA. Instead,
        // return a 401 so the frontend can show a graceful "refresh page" prompt.
        if ($this->isLivewireRequest($request) && ! Filament::auth()->check()) {
            return response()->json([
                'message' => 'Your session has expired. Please refresh the page and log in again.',
                'refresh' => true,
            ], 401, ['X-Refresh-Required' => 'true']);
        }

        return parent::handle($request, $next, ...$guards);
    }

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

    private function isLivewireRequest(Request $request): bool
    {
        return $request->hasHeader('X-Livewire')
            || str_contains($request->path(), 'livewire/update');
    }
}
