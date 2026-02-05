<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Search Form -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
            <form wire:submit="search" class="space-y-4">
                {{ $this->form }}
                
                <div class="flex items-center gap-4">
                    <x-filament::button type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="search">
                            <x-heroicon-m-magnifying-glass class="w-5 h-5 mr-2" />
                            Search Videos
                        </span>
                        <span wire:loading wire:target="search">
                            <x-filament::loading-indicator class="w-5 h-5 mr-2" />
                            Searching...
                        </span>
                    </x-filament::button>
                    
                    @if(count($searchResults) > 0)
                        <span class="text-sm text-gray-500">
                            Found {{ count($searchResults) }} videos on page {{ $currentPage }}
                        </span>
                    @endif
                </div>
            </form>
        </div>

        <!-- Error Message -->
        @if($errorMessage)
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <x-heroicon-m-exclamation-circle class="w-6 h-6 text-red-500 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="font-medium text-red-700 dark:text-red-400">{{ $errorMessage }}</p>
                        @if($errorSuggestion ?? false)
                            <p class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $errorSuggestion }}</p>
                        @endif
                        @if($isBlocked ?? false)
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                <strong>Tip:</strong> Some adult sites block access from certain US states (like Texas). 
                                XNXX typically works without restrictions. You can also try using a VPN or proxy service.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- Bulk Actions -->
        @if(count($searchResults) > 0)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div class="flex items-center gap-4">
                        <x-filament::button size="sm" color="gray" wire:click="selectAll">
                            <x-heroicon-m-check-circle class="w-4 h-4 mr-1" />
                            Select All
                        </x-filament::button>
                        <x-filament::button size="sm" color="gray" wire:click="deselectAll">
                            <x-heroicon-m-x-circle class="w-4 h-4 mr-1" />
                            Deselect All
                        </x-filament::button>
                        
                        @if(count($selectedVideos) > 0)
                            <span class="text-sm font-medium text-primary-600 dark:text-primary-400">
                                {{ count($selectedVideos) }} selected
                            </span>
                        @endif
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <!-- Pagination -->
                        <div class="flex items-center gap-2">
                            <x-filament::button 
                                size="sm" 
                                color="gray" 
                                wire:click="prevPage"
                                :disabled="!$hasPrevPage"
                            >
                                <x-heroicon-m-chevron-left class="w-4 h-4" />
                            </x-filament::button>
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                Page {{ $currentPage }}
                            </span>
                            <x-filament::button 
                                size="sm" 
                                color="gray" 
                                wire:click="nextPage"
                                :disabled="!$hasNextPage"
                            >
                                <x-heroicon-m-chevron-right class="w-4 h-4" />
                            </x-filament::button>
                        </div>
                        
                        <x-filament::button 
                            color="success" 
                            wire:click="importSelected"
                            :disabled="count($selectedVideos) === 0"
                        >
                            <x-heroicon-m-arrow-down-tray class="w-4 h-4 mr-1" />
                            Import Selected ({{ count($selectedVideos) }})
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif

        <!-- Loading State -->
        <div wire:loading wire:target="search,nextPage,prevPage" class="flex justify-center py-12">
            <x-filament::loading-indicator class="w-10 h-10 text-primary-500" />
        </div>

        <!-- Video Grid -->
        @if(count($searchResults) > 0)
            <div wire:loading.remove wire:target="search,nextPage,prevPage" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                @foreach($searchResults as $index => $video)
                    <div 
                        wire:key="video-{{ $index }}-{{ $video['sourceId'] }}"
                        class="relative group rounded-lg overflow-hidden bg-white dark:bg-gray-800 shadow hover:shadow-lg transition-shadow cursor-pointer
                            {{ in_array($video['sourceId'], $selectedVideos) ? 'ring-2 ring-primary-500' : '' }}
                            {{ ($video['isImported'] ?? false) ? 'opacity-50' : '' }}"
                        wire:click="toggleVideoSelection('{{ $video['sourceId'] }}')"
                    >
                        <!-- Thumbnail -->
                        <div class="relative aspect-video bg-gray-900 overflow-hidden">
                            @if(!empty($video['thumbnail']))
                                <img 
                                    src="{{ $video['thumbnail'] }}" 
                                    alt=""
                                    class="w-full h-full object-cover"
                                    loading="lazy"
                                    onerror="this.parentElement.querySelector('.thumb-fallback').style.display='flex'; this.style.display='none';"
                                >
                                <div class="thumb-fallback w-full h-full items-center justify-center absolute inset-0 bg-gray-800/90" style="display: none;">
                                    <x-heroicon-o-film class="w-10 h-10 text-gray-500" />
                                </div>
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-gray-800/90">
                                    <x-heroicon-o-film class="w-10 h-10 text-gray-500" />
                                </div>
                            @endif
                            
                            <!-- Selection Checkbox -->
                            <div class="absolute top-2 left-2 z-10">
                                @if($video['isImported'] ?? false)
                                    <div class="bg-green-500 text-white text-xs px-2 py-1 rounded flex items-center gap-1">
                                        <x-heroicon-m-check class="w-3 h-3" />
                                        Imported
                                    </div>
                                @else
                                    <div class="w-6 h-6 rounded border-2 flex items-center justify-center
                                        {{ in_array($video['sourceId'], $selectedVideos) 
                                            ? 'bg-primary-500 border-primary-500 text-white' 
                                            : 'bg-white/80 border-gray-300' }}">
                                        @if(in_array($video['sourceId'], $selectedVideos))
                                            <x-heroicon-m-check class="w-4 h-4" />
                                        @endif
                                    </div>
                                @endif
                            </div>
                            
                            <!-- Source Badge -->
                            <div class="absolute top-2 right-2 bg-black/80 text-white text-xs px-1.5 py-0.5 rounded uppercase z-10">
                                {{ $video['sourceSite'] ?? 'unknown' }}
                            </div>
                            
                            <!-- Duration Badge - always at bottom-right, inside the thumbnail area -->
                            @if(!empty($video['durationFormatted']) && $video['durationFormatted'] !== '0:00')
                                <div class="absolute bottom-1.5 right-1.5 bg-black/80 text-white text-xs font-medium px-1.5 py-0.5 rounded z-10">
                                    {{ $video['durationFormatted'] }}
                                </div>
                            @elseif(!empty($video['duration']) && $video['duration'] > 0)
                                @php
                                    $d = (int) $video['duration'];
                                    $h = intdiv($d, 3600);
                                    $m = intdiv($d % 3600, 60);
                                    $s = $d % 60;
                                    $fmt = $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
                                @endphp
                                <div class="absolute bottom-1.5 right-1.5 bg-black/80 text-white text-xs font-medium px-1.5 py-0.5 rounded z-10">
                                    {{ $fmt }}
                                </div>
                            @endif
                        </div>
                        
                        <!-- Video Info -->
                        <div class="p-3">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2 leading-tight" title="{{ $video['title'] }}">
                                {{ Str::limit($video['title'] ?? 'Untitled', 60) }}
                            </h3>
                            <div class="mt-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                @if(!empty($video['views']))
                                    <span>{{ number_format($video['views']) }} views</span>
                                @endif
                                @if(!empty($video['rating']))
                                    <span>{{ $video['rating'] }}%</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <!-- Bottom Pagination -->
            <div class="flex justify-center gap-4 pt-4">
                <x-filament::button 
                    size="sm" 
                    color="gray" 
                    wire:click="prevPage"
                    :disabled="!$hasPrevPage"
                >
                    <x-heroicon-m-chevron-left class="w-4 h-4 mr-1" />
                    Previous
                </x-filament::button>
                <span class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                    Page {{ $currentPage }}
                </span>
                <x-filament::button 
                    size="sm" 
                    color="gray" 
                    wire:click="nextPage"
                    :disabled="!$hasNextPage"
                >
                    Next
                    <x-heroicon-m-chevron-right class="w-4 h-4 ml-1" />
                </x-filament::button>
            </div>
        @elseif(!$isLoading && $searchQuery && !$errorMessage)
            <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                <x-heroicon-o-film class="w-16 h-16 mx-auto mb-4 opacity-50" />
                <p>No videos found. Try different keywords.</p>
            </div>
        @elseif(!$searchQuery)
            <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                <x-heroicon-o-magnifying-glass class="w-16 h-16 mx-auto mb-4 opacity-50" />
                <p>Enter keywords and click Search to find videos.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
