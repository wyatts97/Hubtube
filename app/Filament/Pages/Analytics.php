<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Models\Video;
use App\Models\VideoAd;
use App\Models\WalletTransaction;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class Analytics extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Analytics';
    protected static ?string $navigationGroup = 'Overview';
    protected static ?int    $navigationSort  = 2;
    protected static string  $view            = 'filament.pages.analytics';

    public array $uploadsPerDay    = [];
    public array $signupsPerDay    = [];
    public array $viewsByCategory  = [];
    public array $revenuePerDay    = [];
    public array $adStats          = [];

    public function mount(): void
    {
        $this->uploadsPerDay   = $this->getUploadsPerDay();
        $this->signupsPerDay   = $this->getSignupsPerDay();
        $this->viewsByCategory = $this->getViewsByCategory();
        $this->revenuePerDay   = $this->getRevenuePerDay();
        $this->adStats         = $this->getAdStats();
    }

    protected function getUploadsPerDay(): array
    {
        return DB::table('videos')
            ->select(DB::raw('DATE(created_at) as date, COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => $row->date, 'count' => (int) $row->count])
            ->toArray();
    }

    protected function getSignupsPerDay(): array
    {
        return DB::table('users')
            ->select(DB::raw('DATE(created_at) as date, COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => $row->date, 'count' => (int) $row->count])
            ->toArray();
    }

    protected function getViewsByCategory(): array
    {
        return DB::table('videos')
            ->join('categories', 'videos.category_id', '=', 'categories.id')
            ->select('categories.name as category', DB::raw('SUM(videos.views_count) as total_views'))
            ->whereNotNull('videos.category_id')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_views')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['category' => $row->category, 'views' => (int) $row->total_views])
            ->toArray();
    }

    protected function getRevenuePerDay(): array
    {
        if (!DB::getSchemaBuilder()->hasTable('wallet_transactions')) {
            return [];
        }

        return DB::table('wallet_transactions')
            ->select(DB::raw('DATE(created_at) as date, SUM(amount) as total'))
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->where('type', 'deposit')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => $row->date, 'total' => (float) $row->total])
            ->toArray();
    }

    protected function getAdStats(): array
    {
        return VideoAd::active()
            ->select('id', 'name', 'placement', 'type', 'impressions_count', 'clicks_count')
            ->orderByDesc('impressions_count')
            ->limit(20)
            ->get()
            ->map(function ($ad) {
                $ctr = $ad->impressions_count > 0
                    ? round(($ad->clicks_count / $ad->impressions_count) * 100, 2)
                    : 0;
                return [
                    'id'          => $ad->id,
                    'name'        => $ad->name,
                    'placement'   => $ad->placement,
                    'type'        => $ad->type,
                    'impressions' => $ad->impressions_count,
                    'clicks'      => $ad->clicks_count,
                    'ctr'         => $ctr,
                ];
            })
            ->toArray();
    }

    public function getSummaryStats(): array
    {
        return [
            'total_videos'     => Video::count(),
            'total_users'      => User::count(),
            'total_views'      => Video::sum('views_count'),
            'videos_this_week' => Video::where('created_at', '>=', now()->subWeek())->count(),
            'users_this_week'  => User::where('created_at', '>=', now()->subWeek())->count(),
            'total_impressions' => VideoAd::sum('impressions_count'),
            'total_clicks'     => VideoAd::sum('clicks_count'),
        ];
    }
}
