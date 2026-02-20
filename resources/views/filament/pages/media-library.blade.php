<x-filament-panels::page>
<div class="space-y-6">

    {{-- Delete Confirmation Modal --}}
    @if ($deleteTarget)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
        <x-filament::section class="max-w-sm w-full text-center">
            <div class="flex flex-col items-center gap-4">
                <x-heroicon-o-trash class="w-10 h-10 text-danger-400" />
                <div>
                    <p class="text-sm font-semibold text-gray-100">Delete File?</p>
                    <p class="text-xs text-gray-400 mt-1 break-all">{{ basename($deleteTarget) }}</p>
                </div>
                <div class="flex gap-3">
                    <x-filament::button wire:click="cancelDelete" color="gray" size="sm">Cancel</x-filament::button>
                    <x-filament::button wire:click="deleteFile" color="danger" size="sm" icon="heroicon-o-trash">Delete</x-filament::button>
                </div>
            </div>
        </x-filament::section>
    </div>
    @endif

    {{-- Tab Bar --}}
    <div class="flex border-b border-gray-700">
        <button
            wire:click="$set('activeTab', 'images')"
            @class([
                'flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 -mb-px transition-colors',
                'text-primary-400 border-primary-500' => $activeTab === 'images',
                'text-gray-400 border-transparent hover:text-gray-200 hover:border-gray-500' => $activeTab !== 'images',
            ])
        >
            <x-heroicon-o-photo class="w-4 h-4" />
            Images
        </button>
        <button
            wire:click="$set('activeTab', 'videos')"
            @class([
                'flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 -mb-px transition-colors',
                'text-primary-400 border-primary-500' => $activeTab === 'videos',
                'text-gray-400 border-transparent hover:text-gray-200 hover:border-gray-500' => $activeTab !== 'videos',
            ])
        >
            <x-heroicon-o-film class="w-4 h-4" />
            Ad Videos
        </button>
    </div>

    {{-- ── IMAGES TAB ── --}}
    @if ($activeTab === 'images')

        <x-filament::section>
            <x-slot name="heading">Upload Images</x-slot>
            <x-slot name="description">JPG, PNG, GIF, WebP, SVG, ICO — max 10 MB each</x-slot>

            <label class="flex flex-col items-center justify-center gap-3 p-8 border-2 border-dashed border-gray-600 rounded-xl cursor-pointer hover:border-primary-500 transition-colors">
                <input type="file" wire:model="uploadedImages" multiple accept="image/*" class="hidden">
                <x-heroicon-o-arrow-up-tray class="w-8 h-8 text-gray-400" />
                <span class="text-sm font-medium text-gray-300">Drop images here or click to browse</span>
            </label>

            @if ($uploadedImages)
                <div class="mt-4 flex items-center gap-3" wire:loading.remove wire:target="uploadImages">
                    <span class="text-sm text-gray-400">{{ count($uploadedImages) }} file(s) selected</span>
                    <x-filament::button wire:click="uploadImages" size="sm" icon="heroicon-o-arrow-up-tray">
                        Upload Now
                    </x-filament::button>
                </div>
                <div class="mt-4 flex items-center gap-2 text-sm text-gray-400" wire:loading wire:target="uploadImages">
                    <x-heroicon-o-arrow-path class="w-4 h-4 animate-spin" /> Uploading…
                </div>
            @endif
        </x-filament::section>

        @php $images = $this->getImageFiles(); @endphp

        <x-filament::section>
            <x-slot name="heading">
                Images
                @if (!empty($images))
                    <span class="ml-2 text-xs font-normal text-gray-500">{{ count($images) }} file(s) — click Copy URL to use in Ad Settings</span>
                @endif
            </x-slot>

            @if (empty($images))
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <x-heroicon-o-photo class="w-12 h-12 text-gray-600 mb-3" />
                    <p class="text-sm font-medium text-gray-400">No images yet</p>
                    <p class="text-xs text-gray-600 mt-1">Upload banner images, logos, and other static assets above.</p>
                </div>
            @else
                <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));">
                    @foreach ($images as $file)
                    <div class="bg-gray-900 border border-gray-700 rounded-xl overflow-hidden hover:border-primary-500 transition-colors group">
                        <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}"
                             class="w-full object-cover" style="height:110px;" loading="lazy">
                        <div class="p-2.5">
                            <p class="text-xs text-gray-300 truncate mb-0.5" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
                            <p class="text-[10px] text-gray-500 mb-2">{{ $file['size'] }}</p>
                            <div class="flex gap-1.5">
                                <button
                                    class="flex-1 text-[11px] px-2 py-1 bg-gray-700 text-gray-300 rounded-lg hover:bg-gray-600 transition-colors"
                                    onclick="(function(btn,url){navigator.clipboard.writeText(url).then(()=>{btn.textContent='Copied!';btn.classList.add('!bg-success-900','!text-success-300');setTimeout(()=>{btn.textContent='Copy URL';btn.classList.remove('!bg-success-900','!text-success-300');},2000);})})(this,'{{ $file['url'] }}')"
                                >Copy URL</button>
                                <button
                                    wire:click="confirmDelete('{{ $file['path'] }}')"
                                    class="text-[11px] px-2 py-1 bg-danger-900/50 text-danger-400 rounded-lg hover:bg-danger-800 transition-colors"
                                >
                                    <x-heroicon-o-trash class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    @endif

    {{-- ── AD VIDEOS TAB ── --}}
    @if ($activeTab === 'videos')

        <x-filament::section>
            <x-slot name="heading">Upload Ad Videos</x-slot>
            <x-slot name="description">MP4, WebM, MOV — max 200 MB each</x-slot>

            <label class="flex flex-col items-center justify-center gap-3 p-8 border-2 border-dashed border-gray-600 rounded-xl cursor-pointer hover:border-primary-500 transition-colors">
                <input type="file" wire:model="uploadedVideos" multiple accept="video/mp4,video/webm,video/quicktime" class="hidden">
                <x-heroicon-o-arrow-up-tray class="w-8 h-8 text-gray-400" />
                <span class="text-sm font-medium text-gray-300">Drop ad videos here or click to browse</span>
            </label>

            @if ($uploadedVideos)
                <div class="mt-4 flex items-center gap-3" wire:loading.remove wire:target="uploadVideos">
                    <span class="text-sm text-gray-400">{{ count($uploadedVideos) }} file(s) selected</span>
                    <x-filament::button wire:click="uploadVideos" size="sm" icon="heroicon-o-arrow-up-tray">
                        Upload Now
                    </x-filament::button>
                </div>
                <div class="mt-4 flex items-center gap-2 text-sm text-gray-400" wire:loading wire:target="uploadVideos">
                    <x-heroicon-o-arrow-path class="w-4 h-4 animate-spin" /> Uploading…
                </div>
            @endif
        </x-filament::section>

        @php $videos = $this->getVideoFiles(); @endphp

        <x-filament::section>
            <x-slot name="heading">
                Ad Videos
                @if (!empty($videos))
                    <span class="ml-2 text-xs font-normal text-gray-500">{{ count($videos) }} file(s) — copy the URL into an MP4 ad creative</span>
                @endif
            </x-slot>

            @if (empty($videos))
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <x-heroicon-o-film class="w-12 h-12 text-gray-600 mb-3" />
                    <p class="text-sm font-medium text-gray-400">No ad videos yet</p>
                    <p class="text-xs text-gray-600 mt-1">Upload MP4 ad creatives above, then copy the URL into Appearance → Ad Creatives.</p>
                </div>
            @else
                <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));">
                    @foreach ($videos as $file)
                    <div class="bg-gray-900 border border-gray-700 rounded-xl overflow-hidden hover:border-primary-500 transition-colors">
                        <video src="{{ $file['url'] }}" class="w-full" style="height:110px;object-fit:cover;" muted preload="metadata"></video>
                        <div class="p-2.5">
                            <p class="text-xs text-gray-300 truncate mb-0.5" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
                            <p class="text-[10px] text-gray-500 mb-2">{{ $file['size'] }}</p>
                            <div class="flex gap-1.5">
                                <button
                                    class="flex-1 text-[11px] px-2 py-1 bg-gray-700 text-gray-300 rounded-lg hover:bg-gray-600 transition-colors"
                                    onclick="(function(btn,url){navigator.clipboard.writeText(url).then(()=>{btn.textContent='Copied!';btn.classList.add('!bg-success-900','!text-success-300');setTimeout(()=>{btn.textContent='Copy URL';btn.classList.remove('!bg-success-900','!text-success-300');},2000);})})(this,'{{ $file['url'] }}')"
                                >Copy URL</button>
                                <button
                                    wire:click="confirmDelete('{{ $file['path'] }}')"
                                    class="text-[11px] px-2 py-1 bg-danger-900/50 text-danger-400 rounded-lg hover:bg-danger-800 transition-colors"
                                >
                                    <x-heroicon-o-trash class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    @endif

</div>
</x-filament-panels::page>
