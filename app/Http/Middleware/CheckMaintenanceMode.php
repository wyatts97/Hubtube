<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Setting::get('maintenance_mode', false)) {
            // Allow admins through
            if ($request->user() && $request->user()->is_admin) {
                return $next($request);
            }

            // Allow paths that must work during maintenance
            $path = $request->path();
            $allowedPrefixes = [
                'admin',        // Filament admin panel
                'livewire',     // Livewire requests (Filament uses Livewire)
                'login',        // Login page (GET and POST)
                'logout',       // Logout
                'sanctum',      // CSRF cookie
                'build',        // Vite assets
                'vendor',       // Filament assets
                'css',          // Stylesheets
                'js',           // Scripts
                'favicon',      // Favicon
            ];

            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    return $next($request);
                }
            }

            $message = Setting::get('maintenance_message', 'We are currently undergoing maintenance. Please check back soon.');

            return response()
                ->view('maintenance', ['message' => $message], 503);
        }

        return $next($request);
    }
}
