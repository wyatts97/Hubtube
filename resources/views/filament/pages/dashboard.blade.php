<x-filament-panels::page>
    <style>
        .ht-stat-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 0.75rem;
            padding: 1rem;
            transition: all 0.15s;
        }
        .ht-stat-card:hover {
            border-color: rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.06);
        }
        .ht-list-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 0.75rem;
            overflow: hidden;
        }
        .ht-list-header {
            padding: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .ht-list-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            transition: opacity 0.15s;
            text-decoration: none;
        }
        .ht-list-row:last-child { border-bottom: none; }
        .ht-list-row:hover { opacity: 0.85; }
        .ht-label { font-size: 0.75rem; color: rgba(255,255,255,0.45); }
        .ht-value { font-size: 1.125rem; font-weight: 700; color: #fff; }
        .ht-value-sm { font-size: 1rem; font-weight: 700; color: #fff; }
        .ht-sub { font-size: 0.75rem; color: rgba(255,255,255,0.35); margin-top: 0.5rem; }
        .ht-title { font-size: 0.875rem; font-weight: 500; color: rgba(255,255,255,0.9); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ht-meta { font-size: 0.75rem; color: rgba(255,255,255,0.4); display: flex; align-items: center; gap: 0.25rem; }
        .ht-icon-box { width: 2.5rem; height: 2.5rem; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .ht-icon-box-sm { width: 2.25rem; height: 2.25rem; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .ht-thumb { width: 5rem; height: 3rem; border-radius: 0.5rem; overflow: hidden; flex-shrink: 0; background: rgba(255,255,255,0.05); }
        .ht-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .ht-rank { font-size: 1.125rem; font-weight: 700; width: 1.5rem; text-align: center; color: rgba(255,255,255,0.25); }
        .ht-badge { font-size: 0.75rem; padding: 0.125rem 0.375rem; border-radius: 0.25rem; }
        .ht-section-title { font-weight: 600; color: #fff; display: flex; align-items: center; gap: 0.5rem; }
        .ht-link { font-size: 0.875rem; color: rgb(var(--primary-400)); }
        .ht-link:hover { color: rgb(var(--primary-300)); }
        .ht-avatar { width: 2rem; height: 2rem; border-radius: 9999px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; }
    </style>

    <div class="space-y-6">

        {{-- Primary Stats Grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 sm:gap-3">
            {{-- Total Users --}}
            <a href="{{ route('filament.admin.resources.users.index') }}" class="ht-stat-card block">
                <div class="flex items-center gap-3">
                    <div class="ht-icon-box" style="background: rgba(59,130,246,0.15);">
                        <x-heroicon-m-users class="w-5 h-5" style="color: #3b82f6;" />
                    </div>
                    <div class="min-w-0">
                        <p class="ht-label truncate">Total Users</p>
                        <p class="ht-value">{{ number_format($totalUsers) }}</p>
                    </div>
                </div>
                <p class="ht-sub">+{{ $users24h }} today &middot; +{{ $users7d }} this week</p>
            </a>

            {{-- Total Videos --}}
            <a href="{{ route('filament.admin.resources.videos.index') }}" class="ht-stat-card block">
                <div class="flex items-center gap-3">
                    <div class="ht-icon-box" style="background: rgba(34,197,94,0.15);">
                        <x-heroicon-m-video-camera class="w-5 h-5" style="color: #22c55e;" />
                    </div>
                    <div class="min-w-0">
                        <p class="ht-label truncate">Total Videos</p>
                        <p class="ht-value">{{ number_format($totalVideos) }}</p>
                    </div>
                </div>
                <p class="ht-sub">+{{ $videos24h }} today &middot; +{{ $videos7d }} this week</p>
            </a>

            {{-- Total Views --}}
            <div class="ht-stat-card">
                <div class="flex items-center gap-3">
                    <div class="ht-icon-box" style="background: rgba(139,92,246,0.15);">
                        <x-heroicon-m-eye class="w-5 h-5" style="color: #8b5cf6;" />
                    </div>
                    <div class="min-w-0">
                        <p class="ht-label truncate">Total Views</p>
                        <p class="ht-value">{{ number_format($totalViews) }}</p>
                    </div>
                </div>
                <p class="ht-sub">{{ number_format($totalComments) }} comments &middot; +{{ $comments7d }} this week</p>
            </div>

            {{-- Revenue --}}
            <a href="{{ route('filament.admin.resources.wallet-transactions.index') }}" class="ht-stat-card block">
                <div class="flex items-center gap-3">
                    <div class="ht-icon-box" style="background: rgba(245,158,11,0.15);">
                        <x-heroicon-m-banknotes class="w-5 h-5" style="color: #f59e0b;" />
                    </div>
                    <div class="min-w-0">
                        <p class="ht-label truncate">Revenue</p>
                        <p class="ht-value">${{ number_format($totalRevenue, 2) }}</p>
                    </div>
                </div>
                <p class="ht-sub">${{ number_format($revenue7d, 2) }} this week &middot; ${{ number_format($revenue30d, 2) }} this month</p>
            </a>

            {{-- Playlists --}}
            <div class="ht-stat-card">
                <div class="flex items-center gap-3">
                    <div class="ht-icon-box" style="background: rgba(236,72,153,0.15);">
                        <x-heroicon-m-queue-list class="w-5 h-5" style="color: #ec4899;" />
                    </div>
                    <div class="min-w-0">
                        <p class="ht-label truncate">Playlists</p>
                        <p class="ht-value">{{ number_format($totalPlaylists) }}</p>
                    </div>
                </div>
                <p class="ht-sub">+{{ $playlists7d }} this week</p>
            </div>
        </div>

        {{-- Secondary Stats Row --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3">
            {{-- Live Now --}}
            <a href="{{ route('filament.admin.resources.live-streams.index') }}" class="ht-stat-card block">
                <div class="flex items-center gap-3">
                    <div class="ht-icon-box-sm" style="background: rgba(239,68,68,0.15);">
                        <x-heroicon-m-signal class="w-4 h-4" style="color: {{ $liveNow > 0 ? '#ef4444' : 'rgba(239,68,68,0.4)' }};" />
                    </div>
                    <div>
                        <p class="ht-label">Live Now</p>
                        <p class="ht-value-sm">{{ $liveNow }}</p>
                    </div>
                </div>
            </a>

            {{-- Storage --}}
            <div class="ht-stat-card">
                <div class="flex items-center gap-3">
                    <div class="ht-icon-box-sm" style="background: rgba(6,182,212,0.15);">
                        <x-heroicon-m-server-stack class="w-4 h-4" style="color: #06b6d4;" />
                    </div>
                    <div>
                        <p class="ht-label">Storage</p>
                        <p class="ht-value-sm">{{ \App\Filament\Pages\Dashboard::formatBytes($totalSize) }}</p>
                    </div>
                </div>
            </div>

            {{-- Processing --}}
            <div class="ht-stat-card">
                <div class="flex items-center gap-3">
                    <div class="ht-icon-box-sm" style="background: rgba(99,102,241,0.15);">
                        <x-heroicon-m-cog-6-tooth class="w-4 h-4 {{ $processingCount > 0 ? 'animate-spin' : '' }}" style="color: {{ $processingCount > 0 ? '#6366f1' : 'rgba(99,102,241,0.4)' }};" />
                    </div>
                    <div class="min-w-0">
                        <p class="ht-label">Processing</p>
                        <p class="ht-value-sm">
                            @if($processingCount > 0)
                                {{ $processingCount }} Encoding
                            @else
                                Idle
                            @endif
                        </p>
                        @if($pendingCount > 0 || $failedCount > 0)
                            <p class="ht-sub" style="margin-top: 0.125rem;">
                                @if($pendingCount > 0) {{ $pendingCount }} queued @endif
                                @if($failedCount > 0) <span style="color: #ef4444;">{{ $failedCount }} failed</span> @endif
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Comments --}}
            <a href="{{ route('filament.admin.resources.comments.index') }}" class="ht-stat-card block">
                <div class="flex items-center gap-3">
                    <div class="ht-icon-box-sm" style="background: rgba(234,179,8,0.15);">
                        <x-heroicon-m-chat-bubble-left-right class="w-4 h-4" style="color: #eab308;" />
                    </div>
                    <div>
                        <p class="ht-label">Comments</p>
                        <p class="ht-value-sm">{{ number_format($totalComments) }}</p>
                    </div>
                </div>
            </a>
        </div>

        {{-- Two Column Layout: Trending + Recent Uploads (side by side on desktop) --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Trending Videos --}}
            <div class="ht-list-card">
                <div class="ht-list-header">
                    <h2 class="ht-section-title">
                        <x-heroicon-m-fire class="w-4 h-4" style="color: #f97316;" />
                        Trending Videos
                    </h2>
                    <a href="{{ route('filament.admin.resources.videos.index') }}" class="ht-link">View All</a>
                </div>
                @if($trendingVideos->count())
                    @foreach($trendingVideos as $index => $video)
                        <a href="{{ route('filament.admin.resources.videos.edit', $video) }}" class="ht-list-row">
                            <span class="ht-rank">{{ $index + 1 }}</span>
                            <div class="ht-thumb">
                                @if($video->thumbnail_url)
                                    <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" />
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="ht-title">{{ $video->title }}</p>
                                <div class="flex items-center gap-3 mt-0.5">
                                    <span class="ht-meta">
                                        <x-heroicon-m-eye class="w-3 h-3" /> {{ number_format($video->views_count) }}
                                    </span>
                                    <span class="ht-meta">
                                        <x-heroicon-m-hand-thumb-up class="w-3 h-3" /> {{ number_format($video->likes_count) }}
                                    </span>
                                    @if($video->user)
                                        <span class="ht-meta">by {{ $video->user->username }}</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                @else
                    <div class="p-8 text-center">
                        <x-heroicon-m-video-camera class="w-10 h-10 mx-auto mb-2" style="color: rgba(255,255,255,0.15);" />
                        <p style="font-size: 0.875rem; color: rgba(255,255,255,0.4);">No videos yet</p>
                    </div>
                @endif
            </div>

            {{-- Recent Uploads --}}
            <div class="ht-list-card">
                <div class="ht-list-header">
                    <h2 class="ht-section-title">
                        <x-heroicon-m-clock class="w-4 h-4" style="color: #3b82f6;" />
                        Recent Uploads
                    </h2>
                    <a href="{{ route('filament.admin.resources.videos.index') }}" class="ht-link">Manage</a>
                </div>
                @if($recentVideos->count())
                    @foreach($recentVideos as $video)
                        <a href="{{ route('filament.admin.resources.videos.edit', $video) }}" class="ht-list-row">
                            <div class="ht-thumb">
                                @if($video->thumbnail_url)
                                    <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" />
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="ht-title">{{ $video->title }}</p>
                                <div class="flex items-center gap-3 mt-0.5">
                                    <span class="ht-meta">{{ number_format($video->views_count) }} views</span>
                                    <span class="ht-meta">{{ $video->created_at->diffForHumans() }}</span>
                                    <span class="ht-badge" style="background: {{ $video->status === 'processed' ? 'rgba(34,197,94,0.1)' : ($video->status === 'failed' ? 'rgba(239,68,68,0.1)' : 'rgba(234,179,8,0.1)') }}; color: {{ $video->status === 'processed' ? '#22c55e' : ($video->status === 'failed' ? '#ef4444' : '#eab308') }};">{{ $video->status }}</span>
                                </div>
                            </div>
                            @if($video->user)
                                <span class="ht-meta hidden sm:block">{{ $video->user->username }}</span>
                            @endif
                        </a>
                    @endforeach
                @else
                    <div class="p-8 text-center">
                        <x-heroicon-m-video-camera class="w-10 h-10 mx-auto mb-2" style="color: rgba(255,255,255,0.15);" />
                        <p style="font-size: 0.875rem; color: rgba(255,255,255,0.4);">No videos uploaded yet</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Recent Signups --}}
        <div class="ht-list-card">
            <div class="ht-list-header">
                <h2 class="ht-section-title">
                    <x-heroicon-m-user-plus class="w-4 h-4" style="color: #22c55e;" />
                    Recent Signups
                </h2>
                <a href="{{ route('filament.admin.resources.users.index') }}" class="ht-link">View All</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-5" style="border-top: none;">
                @foreach($recentUsers as $user)
                    <a href="{{ route('filament.admin.resources.users.edit', $user) }}" class="ht-list-row" style="border-right: 1px solid rgba(255,255,255,0.05);">
                        <div class="ht-avatar" style="background: rgba(var(--primary-400), 0.1);">
                            @if($user->avatar)
                                <img src="{{ $user->avatar }}" class="w-8 h-8 rounded-full object-cover" />
                            @else
                                <span style="color: rgb(var(--primary-400));">{{ strtoupper(substr($user->username, 0, 2)) }}</span>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="ht-title">{{ $user->username }}</p>
                            <p class="ht-meta">{{ $user->created_at->diffForHumans() }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

    </div>
</x-filament-panels::page>
