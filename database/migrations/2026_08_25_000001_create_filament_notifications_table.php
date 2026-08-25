<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backing store for Filament's admin-panel database notification bell
 * (->databaseNotifications() in AdminPanelProvider).
 *
 * This is intentionally a separate table from the app's own `notifications`
 * table (App\Models\Notification, the user-facing in-app notification
 * system): that table has a bespoke schema (int id, user_id, title, message)
 * that predates this feature and is incompatible with Laravel's standard
 * polymorphic notifications schema that Filament's Livewire notification
 * center expects (uuid id, notifiable_type/notifiable_id, data json). Rather
 * than migrate the existing table (and the app-facing notification feature
 * built on it), Filament notifications get their own table + model
 * (App\Models\FilamentNotification) and User::notifications() is pointed at
 * it — see User::appNotifications() for the original relation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filament_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filament_notifications');
    }
};
