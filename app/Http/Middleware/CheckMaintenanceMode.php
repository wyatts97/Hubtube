<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
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

            // Allow login/logout routes so admins can authenticate
            if ($request->routeIs('login', 'login.store', 'logout')) {
                return $next($request);
            }

            // Allow the Filament admin panel routes
            if (str_starts_with($request->path(), 'admin')) {
                return $next($request);
            }

            // Return 503 maintenance page
            return Inertia::render('Error', [
                'status' => 503,
                'message' => Setting::get('maintenance_message', 'We are currently undergoing maintenance. Please check back soon.'),
            ])->toResponse($request)->setStatusCode(503);
        }

        return $next($request);
    }
}
