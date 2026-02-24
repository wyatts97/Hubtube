<?php

namespace App\Filament\Widgets;

use App\Models\Comment;
use App\Models\User;
use App\Models\Video;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        try {
            $now = Carbon::now();

            // ── Users ──
            $totalUsers = User::count();
            $users24h = User::where('created_at', '>=', $now->copy()->subDay())->count();
            $users7d = User::where('created_at', '>=', $now->copy()->subDays(7))->count();
            $users30d = User::where('created_at', '>=', $now->copy()->subDays(30))->count();

            // 7-day user trend (daily counts for sparkline)
            $userChart = [];
            for ($i = 6; $i >= 0; $i--) {
                $day = $now->copy()->subDays($i);
                $userChart[] = User::whereDate('created_at', $day->toDateString())->count();
            }

            // ── Videos ──
            $totalVideos = Video::count();
            $videos24h = Video::where('created_at', '>=', $now->copy()->subDay())->count();
            $videos7d = Video::where('created_at', '>=', $now->copy()->subDays(7))->count();
            $videos30d = Video::where('created_at', '>=', $now->copy()->subDays(30))->count();

            $videoChart = [];
            for ($i = 6; $i >= 0; $i--) {
                $day = $now->copy()->subDays($i);
                $videoChart[] = Video::whereDate('created_at', $day->toDateString())->count();
            }

            // ── Total Views ──
            $totalViews = Video::sum('views_count');
            // Approximate views growth: compare current total vs what it was 24h/7d/30d ago
            // We can't track historical view snapshots, so show top video's views instead
            $topVideo = Video::orderByDesc('views_count')->first();
            $topVideoLabel = $topVideo
                ? \Illuminate\Support\Str::limit($topVideo->title, 25) . ' (' . number_format($topVideo->views_count) . ')'
                : 'No videos yet';

            // ── Comments ──
            $totalComments = Comment::count();
            $comments7d = Comment::where('created_at', '>=', $now->copy()->subDays(7))->count();

            // ── Live Streams ──
            $liveNow = LiveStream::where('status', 'live')->count();
            $streams7d = LiveStream::where('created_at', '>=', $now->copy()->subDays(7))->count();

            // ── Revenue ──
            $totalRevenue = WalletTransaction::where('type', 'deposit')
                ->where('status', 'completed')
                ->sum('amount');
            $revenue7d = WalletTransaction::where('type', 'deposit')
                ->where('status', 'completed')
                ->where('created_at', '>=', $now->copy()->subDays(7))
                ->sum('amount');
            $revenue30d = WalletTransaction::where('type', 'deposit')
                ->where('status', 'completed')
                ->where('created_at', '>=', $now->copy()->subDays(30))
                ->sum('amount');

            // ── Storage ──
            $totalSize = Video::sum('size');
            $storageLabel = $this->formatBytes($totalSize);

            // ── Processing ──
            $processingCount = Video::where('status', 'processing')->count();
            $pendingCount = Video::where('status', 'pending')->count();
            $failedCount = Video::where('status', 'failed')->count();

            return [
                // Row 1
                Stat::make('Total Users', number_format($totalUsers))
                    ->description("+{$users24h} today · +{$users7d} this week · +{$users30d} this month")
                    ->descriptionIcon('heroicon-m-users')
                    ->chart($userChart)
                    ->chartColor('primary')
                    ->color('primary'),

                Stat::make('Total Videos', number_format($totalVideos))
                    ->description("+{$videos24h} today · +{$videos7d} this week · +{$videos30d} this month")
                    ->descriptionIcon('heroicon-m-video-camera')
                    ->chart($videoChart)
                    ->chartColor('success')
                    ->color('success'),

                Stat::make('Total Views', number_format($totalViews))
                    ->description("Top: {$topVideoLabel}")
                    ->descriptionIcon('heroicon-m-eye')
                    ->color('info'),

                Stat::make('Revenue', '$' . number_format($totalRevenue, 2))
                    ->description('$' . number_format($revenue7d, 2) . ' this week · $' . number_format($revenue30d, 2) . ' this month')
                    ->descriptionIcon('heroicon-m-banknotes')
                    ->color('warning'),

                // Row 2
                Stat::make('Comments', number_format($totalComments))
                    ->description("+{$comments7d} this week")
                    ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                    ->color('gray'),

                Stat::make('Video Storage', $storageLabel)
                    ->description(number_format($totalVideos) . ' files on disk')
                    ->descriptionIcon('heroicon-m-server-stack')
                    ->color('gray'),

                Stat::make('Processing', $processingCount > 0 ? "{$processingCount} encoding" : 'Idle')
                    ->description(
                        ($pendingCount > 0 ? "{$pendingCount} queued" : 'No queue') .
                        ($failedCount > 0 ? " · {$failedCount} failed" : '')
                    )
                    ->descriptionIcon('heroicon-m-cog-6-tooth')
                    ->color($failedCount > 0 ? 'danger' : ($processingCount > 0 ? 'info' : 'gray')),
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function formatBytes(int|float $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
