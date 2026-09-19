<?php

namespace App\Jobs;

use App\Models\MediaFile;
use App\Models\User;
use App\Services\Encoding\FfmpegRunner;
use App\Services\Media\MediaCompressService;
use App\Services\Media\MediaIndexService;
use App\Support\Bytes;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Compress one library video to H.265, VP9 or AV1.
 *
 * Always writes a NEW file beside the source (see
 * MediaCompressService::targetPathFor()); the source is never modified. The
 * result is verified before it is kept, and whoever asked is notified either
 * way — the explorer's progress badge is a convenience, not the record.
 */
class CompressMediaFileJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Never auto-retried: a failed encode is looked at, not repeated. */
    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(
        public string $sourcePath,
        public string $codec = 'h265',
        public string $quality = 'balanced',
        public ?int $requestedBy = null,
    ) {
        // One niced worker (config/horizon.php), so a bulk compress can never
        // starve live upload encoding.
        $this->onQueue('media-compress');
    }

    public function uniqueId(): string
    {
        return md5($this->sourcePath.'|'.$this->codec);
    }

    public function handle(MediaCompressService $compress, FfmpegRunner $runner, MediaIndexService $index): void
    {
        $codec = in_array($this->codec, MediaCompressService::CODECS, true) ? $this->codec : 'h265';
        $quality = in_array($this->quality, MediaCompressService::QUALITIES, true) ? $this->quality : 'balanced';
        $disk = Storage::disk('public');

        if (! $runner->isAvailable() || ! ($compress->available()[$codec] ?? false)) {
            $this->markFailed("This server's ffmpeg cannot encode {$codec}.", $compress);

            return;
        }

        $before = $compress->readableSize($this->sourcePath);

        if ($before === null) {
            $this->markFailed('The file is no longer on disk.', $compress);

            return;
        }

        $target = $compress->targetPathFor($this->sourcePath, $codec);
        $duration = (int) MediaFile::query()->where('path_hash', md5($this->sourcePath))->value('duration_seconds');

        $status = ['state' => 'running', 'codec' => $codec, 'percent' => 0];
        $compress->setStatus($this->sourcePath, $status);

        $lastPercent = 0;
        $onProgress = $duration <= 0 ? null : function (float $seconds) use ($compress, $duration, &$status, &$lastPercent): void {
            $percent = min(99, (int) ($seconds / $duration * 100));

            // Only on a whole-percent change: this fires many times a second.
            if ($percent > $lastPercent) {
                $lastPercent = $percent;
                $compress->setStatus($this->sourcePath, ['percent' => $percent] + $status);
            }
        };

        try {
            [$exit, $output] = $runner->run(
                $compress->buildCommand($disk->path($this->sourcePath), $disk->path($target), $codec, $quality),
                $this->timeout - 60,
                $onProgress,
            );
        } catch (Throwable $e) {
            $this->discard($target);
            $this->markFailed($e->getMessage(), $compress);

            return;
        }

        if ($exit !== 0) {
            Log::warning('Media compression failed', ['path' => $this->sourcePath, 'output' => mb_substr($output, -2000)]);
            $this->discard($target);
            $this->markFailed('ffmpeg exited with code '.$exit.'.', $compress);

            return;
        }

        if ($reason = $compress->verify($this->sourcePath, $target)) {
            $this->discard($target);
            $this->markFailed($reason, $compress);

            return;
        }

        $after = (int) $compress->readableSize($target);
        $index->indexPath($target);

        $compress->setStatus($this->sourcePath, [
            'state' => 'done',
            'codec' => $codec,
            'target' => $target,
            'before' => $before,
            'after' => $after,
        ]);

        $this->notify(true, basename($this->sourcePath).' compressed', sprintf(
            'Saved %s as %s. The original is untouched — delete whichever you do not want%s.',
            Bytes::saving($before, $after),
            basename($target),
            $compress->videoOriginalFor($this->sourcePath) ? ', or use "Replace original" on the upload' : '',
        ));
    }

    /** Called by the queue when the job dies outright (timeout, worker crash). */
    public function failed(?Throwable $exception): void
    {
        $this->markFailed($exception?->getMessage() ?? 'The job failed.', app(MediaCompressService::class));
    }

    protected function markFailed(string $reason, MediaCompressService $compress): void
    {
        $compress->setStatus($this->sourcePath, ['state' => 'failed', 'codec' => $this->codec, 'error' => $reason]);
        $this->notify(false, 'Could not compress '.basename($this->sourcePath), $reason);
    }

    protected function discard(string $path): void
    {
        try {
            Storage::disk('public')->delete($path);
        } catch (Throwable) {
            // Nothing to clean up.
        }
    }

    protected function notify(bool $success, string $title, string $body): void
    {
        $user = $this->requestedBy ? User::find($this->requestedBy) : null;

        if (! $user) {
            return;
        }

        $notification = Notification::make()->title($title)->body($body);
        $success ? $notification->success() : $notification->danger();
        $notification->sendToDatabase($user);
    }
}
