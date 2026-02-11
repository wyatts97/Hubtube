<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Primary Stats Grid — Glassmorphism cards with colored accents --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            {{-- Total Users --}}
            <a href="{{ route('filament.admin.resources.users.index') }}" class="group block rounded-xl p-4 shadow-sm border border-blue-500/20 bg-blue-500/5 dark:bg-blue-500/10 backdrop-blur-sm hover:border-blue-400 hover:bg-blue-500/10 dark:hover:bg-blue-500/15 transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-blue-500/20">
                        <x-heroicon-m-users class="w-5 h-5 text-blue-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">Total Users</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($totalUsers) }}</p>
                    </div>
                </div>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">+{{ $users24h }} today &middot; +{{ $users7d }} this week</p>
            </a>

            {{-- Total Videos --}}
            <a href="{{ route('filament.admin.resources.videos.index') }}" class="group block rounded-xl p-4 shadow-sm border border-green-500/20 bg-green-500/5 dark:bg-green-500/10 backdrop-blur-sm hover:border-green-400 hover:bg-green-500/10 dark:hover:bg-green-500/15 transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-green-500/20">
                        <x-heroicon-m-video-camera class="w-5 h-5 text-green-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">Total Videos</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($totalVideos) }}</p>
                    </div>
                </div>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">+{{ $videos24h }} today &middot; +{{ $videos7d }} this week</p>
            </a>

            {{-- Total Views --}}
            <div class="rounded-xl p-4 shadow-sm border border-purple-500/20 bg-purple-500/5 dark:bg-purple-500/10 backdrop-blur-sm">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-purple-500/20">
                        <x-heroicon-m-eye class="w-5 h-5 text-purple-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">Total Views</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($totalViews) }}</p>
                    </div>
                </div>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">{{ number_format($totalComments) }} comments &middot; +{{ $comments7d }} this week</p>
            </div>

            {{-- Revenue --}}
            <a href="{{ route('filament.admin.resources.wallet-transactions.index') }}" class="group block rounded-xl p-4 shadow-sm border border-amber-500/20 bg-amber-500/5 dark:bg-amber-500/10 backdrop-blur-sm hover:border-amber-400 hover:bg-amber-500/10 dark:hover:bg-amber-500/15 transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-amber-500/20">
                        <x-heroicon-m-banknotes class="w-5 h-5 text-amber-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">Revenue</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">${{ number_format($totalRevenue, 2) }}</p>
                    </div>
                </div>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">${{ number_format($revenue7d, 2) }} this week &middot; ${{ number_format($revenue30d, 2) }} this month</p>
            </a>

            {{-- Playlists --}}
            <div class="rounded-xl p-4 shadow-sm border border-pink-500/20 bg-pink-500/5 dark:bg-pink-500/10 backdrop-blur-sm">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-pink-500/20">
                        <x-heroicon-m-queue-list class="w-5 h-5 text-pink-400" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">Playlists</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($totalPlaylists) }}</p>
                    </div>
                </div>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">+{{ $playlists7d }} this week</p>
            </div>
        </div>

        {{-- Secondary Stats Row — Card style matching primary widgets --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            {{-- Live Now --}}
            <a href="{{ route('filament.admin.resources.live-streams.index') }}" class="group block rounded-xl p-4 shadow-sm border border-red-500/20 bg-red-500/5 dark:bg-red-500/10 backdrop-blur-sm hover:border-red-400 hover:bg-red-500/10 dark:hover:bg-red-500/15 transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-red-500/20">
                        <x-heroicon-m-signal class="w-4 h-4 {{ $liveNow > 0 ? 'text-red-400 animate-pulse' : 'text-red-400/50' }}" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Live Now</p>
                        <p class="text-base font-bold text-gray-900 dark:text-white">{{ $liveNow }}</p>
                    </div>
                </div>
            </a>

            {{-- Storage --}}
            <div class="rounded-xl p-4 shadow-sm border border-cyan-500/20 bg-cyan-500/5 dark:bg-cyan-500/10 backdrop-blur-sm">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-cyan-500/20">
                        <x-heroicon-m-server-stack class="w-4 h-4 text-cyan-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Storage</p>
                        <p class="text-base font-bold text-gray-900 dark:text-white">{{ \App\Filament\Pages\Dashboard::formatBytes($totalSize) }}</p>
                    </div>
                </div>
            </div>

            {{-- Processing --}}
            <div class="rounded-xl p-4 shadow-sm border border-indigo-500/20 bg-indigo-500/5 dark:bg-indigo-500/10 backdrop-blur-sm">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-indigo-500/20">
                        <x-heroicon-m-cog-6-tooth class="w-4 h-4 {{ $processingCount > 0 ? 'text-indigo-400 animate-spin' : 'text-indigo-400/50' }}" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Processing</p>
                        <p class="text-base font-bold text-gray-900 dark:text-white">
                            @if($processingCount > 0)
                                {{ $processingCount }} Encoding
                            @else
                                Idle
                            @endif
                        </p>
                        @if($pendingCount > 0 || $failedCount > 0)
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                @if($pendingCount > 0) {{ $pendingCount }} queued @endif
                                @if($failedCount > 0) <span class="text-red-400">{{ $failedCount }} failed</span> @endif
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Comments --}}
            <a href="{{ route('filament.admin.resources.comments.index') }}" class="group block rounded-xl p-4 shadow-sm border border-yellow-500/20 bg-yellow-500/5 dark:bg-yellow-500/10 backdrop-blur-sm hover:border-yellow-400 hover:bg-yellow-500/10 dark:hover:bg-yellow-500/15 transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-yellow-500/20">
                        <x-heroicon-m-chat-bubble-left-right class="w-4 h-4 text-yellow-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Comments</p>
                        <p class="text-base font-bold text-gray-900 dark:text-white">{{ number_format($totalComments) }}</p>
                    </div>
                </div>
            </a>
        </div>

        {{-- Two Column Layout: Trending + Recent Uploads (side by side on desktop) --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Trending Videos --}}
            <div class="rounded-xl shadow-sm border border-gray-200 dark:border-gray-700/50 bg-white dark:bg-gray-800/50 overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700/50 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-m-fire class="w-4 h-4 text-orange-500" />
                        Trending Videos
                    </h2>
                    <a href="{{ route('filament.admin.resources.videos.index') }}" class="text-sm text-primary-500 hover:text-primary-400">View All</a>
                </div>
                @if($trendingVideos->count())
                    <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
                        @foreach($trendingVideos as $index => $video)
                            <a href="{{ route('filament.admin.resources.videos.edit', $video) }}" class="flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                <span class="text-lg font-bold w-6 text-center text-gray-300 dark:text-gray-600">{{ $index + 1 }}</span>
                                <div class="w-20 h-12 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700 shrink-0">
                                    @if($video->thumbnail_url)
                                        <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" class="w-full h-full object-cover" />
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $video->title }}</p>
                                    <div class="flex items-center gap-3 mt-0.5">
                                        <span class="text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1">
                                            <x-heroicon-m-eye class="w-3 h-3" /> {{ number_format($video->views_count) }}
                                        </span>
                                        <span class="text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1">
                                            <x-heroicon-m-hand-thumb-up class="w-3 h-3" /> {{ number_format($video->likes_count) }}
                                        </span>
                                        @if($video->user)
                                            <span class="text-xs text-gray-400 dark:text-gray-500">by {{ $video->user->username }}</span>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 text-center">
                        <x-heroicon-m-video-camera class="w-10 h-10 mx-auto mb-2 text-gray-300 dark:text-gray-600" />
                        <p class="text-sm text-gray-500 dark:text-gray-400">No videos yet</p>
                    </div>
                @endif
            </div>

            {{-- Recent Uploads --}}
            <div class="rounded-xl shadow-sm border border-gray-200 dark:border-gray-700/50 bg-white dark:bg-gray-800/50 overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700/50 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-m-clock class="w-4 h-4 text-blue-500" />
                        Recent Uploads
                    </h2>
                    <a href="{{ route('filament.admin.resources.videos.index') }}" class="text-sm text-primary-500 hover:text-primary-400">Manage</a>
                </div>
                @if($recentVideos->count())
                    <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
                        @foreach($recentVideos as $video)
                            <a href="{{ route('filament.admin.resources.videos.edit', $video) }}" class="flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                <div class="w-20 h-12 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700 shrink-0">
                                    @if($video->thumbnail_url)
                                        <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" class="w-full h-full object-cover" />
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $video->title }}</p>
                                    <div class="flex items-center gap-3 mt-0.5">
                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ number_format($video->views_count) }} views</span>
                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $video->created_at->diffForHumans() }}</span>
                                        <span class="text-xs px-1.5 py-0.5 rounded {{ $video->status === 'processed' ? 'bg-green-500/10 text-green-400' : ($video->status === 'failed' ? 'bg-red-500/10 text-red-400' : 'bg-yellow-500/10 text-yellow-400') }}">{{ $video->status }}</span>
                                    </div>
                                </div>
                                @if($video->user)
                                    <span class="text-xs text-gray-400 dark:text-gray-500 hidden sm:block">{{ $video->user->username }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 text-center">
                        <x-heroicon-m-video-camera class="w-10 h-10 mx-auto mb-2 text-gray-300 dark:text-gray-600" />
                        <p class="text-sm text-gray-500 dark:text-gray-400">No videos uploaded yet</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Recent Signups --}}
        <div class="rounded-xl shadow-sm border border-gray-200 dark:border-gray-700/50 bg-white dark:bg-gray-800/50 overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700/50 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-m-user-plus class="w-4 h-4 text-green-500" />
                    Recent Signups
                </h2>
                <a href="{{ route('filament.admin.resources.users.index') }}" class="text-sm text-primary-500 hover:text-primary-400">View All</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-5 divide-y sm:divide-y-0 sm:divide-x divide-gray-100 dark:divide-gray-700/50">
                @foreach($recentUsers as $user)
                    <a href="{{ route('filament.admin.resources.users.edit', $user) }}" class="flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                        <div class="w-8 h-8 rounded-full bg-primary-500/10 flex items-center justify-center shrink-0">
                            @if($user->avatar)
                                <img src="{{ $user->avatar }}" class="w-8 h-8 rounded-full object-cover" />
                            @else
                                <span class="text-xs font-bold text-primary-500">{{ strtoupper(substr($user->username, 0, 2)) }}</span>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $user->username }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $user->created_at->diffForHumans() }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

    </div>
</x-filament-panels::page>
