<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetAdminTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        static::setTimezone();

        return $next($request);
    }

    public static function setTimezone(): void
    {
        $timezone = config('app.timezone');

        try {
            $timezone = Setting::get('site_timezone', $timezone) ?: $timezone;
        } catch (\Throwable $e) {
            // Database may not be available during install/boot
        }

        if (! in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            return;
        }

        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);

        // MySQL/MariaDB TIMESTAMP columns convert between the session timezone
        // and UTC. If we change PHP's default timezone without telling the DB,
        // timestamps are stored/retrieved with the wrong offset and can appear
        // hours in the future or past in the admin panel.
        try {
            $offset = (new \DateTime('now', new \DateTimeZone($timezone)))->format('P');
            foreach (config('database.connections') as $name => $config) {
                $driver = $config['driver'] ?? null;
                if (in_array($driver, ['mysql', 'mariadb'], true)) {
                    DB::connection($name)->statement("SET time_zone = '{$offset}'");
                }
            }
        } catch (\Throwable) {
            // Ignore: connection may not be available during install/boot/tests.
        }
    }
}
