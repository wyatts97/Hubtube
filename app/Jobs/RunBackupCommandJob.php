<?php

namespace App\Jobs;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs backup:run or backup:clean for the Backups page.
 *
 * A backup can take minutes, which a web request would not survive (PHP-FPM
 * and proxy timeouts). It runs on the media-compress queue: serial, low CPU
 * priority and a long timeout, which suits a backup as well as a re-encode.
 */
class RunBackupCommandJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public string $command, public ?int $userId = null)
    {
        $this->onQueue('media-compress');
    }

    /** One backup or cleanup at a time. */
    public function uniqueId(): string
    {
        return 'backup';
    }

    public function handle(): void
    {
        $exitCode = Artisan::call($this->command);
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            $this->notify('Backup failed', Str::limit($output, 300), false);

            return;
        }

        $this->command === 'backup:clean'
            ? $this->notify('Cleanup complete', 'Old backups have been cleaned up.', true)
            : $this->notify('Backup created', 'The backup finished.', true);
    }

    public function failed(Throwable $e): void
    {
        $this->notify('Backup failed', Str::limit($e->getMessage(), 300), false);
    }

    private function notify(string $title, string $body, bool $success): void
    {
        $user = $this->userId ? User::find($this->userId) : null;
        if (! $user) {
            return;
        }

        $notification = Notification::make()->title($title)->body($body);
        ($success ? $notification->success() : $notification->danger())->sendToDatabase($user);
    }
}
