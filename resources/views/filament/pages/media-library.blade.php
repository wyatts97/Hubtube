<x-filament-panels::page>
<div class="space-y-6">

    {{-- Delete Confirmation Modal --}}
    @if ($deleteTarget)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
        <div style="background:#27272a;border:1px solid #3f3f46;border-radius:12px;box-shadow:0 25px 50px rgba(0,0,0,0.5);padding:24px;max-width:360px;width:100%;margin:0 16px;">
            <div class="flex flex-col items-center gap-3 text-center">
                <div class="w-10 h-10 rounded-full bg-danger-500/20 flex items-center justify-center">
                    <x-heroicon-o-trash class="w-5 h-5 text-danger-400" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-white">Delete File?</p>
                    <p class="text-xs mt-1 break-all" style="color:#a1a1aa;">{{ basename($deleteTarget) }}</p>
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
    <div style="display:flex;border-bottom:1px solid #3f3f46;">
        <button
            wire:click="$set('activeTab', 'images')"
            style="display:flex;align-items:center;gap:8px;padding:10px 16px;font-size:14px;font-weight:500;border:none;border-bottom:2px solid {{ $activeTab === 'images' ? 'var(--color-primary-500,#f43f5e)' : 'transparent' }};color:{{ $activeTab === 'images' ? 'var(--color-primary-400,#fb7185)' : '#a1a1aa' }};background:transparent;cursor:pointer;margin-bottom:-1px;transition:color 0.15s;"
        >
            <x-heroicon-o-photo style="width:16px;height:16px;" />
            Images
        </button>
        <button
            wire:click="$set('activeTab', 'videos')"
            style="display:flex;align-items:center;gap:8px;padding:10px 16px;font-size:14px;font-weight:500;border:none;border-bottom:2px solid {{ $activeTab === 'videos' ? 'var(--color-primary-500,#f43f5e)' : 'transparent' }};color:{{ $activeTab === 'videos' ? 'var(--color-primary-400,#fb7185)' : '#a1a1aa' }};background:transparent;cursor:pointer;margin-bottom:-1px;transition:color 0.15s;"
        >
            <x-heroicon-o-film style="width:16px;height:16px;" />
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
                :class="dragging ? 'border-primary-500 bg-primary-500/5' : 'border-zinc-600 hover:border-zinc-500'"
                class="relative border-2 border-dashed rounded-xl transition-colors"
            >
                <label class="flex flex-col items-center justify-center gap-2 py-6 px-4 cursor-pointer">
                    <input type="file" wire:model="uploadedImages" multiple accept="image/*"
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                    <x-heroicon-o-arrow-up-tray class="w-6 h-6 text-zinc-400" />
                    <span class="text-sm font-medium" style="color:#d4d4d8;">Drop images here or <span class="text-primary-400 underline">click to browse</span></span>
                    <span class="text-xs" style="color:#71717a;">JPG, PNG, GIF, WebP, SVG, ICO  max 10 MB</span>
                </label>
            </div>

            <div wire:loading wire:target="uploadedImages" class="mt-3 flex items-center gap-2 text-sm" style="color:#a1a1aa;">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                Processing
            </div>

            @if ($uploadedImages)
                <div class="mt-3 flex items-center gap-3" wire:loading.remove wire:target="uploadedImages">
                    <span class="text-sm" style="color:#a1a1aa;">{{ count($uploadedImages) }} file(s) ready</span>
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
                    <span style="margin-left:8px;display:inline-flex;align-items:center;padding:1px 8px;border-radius:9999px;font-size:11px;font-weight:500;background:#3f3f46;color:#d4d4d8;">{{ count($images) }}</span>
                @endif
            </x-slot>
            @if (!empty($images))
                <x-slot name="description">Click "Copy URL" to use in Ad Settings</x-slot>
            @endif

            @if (empty($images))
                <div class="flex flex-col items-center justify-center py-10 text-center gap-2">
                    <x-heroicon-o-photo class="w-8 h-8" style="color:#52525b;" />
                    <p class="text-sm" style="color:#a1a1aa;">No images yet</p>
                    <p class="text-xs" style="color:#52525b;">Upload banner images, logos, and other static assets above.</p>
                </div>
            @else
                <div class="grid gap-3" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));">
                    @foreach ($images as $file)
                    <div class="group" style="background:#18181b;border:1px solid #3f3f46;border-radius:10px;overflow:hidden;transition:border-color 0.15s;" onmouseenter="this.style.borderColor='var(--color-primary-500,#f43f5e)'" onmouseleave="this.style.borderColor='#3f3f46'">
                        <div style="height:100px;">
                            <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}"
                                 style="width:100%;height:100%;object-fit:cover;display:block;" loading="lazy">
                        </div>
                        <div style="padding:8px;">
                            <p style="font-size:11px;color:#d4d4d8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin:0 0 2px;" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
                            <p style="font-size:10px;color:#71717a;margin:0 0 6px;">{{ $file['size'] }}</p>
                            <div style="display:flex;gap:4px;">
                                <button
                                    style="flex:1;font-size:11px;padding:4px 6px;background:#27272a;color:#d4d4d8;border:none;border-radius:6px;cursor:pointer;font-weight:500;transition:background 0.15s;"
                                    onmouseenter="this.style.background='#3f3f46'" onmouseleave="this.style.background='#27272a'"
                                    onclick="(function(btn,url){navigator.clipboard.writeText(url).then(()=>{btn.textContent='Copied!';btn.style.background='#14532d';btn.style.color='#86efac';setTimeout(()=>{btn.textContent='Copy URL';btn.style.background='#27272a';btn.style.color='#d4d4d8';},2000);})})(this,'{{ $file['url'] }}')"
                                >Copy URL</button>
                                <button wire:click="confirmDelete('{{ $file['path'] }}')"
                                    style="padding:4px 6px;background:#27272a;color:#a1a1aa;border:none;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background 0.15s;"
                                    onmouseenter="this.style.background='rgba(127,29,29,0.4)';this.style.color='#f87171'" onmouseleave="this.style.background='#27272a';this.style.color='#a1a1aa'"
                                    title="Delete">
                                    <x-heroicon-o-trash style="width:13px;height:13px;" />
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
                :class="dragging ? 'border-primary-500 bg-primary-500/5' : 'border-zinc-600 hover:border-zinc-500'"
                class="relative border-2 border-dashed rounded-xl transition-colors"
            >
                <label class="flex flex-col items-center justify-center gap-2 py-6 px-4 cursor-pointer">
                    <input type="file" wire:model="uploadedVideos" multiple accept="video/mp4,video/webm,video/quicktime"
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                    <x-heroicon-o-arrow-up-tray class="w-6 h-6 text-zinc-400" />
                    <span class="text-sm font-medium" style="color:#d4d4d8;">Drop videos here or <span class="text-primary-400 underline">click to browse</span></span>
                    <span class="text-xs" style="color:#71717a;">MP4, WebM, MOV  max 200 MB</span>
                </label>
            </div>

            <div wire:loading wire:target="uploadedVideos" class="mt-3 flex items-center gap-2 text-sm" style="color:#a1a1aa;">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                Processing
            </div>

            @if ($uploadedVideos)
                <div class="mt-3 flex items-center gap-3" wire:loading.remove wire:target="uploadedVideos">
                    <span class="text-sm" style="color:#a1a1aa;">{{ count($uploadedVideos) }} file(s) ready</span>
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
                    <span style="margin-left:8px;display:inline-flex;align-items:center;padding:1px 8px;border-radius:9999px;font-size:11px;font-weight:500;background:#3f3f46;color:#d4d4d8;">{{ count($videos) }}</span>
                @endif
            </x-slot>
            @if (!empty($videos))
                <x-slot name="description">Copy the URL and paste it into an MP4 ad creative</x-slot>
            @endif

            @if (empty($videos))
                <div class="flex flex-col items-center justify-center py-10 text-center gap-2">
                    <x-heroicon-o-film class="w-8 h-8" style="color:#52525b;" />
                    <p class="text-sm" style="color:#a1a1aa;">No ad videos yet</p>
                    <p class="text-xs" style="color:#52525b;">Upload MP4 ad creatives above, then copy the URL into Appearance  Ad Creatives.</p>
                </div>
            @else
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
                    @foreach ($videos as $file)
                    <div class="group" style="background:#18181b;border:1px solid #3f3f46;border-radius:12px;display:flex;flex-direction:column;overflow:hidden;transition:border-color 0.15s;" onmouseenter="this.style.borderColor='#f43f5e'" onmouseleave="this.style.borderColor='#3f3f46'">
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
                            <p style="font-size:11px;color:#d4d4d8;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
                            <p style="font-size:10px;color:#71717a;">{{ $file['size'] }}</p>
                            <div style="display:flex;gap:4px;margin-top:4px;">
                                <button
                                    style="flex:1;font-size:11px;padding:4px 6px;background:#27272a;color:#d4d4d8;border:none;border-radius:6px;cursor:pointer;font-weight:500;transition:background 0.15s;"
                                    onmouseenter="this.style.background='#3f3f46'" onmouseleave="this.style.background='#27272a'"
                                    onclick="(function(btn,url){navigator.clipboard.writeText(url).then(()=>{btn.textContent='Copied!';btn.style.background='#14532d';btn.style.color='#86efac';setTimeout(()=>{btn.textContent='Copy URL';btn.style.background='#27272a';btn.style.color='#d4d4d8';},2000);})})(this,'{{ $file['url'] }}')"
                                >Copy URL</button>
                                <button
                                    wire:click="confirmDelete('{{ $file['path'] }}')"
                                    style="padding:4px 6px;background:#27272a;color:#a1a1aa;border:none;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background 0.15s;"
                                    onmouseenter="this.style.background='rgba(127,29,29,0.4)';this.style.color='#f87171'" onmouseleave="this.style.background='#27272a';this.style.color='#a1a1aa'"
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