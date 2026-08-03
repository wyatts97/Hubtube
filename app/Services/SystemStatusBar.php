<?php

namespace App\Services;

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
     * even at zero, so the strip reads as a stable status bar. Each lookup
     * is individually guarded so a single failing query never takes down
     * the whole topbar.
     *
     * @return array<int, array{key:string,label:string,shortLabel:string,count:int,url:?string,icon:string,tone:string}>
     */
    public function getActionItems(): array
    {
        $items = [];

        $items[] = $this->buildItem(
            key: 'moderation',
            label: 'Videos need moderation',
            shortLabel: 'Needs Moderation',
            icon: 'phosphor-shield-check',
            tone: 'warning',
            count: fn () => Video::where('is_approved', false)
                ->where('status', 'processed')
                ->whereNull('queue_order')
                ->count(),
            url: fn () => VideoResource::getUrl('index'),
        );

        $items[] = $this->buildItem(
            key: 'images',
            label: 'Images need moderation',
            shortLabel: 'Images',
            icon: 'phosphor-image',
            tone: 'warning',
            count: fn () => Image::where('is_approved', false)->count(),
            url: fn () => ImageResource::getUrl('index'),
        );

        $items[] = $this->buildItem(
            key: 'comments',
            label: 'Comments awaiting approval',
            shortLabel: 'Comments',
            icon: 'phosphor-chat-text',
            tone: 'warning',
            count: fn () => Comment::where('is_approved', false)->count(),
            url: fn () => CommentResource::getUrl('index'),
        );

        $items[] = $this->buildItem(
            key: 'reports',
            label: 'Pending reports',
            shortLabel: 'Reports',
            icon: 'phosphor-flag',
            tone: 'danger',
            count: fn () => Report::whereIn('status', [Report::STATUS_PENDING, Report::STATUS_REVIEWING])->count(),
            url: fn () => ReportResource::getUrl('index'),
        );

        $items[] = $this->buildItem(
            key: 'contacts',
            label: 'Unread contact messages',
            shortLabel: 'Messages',
            icon: 'phosphor-envelope',
            tone: 'danger',
            count: fn () => ContactMessage::where('is_read', false)->count(),
            url: fn () => ContactMessageResource::getUrl('index'),
        );

        if ((bool) Setting::get('monetization_enabled', true)) {
            $items[] = $this->buildItem(
                key: 'withdrawals',
                label: 'Pending withdrawals',
                shortLabel: 'Withdrawals',
                icon: 'phosphor-currency-dollar',
                tone: 'warning',
                count: fn () => WithdrawalRequest::where('status', WithdrawalRequest::STATUS_PENDING)->count(),
                url: fn () => WithdrawalRequestResource::getUrl('index'),
            );
        }

        $items[] = $this->buildItem(
            key: 'scheduled',
            label: 'Scheduled videos',
            shortLabel: 'Scheduled',
            icon: 'phosphor-clock',
            tone: 'info',
            count: fn () => Video::whereNotNull('queue_order')
                ->whereNull('published_at')
                ->count(),
            url: fn () => ScheduledVideos::getUrl(),
        );

        $items[] = $this->buildItem(
            key: 'logs',
            label: 'Failed jobs',
            shortLabel: 'Failed Jobs',
            icon: 'phosphor-warning-octagon',
            tone: 'danger',
            count: fn () => $this->getQueueStatus()['failed'] ?? 0,
            url: fn () => $this->logsUrl(),
        );

        // Only drop items whose count query actually failed.
        return array_values(array_filter($items, fn ($item) => $item !== null));
    }

    /**
     * Resolve a single action item, swallowing any error (missing table,
     * unregistered resource, etc.) so the topbar degrades gracefully.
     * A zero count is a valid result and is still rendered.
     */
    protected function buildItem(string $key, string $label, string $shortLabel, string $icon, string $tone, \Closure $count, \Closure $url): ?array
    {
        try {
            $value = (int) $count();
        } catch (Throwable $e) {
            return null;
        }

        try {
            $resolvedUrl = $url();
        } catch (Throwable $e) {
            $resolvedUrl = null;
        }

        return [
            'key' => $key,
            'label' => $label,
            'shortLabel' => $shortLabel,
            'count' => $value,
            'url' => $resolvedUrl,
            'icon' => $icon,
            'tone' => $tone,
        ];
    }

    protected function logsUrl(): ?string
    {
        $resource = config('filament-jobs-monitor.resources.resource');

        if (is_string($resource) && class_exists($resource) && method_exists($resource, 'getUrl')) {
            return $resource::getUrl('index');
        }

        return null;
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
