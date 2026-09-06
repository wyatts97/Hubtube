<?php

namespace App\Services;

use Croustibat\FilamentJobsMonitor\Models\FailureGroup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Exception;
use Throwable;
use App\Models\Comment;
use App\Models\ContactMessage;
use App\Models\Image;
use App\Models\Report;
use App\Models\Setting;
use App\Models\WithdrawalRequest;
use App\Services\FfmpegService;
use App\Models\Video;
use App\Filament\Resources\VideoResource;
use App\Filament\Resources\ImageResource;
use App\Filament\Resources\CommentResource;
use App\Filament\Resources\ContactMessageResource;
use App\Filament\Resources\ReportResource;
use App\Filament\Resources\WithdrawalRequestResource;
use App\Filament\Pages\ScheduledVideos;
use Illuminate\Support\Facades\Storage;

class SystemStatusBar
{
    /**
     * Per-request memo. Two render hooks (the desktop strip and the phone
     * chip) both ask for the action items in a single response, and without
     * this the whole count set would be resolved twice per page load.
     */
    protected ?array $actionItems = null;

    protected const COUNTS_CACHE_KEY = 'ht:status-bar:counts';

    /**
     * Short by design. Paired with flush() below, staleness is bounded by
     * whichever comes first: a moderator's own edit, or fifteen seconds.
     */
    protected const COUNTS_CACHE_TTL = 15;

    /**
     * Drop the cached counts.
     *
     * Wired to saved/deleted on the underlying models in AppServiceProvider so
     * that approving the last pending video makes its pill read zero on the
     * next navigation, rather than up to a TTL later.
     */
    public static function flush(): void
    {
        try {
            Cache::forget(self::COUNTS_CACHE_KEY);
        } catch (Throwable $e) {
            // A dead cache store must never break a model save.
        }
    }

    public function getMetrics(): array
    {
        return [
            'storage' => $this->getStorageMetrics(),
            'ffmpeg' => $this->getFfmpegStatus(),
            'queue' => $this->getQueueStatus(),
        ];
    }

    /**
     * Action-item counts surfaced as topbar pills (replaces the old
     * coloured sidebar navigation badges). Every item is always included,
     * even at zero, so the strip reads as a stable status bar — the view
     * mutes a zero rather than hiding it, so colour only ever means
     * "this needs you". Items whose count query fails are dropped.
     *
     * @return array<int, array{key:string,label:string,shortLabel:string,count:int,url:?string,icon:string,tone:string}>
     */
    public function getActionItems(): array
    {
        if ($this->actionItems !== null) {
            return $this->actionItems;
        }

        $definitions = $this->actionItemDefinitions();
        $counts = $this->resolveCounts($definitions);

        $items = [];

        foreach ($definitions as $definition) {
            // A key absent from the map means its count query threw, so the
            // item is dropped rather than rendered as a misleading zero.
            if (! array_key_exists($definition['key'], $counts)) {
                continue;
            }

            $items[] = $this->buildItem($definition, $counts[$definition['key']]);
        }

        return $this->actionItems = $items;
    }

    /**
     * The pill definitions, with their count queries still unresolved.
     *
     * Kept separate from the counts so the list itself is rebuilt per request:
     * the monetization gate below stays live, and resolved URLs (which bake in
     * the current host and panel path) are never cached.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function actionItemDefinitions(): array
    {
        $definitions = [];

        $definitions[] = [
            'key' => 'moderation',
            'label' => 'Videos need moderation',
            'shortLabel' => 'Needs Moderation',
            'icon' => 'phosphor-shield-check',
            'tone' => 'warning',
            'count' => fn () => Video::where('is_approved', false)
                ->where('status', 'processed')
                ->whereNull('queue_order')
                ->count(),
            'url' => fn () => VideoResource::getUrl('index'),
        ];

        $definitions[] = [
            'key' => 'images',
            'label' => 'Images need moderation',
            'shortLabel' => 'Images',
            'icon' => 'phosphor-image',
            'tone' => 'warning',
            'count' => fn () => Image::where('is_approved', false)->count(),
            'url' => fn () => ImageResource::getUrl('index'),
        ];

        $definitions[] = [
            'key' => 'comments',
            'label' => 'Comments awaiting approval',
            'shortLabel' => 'Comments',
            'icon' => 'phosphor-chat-text',
            'tone' => 'warning',
            'count' => fn () => Comment::where('is_approved', false)->count(),
            'url' => fn () => CommentResource::getUrl('index'),
        ];

        $definitions[] = [
            'key' => 'reports',
            'label' => 'Pending reports',
            'shortLabel' => 'Reports',
            'icon' => 'phosphor-flag',
            'tone' => 'danger',
            'count' => fn () => Report::whereIn('status', [Report::STATUS_PENDING, Report::STATUS_REVIEWING])->count(),
            'url' => fn () => ReportResource::getUrl('index'),
        ];

        $definitions[] = [
            'key' => 'contacts',
            'label' => 'Unread contact messages',
            'shortLabel' => 'Messages',
            'icon' => 'phosphor-envelope',
            'tone' => 'danger',
            'count' => fn () => ContactMessage::where('is_read', false)->count(),
            'url' => fn () => ContactMessageResource::getUrl('index'),
        ];

        if ((bool) Setting::get('monetization_enabled', true)) {
            $definitions[] = [
                'key' => 'withdrawals',
                'label' => 'Pending withdrawals',
                'shortLabel' => 'Withdrawals',
                'icon' => 'phosphor-currency-dollar',
                'tone' => 'warning',
                'count' => fn () => WithdrawalRequest::where('status', WithdrawalRequest::STATUS_PENDING)->count(),
                'url' => fn () => WithdrawalRequestResource::getUrl('index'),
            ];
        }

        $definitions[] = [
            'key' => 'scheduled',
            'label' => 'Scheduled videos',
            'shortLabel' => 'Scheduled',
            'icon' => 'phosphor-clock',
            'tone' => 'info',
            'count' => fn () => Video::whereNotNull('queue_order')
                ->whereNull('published_at')
                ->count(),
            'url' => fn () => ScheduledVideos::getUrl(),
        ];

        // Counts unresolved failure *groups*, which is exactly what the page
        // this links to shows on its default tab. The previous version counted
        // `failed_jobs` while linking at the jobs-monitor index (which lists
        // `queue_monitors`) — two unrelated tables, so a single old failure
        // left a permanent badge pointing at an empty page, with nothing in
        // the UI able to clear it. FailureGroup is not prunable and the
        // failures page can resolve or retry a group, so the number and the
        // page can no longer disagree.
        $definitions[] = [
            'key' => 'failures',
            'label' => 'Unresolved job failures',
            'shortLabel' => 'Failures',
            'icon' => 'phosphor-bug',
            'tone' => 'danger',
            'count' => fn () => FailureGroup::whereNull('resolved_at')->count(),
            'url' => fn () => $this->jobFailuresUrl(),
        ];

        return $definitions;
    }

    /**
     * Resolve every count in one cached pass.
     *
     * Only the counts are cached, never the assembled items: a resolved URL
     * carries the current host and panel path, and caching that would serve
     * the wrong domain on a multi-domain install.
     *
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<string, int>  keyed by item key; a key is absent if its query threw
     */
    protected function resolveCounts(array $definitions): array
    {
        $resolve = function () use ($definitions): array {
            $counts = [];

            foreach ($definitions as $definition) {
                try {
                    $counts[$definition['key']] = (int) ($definition['count'])();
                } catch (Throwable $e) {
                    // Missing table, unmigrated plugin, etc. Leave the key out
                    // so one broken lookup drops its own pill and no more.
                }
            }

            return $counts;
        };

        try {
            return Cache::remember(self::COUNTS_CACHE_KEY, self::COUNTS_CACHE_TTL, $resolve);
        } catch (Throwable $e) {
            // Cache store down: still render, just uncached.
            return $resolve();
        }
    }

    /**
     * Assemble one pill. The URL is resolved here rather than in the cached
     * count pass, and a failure to resolve it degrades the pill to plain text
     * instead of dropping it.
     *
     * @param  array<string, mixed>  $definition
     * @return array{key:string,label:string,shortLabel:string,count:int,url:?string,icon:string,tone:string}
     */
    protected function buildItem(array $definition, int $count): array
    {
        try {
            $url = ($definition['url'])();
        } catch (Throwable $e) {
            $url = null;
        }

        return [
            'key' => $definition['key'],
            'label' => $definition['label'],
            'shortLabel' => $definition['shortLabel'],
            'count' => $count,
            'url' => $url,
            'icon' => $definition['icon'],
            'tone' => $definition['tone'],
        ];
    }

    /**
     * Deep-link to the jobs-monitor "Failures" sub-page, not its index.
     *
     * They are not the same data. The index lists `queue_monitors` — every job
     * run, prunable — while the badge counts `queue_monitor_failure_groups`,
     * which is not. Pointing at the index is how a stale count ends up with
     * nothing on screen to explain it. The failures page is also the only
     * place an admin can retry or resolve a group, i.e. the only place the
     * badge can actually be cleared.
     */
    protected function jobFailuresUrl(): ?string
    {
        $resource = config('filament-jobs-monitor.resources.resource');

        if (! is_string($resource) || ! class_exists($resource) || ! method_exists($resource, 'getUrl')) {
            return null;
        }

        // QueueMonitorResource only registers the `failures` page when this is on.
        $page = config('filament-jobs-monitor.failures.enabled', true) ? 'failures' : 'index';

        return $resource::getUrl($page);
    }

    protected function getStorageMetrics(): array
    {
        $storagePath = Storage::disk('public')->path('');
        $totalBytes = @disk_total_space($storagePath);
        $freeBytes = @disk_free_space($storagePath);
        $usedBytes = $totalBytes ? $totalBytes - $freeBytes : 0;

        $activeDisk = StorageManager::getActiveDiskName();
        $driverLabels = [
            'public' => 'Local',
            'wasabi' => 'Wasabi',
            'b2' => 'Backblaze B2',
            's3' => 'Amazon S3',
        ];

        return [
            'driver' => $driverLabels[$activeDisk] ?? $activeDisk,
            'driver_key' => $activeDisk,
            'is_cloud' => StorageManager::isCloudDisk($activeDisk),
            'total' => $totalBytes ? $this->formatBytes($totalBytes) : 'N/A',
            'used' => $totalBytes ? $this->formatBytes($usedBytes) : 'N/A',
            'free' => $freeBytes ? $this->formatBytes($freeBytes) : 'N/A',
            'percent' => $totalBytes ? round(($usedBytes / $totalBytes) * 100, 1) : 0,
        ];
    }

    protected function getFfmpegStatus(): array
    {
        $enabled = Setting::get('ffmpeg_enabled', true);
        $ffmpeg = FfmpegService::ffmpegPath();
        $available = FfmpegService::isAvailable();

        $processingCount = Video::where('status', 'processing')->count();
        $pendingCount = Video::where('status', 'pending')->count();

        return [
            'enabled' => $enabled,
            'available' => $available,
            'processing' => $processingCount,
            'pending' => $pendingCount,
        ];
    }

    protected function getQueueStatus(): array
    {
        $failedCount = 0;
        try {
            $failedCount = DB::table('failed_jobs')->count();
        } catch (Exception $e) {
            // Table may not exist
        }

        return [
            'failed' => $failedCount,
        ];
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1099511627776) {
            return round($bytes / 1099511627776, 1) . ' TB';
        }
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        return round($bytes / 1024, 1) . ' KB';
    }
}
