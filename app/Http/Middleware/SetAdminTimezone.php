<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAdminTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        $timezone = config('app.timezone');

        try {
            $timezone = Setting::get('site_timezone', $timezone) ?: $timezone;
        } catch (\Throwable $e) {
            // Database may not be available during install/boot
        }

        if (in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }

        return $next($request);
    }
}
