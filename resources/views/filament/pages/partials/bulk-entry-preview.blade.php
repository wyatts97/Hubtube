@php
    $filePath = $filePath ?? null;
    $fileSize = $fileSize ?? 0;
    $fileName = $fileName ?? '';
    $exists = $filePath && \Illuminate\Support\Facades\Storage::disk('public')->exists($filePath);
    $url = $exists ? \Illuminate\Support\Facades\Storage::disk('public')->url($filePath) : null;
    $sizeMb = $fileSize ? number_format($fileSize / 1048576, 1) . ' MB' : '';
@endphp

<div class="flex items-center justify-center w-full h-full">
    @if ($exists)
        <div class="w-32 h-20 shrink-0 rounded bg-gray-900 overflow-hidden relative">
            <video
                src="{{ $url }}"
                class="w-full h-full object-contain"
                preload="metadata"
                muted
                playsinline
                style="width: 300px; height: 300px; object-fit: contain;"
            ></video>
            @if ($sizeMb)
                <div class="absolute bottom-1 right-1 bg-black/70 text-white text-xs px-1.5 py-0.5 rounded">
                    {{ $sizeMb }}
                </div>
            @endif
        </div>
    @else
        <div class="w-32 h-20 shrink-0 rounded bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
            <x-heroicon-o-video-camera class="w-8 h-8 text-gray-400" />
        </div>
    @endif
</div>
