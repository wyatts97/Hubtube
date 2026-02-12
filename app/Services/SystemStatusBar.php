<?php

namespace App\Services;

use App\Models\Setting;
use App\Services\FfmpegService;
use App\Models\Video;
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
            $failedCount = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
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
