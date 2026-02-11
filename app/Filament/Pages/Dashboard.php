<?php

namespace App\Filament\Pages;

use App\Models\Comment;
use App\Models\LiveStream;
use App\Models\Playlist;
use App\Models\User;
use App\Models\Video;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.pages.dashboard';

    public function getColumns(): int|string|array
    {
        return 1;
    }

    public function getWidgets(): array
    {
        return [];
    }

    public function getViewData(): array
    {
        $now = Carbon::now();

        // ── Users ──
        $totalUsers = User::count();
        $users24h = User::where('created_at', '>=', $now->copy()->subDay())->count();
        $users7d = User::where('created_at', '>=', $now->copy()->subDays(7))->count();
        $users30d = User::where('created_at', '>=', $now->copy()->subDays(30))->count();

        // 7-day user trend
        $userChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $userChart[] = User::whereDate('created_at', $now->copy()->subDays($i)->toDateString())->count();
        }

        // ── Videos ──
        $totalVideos = Video::count();
        $videos24h = Video::where('created_at', '>=', $now->copy()->subDay())->count();
        $videos7d = Video::where('created_at', '>=', $now->copy()->subDays(7))->count();

        $videoChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $videoChart[] = Video::whereDate('created_at', $now->copy()->subDays($i)->toDateString())->count();
        }

        // ── Views ──
        $totalViews = Video::sum('views_count');

        // ── Comments ──
        $totalComments = Comment::count();
        $comments7d = Comment::where('created_at', '>=', $now->copy()->subDays(7))->count();

        // ── Live ──
        $liveNow = LiveStream::where('status', 'live')->count();

        // ── Revenue ──
        $totalRevenue = WalletTransaction::where('type', 'deposit')->where('status', 'completed')->sum('amount');
        $revenue7d = WalletTransaction::where('type', 'deposit')->where('status', 'completed')
            ->where('created_at', '>=', $now->copy()->subDays(7))->sum('amount');
        $revenue30d = WalletTransaction::where('type', 'deposit')->where('status', 'completed')
            ->where('created_at', '>=', $now->copy()->subDays(30))->sum('amount');

        // ── Storage ──
        $totalSize = Video::sum('size');

        // ── Processing ──
        $processingCount = Video::where('status', 'processing')->count();
        $pendingCount = Video::where('status', 'pending')->count();
        $failedCount = Video::where('status', 'failed')->count();

        // ── Playlists ──
        $totalPlaylists = Playlist::count();
        $publicPlaylists = Playlist::where('privacy', 'public')->count();

        // ── Trending Videos (top 10 by views in last 30 days or overall) ──
        $trendingVideos = Video::with('user')
            ->public()
            ->approved()
            ->processed()
            ->orderByDesc('views_count')
            ->limit(10)
            ->get();

        // ── Recent Videos ──
        $recentVideos = Video::with('user')
            ->latest()
            ->limit(10)
            ->get();

        // ── Recent Users ──
        $recentUsers = User::latest()->limit(5)->get();

        return [
            'totalUsers' => $totalUsers,
            'users24h' => $users24h,
            'users7d' => $users7d,
            'users30d' => $users30d,
            'userChart' => $userChart,
            'totalVideos' => $totalVideos,
            'videos24h' => $videos24h,
            'videos7d' => $videos7d,
            'videoChart' => $videoChart,
            'totalViews' => $totalViews,
            'totalComments' => $totalComments,
            'comments7d' => $comments7d,
            'liveNow' => $liveNow,
            'totalRevenue' => $totalRevenue,
            'revenue7d' => $revenue7d,
            'revenue30d' => $revenue30d,
            'totalSize' => $totalSize,
            'processingCount' => $processingCount,
            'pendingCount' => $pendingCount,
            'failedCount' => $failedCount,
            'totalPlaylists' => $totalPlaylists,
            'publicPlaylists' => $publicPlaylists,
            'trendingVideos' => $trendingVideos,
            'recentVideos' => $recentVideos,
            'recentUsers' => $recentUsers,
        ];
    }

    public static function formatBytes(int|float $bytes): string
    {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }
}
