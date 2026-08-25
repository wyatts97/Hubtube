<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

/**
 * Backing model for Filament's admin database-notification bell.
 *
 * Points Illuminate's standard DatabaseNotification schema at a dedicated
 * `filament_notifications` table so it doesn't collide with the app's own
 * `notifications` table (App\Models\Notification), which has a different,
 * pre-existing schema. See the migration for the full rationale.
 */
class FilamentNotification extends DatabaseNotification
{
    protected $table = 'filament_notifications';
}
