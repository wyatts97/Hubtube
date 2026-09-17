<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Media\MediaIndexService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rebuild the whole Media Library index in the background.
 *
 * What the page's "Rescan library" button dispatches. A full pass over a large
 * library takes far too long for a request, so the admin who asked gets a
 * database notification when it finishes instead of watching a spinner.
 *
 * Unique with no id, so a second press while one is running is discarded
 * rather than starting a competing scan.
 */
class ReindexMediaLibraryJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** A full library scan is allowed to take a while. */
    public int $timeout = 3600;

    public int $uniqueFor = 3600;

    public function __construct(
        public ?int $notifyUserId = null,
        public bool $prune = false,
    ) {
        $this->onQueue('media-thumbnails');
    }

    public function uniqueId(): string
    {
        return 'media-library';
    }

    public function handle(MediaIndexService $index): void
    {
        $result = $index->indexAll(prune: $this->prune, full: true);

        $this->notify(
            'Media Library rescanned',
            sprintf(
                '%d added, %d updated, %d removed across %d folders.',
                $result['added'],
                $result['updated'],
                $result['removed'],
                $result['directories'],
            ),
            success: true,
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Media Library reindex failed', ['error' => $exception?->getMessage()]);

        $this->notify(
            'Media Library rescan failed',
            'The index may be incomplete. Try `php artisan media:index --full` from the console.',
            success: false,
        );
    }

    protected function notify(string $title, string $body, bool $success): void
    {
        if ($this->notifyUserId === null) {
            return;
        }

        $user = User::find($this->notifyUserId);

        if (! $user) {
            return;
        }

        $notification = Notification::make()->title($title)->body($body);

        $success ? $notification->success() : $notification->danger();

        $notification->sendToDatabase($user);
    }
}
