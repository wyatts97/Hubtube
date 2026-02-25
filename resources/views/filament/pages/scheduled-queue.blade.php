<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section heading="Scheduled Draft Queue" icon="heroicon-o-queue-list" description="Drag and drop rows to reorder your videos. The system will automatically recalculate their publish times based on your Schedule Settings.">
            {{ $this->table }}
        </x-filament::section>

        @if (count(\App\Models\Video::whereNotNull('published_at')->where('published_at', '>', now()->subDays(7))->where('is_approved', true)->limit(1)->get()) > 0)
            <x-filament::section collapsible collapsed heading="Recently Published" icon="heroicon-o-check-circle">
                <div class="space-y-2">
                    @foreach (\App\Models\Video::with('user')->whereNotNull('published_at')->where('published_at', '>', now()->subDays(7))->where('is_approved', true)->orderByDesc('published_at')->limit(10)->get() as $video)
                        <div class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <div class="flex items-center gap-3">
                                @if ($video->thumbnail_url)
                                    <img src="{{ $video->thumbnail_url }}" alt="" class="w-12 h-7 rounded object-cover">
                                @endif
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white text-sm">{{ $video->title }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Published {{ $video->published_at->diffForHumans() }}</p>
                                </div>
                            </div>
                            <x-filament::badge color="success" size="sm">Published</x-filament::badge>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
