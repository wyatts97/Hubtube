<?php

namespace App\Filament\Widgets;

use Illuminate\Support\Str;
use Throwable;
use App\Models\Comment;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Models\VisitorDaily;
use App\Models\WalletTransaction;
use App\Services\StorageManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 0;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        try {
            $now = Carbon::now();
            $sevenDaysAgo = $now->copy()->subDays(6)->startOfDay();

            // ── Users (batched 7-day chart query) ──
            $totalUsers = User::count();
            $users24h = User::where('created_at', '>=', $now->copy()->subDay())->count();
            $users7d = User::where('created_at', '>=', $now->copy()->subDays(7))->count();
            $users30d = User::where('created_at', '>=', $now->copy()->subDays(30))->count();

            // Single query for 7-day sparkline data
            $userChartData = User::where('created_at', '>=', $sevenDaysAgo)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupByRaw('DATE(created_at)')
                ->pluck('count', 'date')
                ->toArray();

            $userChart = $this->fillChartData($userChartData, $now, 7);

            // ── Videos (batched 7-day chart query) ──
            $totalVideos = Video::count();
            $videos24h = Video::where('created_at', '>=', $now->copy()->subDay())->count();
            $videos7d = Video::where('created_at', '>=', $now->copy()->subDays(7))->count();
            $videos30d = Video::where('created_at', '>=', $now->copy()->subDays(30))->count();

            $videoChartData = Video::where('created_at', '>=', $sevenDaysAgo)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupByRaw('DATE(created_at)')
                ->pluck('count', 'date')
                ->toArray();

            $videoChart = $this->fillChartData($videoChartData, $now, 7);

            // ── Total Views ──
            $totalViews = Video::sum('views_count');
            // Approximate views growth: compare current total vs what it was 24h/7d/30d ago
            // We can't track historical view snapshots, so show top video's views instead
            $topVideo = Video::orderByDesc('views_count')->first();
            $topVideoLabel = $topVideo
                ? Str::limit($topVideo->title, 25) . ' (' . number_format($topVideo->views_count) . ')'
                : 'No videos yet';

            // ── Comments (7-day sparkline) ──
            $totalComments = Comment::count();
            $comments7d = Comment::where('created_at', '>=', $now->copy()->subDays(7))->count();
            $commentChartData = Comment::where('created_at', '>=', $sevenDaysAgo)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupByRaw('DATE(created_at)')
                ->pluck('count', 'date')
                ->toArray();
            $commentChart = $this->fillChartData($commentChartData, $now, 7);

            // ── Views (sparkline = recent videos' aggregate views per day via created_at proxy) ──
            // We can't show true daily view deltas (no snapshot table), so chart
            // shows total views added to the catalogue each day via new uploads' views_count.
            $viewChartData = Video::where('created_at', '>=', $sevenDaysAgo)
                ->selectRaw('DATE(created_at) as date, SUM(views_count) as total')
                ->groupByRaw('DATE(created_at)')
                ->pluck('total', 'date')
                ->toArray();
            $viewChart = $this->fillChartData($viewChartData, $now, 7);

            // ── Storage growth sparkline (new bytes uploaded per day) ──
            $storageChartData = Video::where('created_at', '>=', $sevenDaysAgo)
                ->selectRaw('DATE(created_at) as date, SUM(size) as total')
                ->groupByRaw('DATE(created_at)')
                ->pluck('total', 'date')
                ->toArray();
            // Normalize to MB so chart values stay reasonable
            $storageChart = array_map(
                fn ($v) => (int) round(((int) $v) / 1048576),
                $this->fillChartData($storageChartData, $now, 7)
            );

            // ── Revenue (only if monetization is enabled) ──
            $monetizationEnabled = (bool) Setting::get('monetization_enabled', false);
            $totalRevenue = 0;
            $revenue7d = 0;
            $revenue30d = 0;
            if ($monetizationEnabled) {
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
            }

            // ── Storage ──
            $totalSize = Video::sum('size');
            $isCloud = StorageManager::isCloudDisk();
            if ($isCloud) {
                $storageLabel = $this->formatBytes($totalSize);
                $storageDescription = number_format($totalVideos) . ' files · Cloud disk';
            } else {
                $storagePath = \Illuminate\Support\Facades\Storage::disk('public')->path('');
                $diskTotal = @disk_total_space($storagePath);
                $diskFree = @disk_free_space($storagePath);
                $diskUsed = $diskTotal ? $diskTotal - $diskFree : 0;
                if ($diskTotal && $diskFree !== false) {
                    $storageLabel = $this->formatBytes($diskUsed) . ' / ' . $this->formatBytes($diskTotal);
                    $percent = round(($diskUsed / $diskTotal) * 100, 1);
                    $storageDescription = $percent . '% used';
                } else {
                    $storageLabel = $this->formatBytes($totalSize);
                    $storageDescription = number_format($totalVideos) . ' files on disk';
                }
            }

            // ── Visitors (today only) ──
            $visitorCount = 0;
            try {
                if (Schema::hasTable('visitor_daily')) {
                    $today = now()->toDateString();
                    $visitorCount = VisitorDaily::where('date', $today)
                        ->distinct()
                        ->count('visitor_hash');
                }
            } catch (\Throwable) {
                // Fallback to zero
            }

            $stats = [
                // Row 1
                Stat::make('Total Users', number_format($totalUsers))
                    ->description("+{$users24h} today · +{$users7d} this week · +{$users30d} this month")
                    ->descriptionIcon('phosphor-users')
                    ->chart($userChart)
                    ->chartColor('primary')
                    ->color('primary')
                    ->url(route('filament.admin.resources.users.index'))
                    ->extraAttributes(['class' => 'cursor-pointer']),

                Stat::make('Total Videos', number_format($totalVideos))
                    ->description("+{$videos24h} today · +{$videos7d} this week · +{$videos30d} this month")
                    ->descriptionIcon('phosphor-video-camera')
                    ->chart($videoChart)
                    ->chartColor('success')
                    ->color('success')
                    ->url(route('filament.admin.resources.videos.index'))
                    ->extraAttributes(['class' => 'cursor-pointer']),

                Stat::make('Total Views', number_format($totalViews))
                    ->description("Top: {$topVideoLabel}")
                    ->descriptionIcon('phosphor-eye')
                    ->chart($viewChart)
                    ->chartColor('info')
                    ->color('info')
                    ->url(route('filament.admin.pages.analytics'))
                    ->extraAttributes(['class' => 'cursor-pointer']),

                // Row 2
                Stat::make('Comments', number_format($totalComments))
                    ->description("+{$comments7d} this week")
                    ->descriptionIcon('phosphor-chat-text')
                    ->chart($commentChart)
                    ->chartColor('gray')
                    ->color('gray')
                    ->url(route('filament.admin.resources.comments.index'))
                    ->extraAttributes(['class' => 'cursor-pointer']),

                Stat::make('Storage', $storageLabel)
                    ->description($storageDescription)
                    ->descriptionIcon('phosphor-hard-drives')
                    ->chart($storageChart)
                    ->chartColor('gray')
                    ->color('gray'),

                Stat::make('Visitors', number_format($visitorCount))
                    ->description('Today')
                    ->descriptionIcon('phosphor-users')
                    ->color('primary'),
            ];

            // Add Revenue stat only if monetization is enabled
            if ($monetizationEnabled) {
                // Insert after Total Views (index 2) to keep it in row 1
                array_splice($stats, 3, 0, [
                    Stat::make('Revenue', '$' . number_format($totalRevenue, 2))
                        ->description('$' . number_format($revenue7d, 2) . ' this week · $' . number_format($revenue30d, 2) . ' this month')
                        ->descriptionIcon('phosphor-currency-dollar')
                        ->color('warning'),
                ]);
            }

            return $stats;
        } catch (Throwable $e) {
            return [];
        }
    }

    private function formatBytes(int|float $bytes): string
    {
        if ($bytes >= 1099511627776) {
            return number_format($bytes / 1099511627776, 2) . ' TB';
        } elseif ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Fill chart data array with zeros for missing days.
     */
    private function fillChartData(array $data, Carbon $now, int $days): array
    {
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i)->toDateString();
            $result[] = $data[$date] ?? 0;
        }
        return $result;
    }
}
