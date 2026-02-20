<x-filament-panels::page>
<div class="space-y-6">

    {{-- Delete Confirmation Modal --}}
    @if ($deleteTarget)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
        <div class="bg-gray-800 border border-gray-700 rounded-xl shadow-2xl p-7 max-w-sm w-full text-center">
            <h3 class="text-base font-semibold text-gray-100 mb-2">Delete File?</h3>
            <p class="text-sm text-gray-400 mb-5 break-all">{{ basename($deleteTarget) }}</p>
            <div class="flex gap-3 justify-center">
                <x-filament::button wire:click="cancelDelete" color="gray" size="sm">Cancel</x-filament::button>
                <x-filament::button wire:click="deleteFile" color="danger" size="sm">Delete</x-filament::button>
            </div>
        </div>
    </div>
    @endif

    {{-- Tab Bar --}}
    <div class="flex gap-1 border-b border-gray-700">
        <button
            wire:click="$set('activeTab', 'images')"
            class="px-5 py-2.5 text-sm font-medium transition-colors border-b-2 -mb-px
                {{ $activeTab === 'images'
                    ? 'text-white border-primary-500'
                    : 'text-gray-400 border-transparent hover:text-gray-200' }}"
        >
            🖼&nbsp; Images
        </button>
        <button
            wire:click="$set('activeTab', 'videos')"
            class="px-5 py-2.5 text-sm font-medium transition-colors border-b-2 -mb-px
                {{ $activeTab === 'videos'
                    ? 'text-white border-primary-500'
                    : 'text-gray-400 border-transparent hover:text-gray-200' }}"
        >
            🎬&nbsp; Ad Videos
        </button>
    </div>

    {{-- ── IMAGES TAB ── --}}
    @if ($activeTab === 'images')
        {{-- Upload Zone --}}
        <div class="border-2 border-dashed border-gray-600 rounded-xl bg-gray-900 hover:border-primary-500 transition-colors">
            <label class="flex flex-col items-center justify-center gap-2 p-8 cursor-pointer text-center">
                <input type="file" wire:model="uploadedImages" multiple accept="image/*" class="hidden">
                <span class="text-3xl">📁</span>
                <span class="text-sm font-medium text-gray-200">Drop images here or click to browse</span>
                <span class="text-xs text-gray-500">JPG, PNG, GIF, WebP, SVG, ICO — max 10 MB each</span>
            </label>
            @if ($uploadedImages)
                <div class="px-8 pb-5 flex items-center gap-3" wire:loading.remove wire:target="uploadImages">
                    <span class="text-sm text-gray-400">{{ count($uploadedImages) }} file(s) selected</span>
                    <x-filament::button wire:click="uploadImages" size="sm">Upload Now</x-filament::button>
                </div>
                <div class="px-8 pb-5 text-sm text-gray-400" wire:loading wire:target="uploadImages">
                    Uploading…
                </div>
            @endif
        </div>

        @php $images = $this->getImageFiles(); @endphp

        @if (empty($images))
            <div class="bg-gray-900 rounded-xl p-12 text-center">
                <p class="text-3xl mb-3">🖼</p>
                <p class="text-sm font-medium text-gray-300 mb-1">No images yet</p>
                <p class="text-xs text-gray-500">Upload banner images, logos, and other static assets here.</p>
            </div>
        @else
            <p class="text-xs text-gray-500">{{ count($images) }} file(s) — click "Copy URL" to use in Ad Settings</p>
            <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));">
                @foreach ($images as $file)
                <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden hover:border-primary-500 transition-colors">
                    <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}"
                         class="w-full object-cover bg-gray-900" style="height:110px;" loading="lazy">
                    <div class="p-2.5">
                        <p class="text-xs text-gray-300 truncate mb-0.5" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
                        <p class="text-[10px] text-gray-500 mb-2">{{ $file['size'] }}</p>
                        <div class="flex gap-1.5">
                            <button
                                class="flex-1 text-[11px] px-2 py-1 bg-gray-700 text-gray-300 rounded-md hover:bg-gray-600 transition-colors copy-url-btn"
                                onclick="(function(btn,url){navigator.clipboard.writeText(url).then(()=>{btn.textContent='Copied!';btn.style.background='#065f46';btn.style.color='#6ee7b7';setTimeout(()=>{btn.textContent='Copy URL';btn.style.background='';btn.style.color='';},2000);})})(this,'{{ $file['url'] }}')"
                            >Copy URL</button>
                            <button
                                wire:click="confirmDelete('{{ $file['path'] }}')"
                                class="text-[11px] px-2 py-1 bg-red-900/60 text-red-300 rounded-md hover:bg-red-800 transition-colors"
                            >✕</button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- ── AD VIDEOS TAB ── --}}
    @if ($activeTab === 'videos')
        {{-- Upload Zone --}}
        <div class="border-2 border-dashed border-gray-600 rounded-xl bg-gray-900 hover:border-primary-500 transition-colors">
            <label class="flex flex-col items-center justify-center gap-2 p-8 cursor-pointer text-center">
                <input type="file" wire:model="uploadedVideos" multiple accept="video/mp4,video/webm,video/quicktime" class="hidden">
                <span class="text-3xl">🎬</span>
                <span class="text-sm font-medium text-gray-200">Drop ad videos here or click to browse</span>
                <span class="text-xs text-gray-500">MP4, WebM, MOV — max 200 MB each</span>
            </label>
            @if ($uploadedVideos)
                <div class="px-8 pb-5 flex items-center gap-3" wire:loading.remove wire:target="uploadVideos">
                    <span class="text-sm text-gray-400">{{ count($uploadedVideos) }} file(s) selected</span>
                    <x-filament::button wire:click="uploadVideos" size="sm">Upload Now</x-filament::button>
                </div>
                <div class="px-8 pb-5 text-sm text-gray-400" wire:loading wire:target="uploadVideos">
                    Uploading…
                </div>
            @endif
        </div>

        @php $videos = $this->getVideoFiles(); @endphp

        @if (empty($videos))
            <div class="bg-gray-900 rounded-xl p-12 text-center">
                <p class="text-3xl mb-3">🎬</p>
                <p class="text-sm font-medium text-gray-300 mb-1">No ad videos yet</p>
                <p class="text-xs text-gray-500">Upload MP4 ad creatives here, then copy the URL into Appearance → Ad Creatives.</p>
            </div>
        @else
            <p class="text-xs text-gray-500">{{ count($videos) }} file(s) — copy the URL and paste it into an MP4 ad creative</p>
            <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));">
                @foreach ($videos as $file)
                <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden hover:border-primary-500 transition-colors">
                    <video src="{{ $file['url'] }}" class="w-full bg-gray-900" style="height:110px;object-fit:cover;" muted preload="metadata"></video>
                    <div class="p-2.5">
                        <p class="text-xs text-gray-300 truncate mb-0.5" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
                        <p class="text-[10px] text-gray-500 mb-2">{{ $file['size'] }}</p>
                        <div class="flex gap-1.5">
                            <button
                                class="flex-1 text-[11px] px-2 py-1 bg-gray-700 text-gray-300 rounded-md hover:bg-gray-600 transition-colors"
                                onclick="(function(btn,url){navigator.clipboard.writeText(url).then(()=>{btn.textContent='Copied!';btn.style.background='#065f46';btn.style.color='#6ee7b7';setTimeout(()=>{btn.textContent='Copy URL';btn.style.background='';btn.style.color='';},2000);})})(this,'{{ $file['url'] }}')"
                            >Copy URL</button>
                            <button
                                wire:click="confirmDelete('{{ $file['path'] }}')"
                                class="text-[11px] px-2 py-1 bg-red-900/60 text-red-300 rounded-md hover:bg-red-800 transition-colors"
                            >✕</button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    @endif

</div>
</x-filament-panels::page>
