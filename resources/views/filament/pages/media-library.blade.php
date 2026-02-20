<x-filament-panels::page>
<div class="space-y-6">

    {{-- Delete Confirmation Modal --}}
    @if ($deleteTarget)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
        <div class="bg-gray-800 border border-gray-700 rounded-xl shadow-2xl p-6 max-w-sm w-full mx-4">
            <div class="flex flex-col items-center gap-3 text-center">
                <div class="w-10 h-10 rounded-full bg-danger-500/20 flex items-center justify-center">
                    <x-heroicon-o-trash class="w-5 h-5 text-danger-400" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-100">Delete File?</p>
                    <p class="text-xs text-gray-400 mt-1 break-all">{{ basename($deleteTarget) }}</p>
                </div>
                <div class="flex gap-3 w-full">
                    <x-filament::button wire:click="cancelDelete" color="gray" size="sm" class="flex-1">Cancel</x-filament::button>
                    <x-filament::button wire:click="deleteFile" color="danger" size="sm" class="flex-1">Delete</x-filament::button>
                </div>
            </div>
        </div>
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

    {{-- IMAGES TAB --}}
    @if ($activeTab === 'images')

        <x-filament::section>
            <x-slot name="heading">Upload Images</x-slot>
            <x-slot name="description">JPG, PNG, GIF, WebP, SVG, ICO  max 10 MB each</x-slot>

            <div
                x-data="{ dragging: false }"
                x-on:dragover.prevent="dragging = true"
                x-on:dragleave.prevent="dragging = false"
                x-on:drop.prevent="dragging = false"
                :class="dragging ? 'border-primary-500 bg-primary-500/5' : 'border-gray-600 hover:border-gray-500'"
                class="relative border-2 border-dashed rounded-xl transition-colors"
            >
                <label class="flex flex-col items-center justify-center gap-2 py-6 px-4 cursor-pointer">
                    <input type="file" wire:model="uploadedImages" multiple accept="image/*"
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                    <x-heroicon-o-arrow-up-tray class="w-6 h-6 text-gray-400" />
                    <span class="text-sm text-gray-300 font-medium">Drop images here or <span class="text-primary-400 underline">click to browse</span></span>
                    <span class="text-xs text-gray-500">JPG, PNG, GIF, WebP, SVG, ICO  max 10 MB</span>
                </label>
            </div>

            <div wire:loading wire:target="uploadedImages" class="mt-3 flex items-center gap-2 text-sm text-gray-400">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                Processing
            </div>

            @if ($uploadedImages)
                <div class="mt-3 flex items-center gap-3" wire:loading.remove wire:target="uploadedImages">
                    <span class="text-sm text-gray-400">{{ count($uploadedImages) }} file(s) ready</span>
                    <x-filament::button wire:click="uploadImages" size="sm" icon="heroicon-o-arrow-up-tray">
                        Upload Now
                    </x-filament::button>
                </div>
            @endif
        </x-filament::section>

        @php $images = $this->getImageFiles(); @endphp

        <x-filament::section>
            <x-slot name="heading">
                Images
                @if (!empty($images))
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-700 text-gray-300">{{ count($images) }}</span>
                @endif
            </x-slot>
            @if (!empty($images))
                <x-slot name="description">Click "Copy URL" to use in Ad Settings</x-slot>
            @endif

            @if (empty($images))
                <div class="flex flex-col items-center justify-center py-10 text-center gap-2">
                    <x-heroicon-o-photo class="w-8 h-8 text-gray-600" />
                    <p class="text-sm text-gray-400">No images yet</p>
                    <p class="text-xs text-gray-600">Upload banner images, logos, and other static assets above.</p>
                </div>
            @else
                <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));">
                    @foreach ($images as $file)
                    <div class="group relative bg-gray-900 border border-gray-700 rounded-xl overflow-hidden hover:border-primary-500 transition-colors">
                        <div style="height:100px;">
                            <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}"
                                 class="w-full h-full object-cover" loading="lazy">
                        </div>
                        <div class="p-2">
                            <p class="text-xs text-gray-300 truncate leading-tight" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
                            <p class="text-[10px] text-gray-500 mt-0.5">{{ $file['size'] }}</p>
                            <div class="flex gap-1 mt-2">
                                <button
                                    class="flex-1 text-[11px] py-1 px-1.5 bg-gray-700 text-gray-300 rounded-lg hover:bg-gray-600 transition-colors font-medium"
                                    onclick="(function(btn,url){navigator.clipboard.writeText(url).then(()=>{btn.textContent='Copied!';btn.style.background='#14532d';btn.style.color='#86efac';setTimeout(()=>{btn.textContent='Copy URL';btn.style.background='';btn.style.color='';},2000);})})(this,'{{ $file['url'] }}')"
                                >Copy URL</button>
                                <button wire:click="confirmDelete('{{ $file['path'] }}')"
                                    class="p-1 bg-gray-700 text-gray-400 rounded-lg hover:bg-danger-900 hover:text-danger-400 transition-colors" title="Delete">
                                    <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    @endif

    {{-- AD VIDEOS TAB --}}
    @if ($activeTab === 'videos')

        <x-filament::section>
            <x-slot name="heading">Upload Ad Videos</x-slot>
            <x-slot name="description">MP4, WebM, MOV  max 200 MB each</x-slot>

            <div
                x-data="{ dragging: false }"
                x-on:dragover.prevent="dragging = true"
                x-on:dragleave.prevent="dragging = false"
                x-on:drop.prevent="dragging = false"
                :class="dragging ? 'border-primary-500 bg-primary-500/5' : 'border-gray-600 hover:border-gray-500'"
                class="relative border-2 border-dashed rounded-xl transition-colors"
            >
                <label class="flex flex-col items-center justify-center gap-2 py-6 px-4 cursor-pointer">
                    <input type="file" wire:model="uploadedVideos" multiple accept="video/mp4,video/webm,video/quicktime"
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                    <x-heroicon-o-arrow-up-tray class="w-6 h-6 text-gray-400" />
                    <span class="text-sm text-gray-300 font-medium">Drop videos here or <span class="text-primary-400 underline">click to browse</span></span>
                    <span class="text-xs text-gray-500">MP4, WebM, MOV  max 200 MB</span>
                </label>
            </div>

            <div wire:loading wire:target="uploadedVideos" class="mt-3 flex items-center gap-2 text-sm text-gray-400">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                Processing
            </div>

            @if ($uploadedVideos)
                <div class="mt-3 flex items-center gap-3" wire:loading.remove wire:target="uploadedVideos">
                    <span class="text-sm text-gray-400">{{ count($uploadedVideos) }} file(s) ready</span>
                    <x-filament::button wire:click="uploadVideos" size="sm" icon="heroicon-o-arrow-up-tray">
                        Upload Now
                    </x-filament::button>
                </div>
            @endif
        </x-filament::section>

        @php $videos = $this->getVideoFiles(); @endphp

        <x-filament::section>
            <x-slot name="heading">
                Ad Videos
                @if (!empty($videos))
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-700 text-gray-300">{{ count($videos) }}</span>
                @endif
            </x-slot>
            @if (!empty($videos))
                <x-slot name="description">Copy the URL and paste it into an MP4 ad creative</x-slot>
            @endif

            @if (empty($videos))
                <div class="flex flex-col items-center justify-center py-10 text-center gap-2">
                    <x-heroicon-o-film class="w-8 h-8 text-gray-600" />
                    <p class="text-sm text-gray-400">No ad videos yet</p>
                    <p class="text-xs text-gray-600">Upload MP4 ad creatives above, then copy the URL into Appearance  Ad Creatives.</p>
                </div>
            @else
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
                    @foreach ($videos as $file)
                    <div class="group" style="background:#18181B;border:1px solid #242427;border-radius:12px;display:flex;flex-direction:column;overflow:hidden;transition:border-color 0.15s;" onmouseenter="this.style.borderColor='#f43f5e'" onmouseleave="this.style.borderColor='#242427'">
                        {{-- Thumbnail --}}
                        <div style="position:relative;height:110px;background:#000;flex-shrink:0;">
                            <video src="{{ $file['url'] }}" style="width:100%;height:100%;object-fit:cover;display:block;" muted preload="metadata"></video>
                            @if (!empty($file['duration']))
                                <span style="position:absolute;bottom:6px;right:6px;background:rgba(0,0,0,0.85);color:#fff;font-size:10px;font-family:monospace;font-weight:600;padding:2px 5px;border-radius:4px;line-height:1.4;">{{ $file['duration'] }}</span>
                            @endif
                            <div class="opacity-0 group-hover:opacity-100 transition-opacity" style="position:absolute;inset:0;background:rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;">
                                <div style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;">
                                    <x-heroicon-s-play style="width:16px;height:16px;color:#fff;margin-left:2px;" />
                                </div>
                            </div>
                        </div>
                        {{-- Info --}}
                        <div style="padding:10px;flex:1;display:flex;flex-direction:column;gap:4px;">
                            <p style="font-size:11px;color:#d1d5db;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
                            <p style="font-size:10px;color:#6b7280;">{{ $file['size'] }}</p>
                            <div style="display:flex;gap:4px;margin-top:4px;">
                                <button
                                    style="flex:1;font-size:11px;padding:4px 6px;background:#242427;color:#d1d5db;border:none;border-radius:6px;cursor:pointer;font-weight:500;transition:background 0.15s;"
                                    onmouseenter="this.style.background='#6B6B73'" onmouseleave="this.style.background='#242427'"
                                    onclick="(function(btn,url){navigator.clipboard.writeText(url).then(()=>{btn.textContent='Copied!';btn.style.background='#14532d';btn.style.color='#86efac';setTimeout(()=>{btn.textContent='Copy URL';btn.style.background='#374151';btn.style.color='#d1d5db';},2000);})})(this,'{{ $file['url'] }}')"
                                >Copy URL</button>
                                <button
                                    wire:click="confirmDelete('{{ $file['path'] }}')"
                                    style="padding:4px 6px;background:#242427;color:#ffffff;border:none;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background 0.15s;"
                                    onmouseenter="this.style.background='#242427';this.style.color='#ff0000'" onmouseleave="this.style.background='#242427';this.style.color='#242427'"
                                    title="Delete"
                                >
                                    <x-heroicon-o-trash style="width:14px;height:14px;" />
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
