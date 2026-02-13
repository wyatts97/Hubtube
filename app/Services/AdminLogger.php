<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class AdminLogger
{
    /**
     * Log an admin action to the activity log.
     *
     * @param string $description  Human-readable description
     * @param string $logName      Log channel: 'admin', 'error', 'auth', 'system'
     * @param array  $properties   Extra context data
     * @param mixed  $subject      Optional Eloquent model the action relates to
     */
    public static function log(
        string $description,
        string $logName = 'admin',
        array $properties = [],
        mixed $subject = null,
    ): void {
        $logger = activity($logName)
            ->withProperties($properties);

        if (Auth::check()) {
            $logger->causedBy(Auth::user());
        }

        if ($subject) {
            $logger->performedOn($subject);
        }

        $logger->log($description);
    }

    /**
     * Log a settings change from an admin panel page.
     */
    public static function settingsSaved(string $page, array $changedKeys = []): void
    {
        static::log(
            "Updated {$page} settings",
            'admin',
            array_filter([
                'page' => $page,
                'changed_keys' => !empty($changedKeys) ? $changedKeys : null,
            ]),
        );
    }

    /**
     * Log a site error or exception.
     */
    public static function error(string $description, array $context = []): void
    {
        static::log($description, 'error', $context);
    }

    /**
     * Log an authentication event.
     */
    public static function auth(string $description, array $context = []): void
    {
        static::log($description, 'auth', $context);
    }
}
