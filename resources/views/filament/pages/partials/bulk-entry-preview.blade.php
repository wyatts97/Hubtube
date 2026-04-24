@php
    $filePath = $filePath ?? null;
    $fileSize = $fileSize ?? 0;
    $fileName = $fileName ?? '';
    $exists = $filePath && \Illuminate\Support\Facades\Storage::disk('public')->exists($filePath);
    $url = $exists ? \Illuminate\Support\Facades\Storage::disk('public')->url($filePath) : null;
    $sizeMb = $fileSize ? number_format($fileSize / 1048576, 1) . ' MB' : '';
@endphp

<div class="w-full">
    @if ($exists)
        <div class="w-full aspect-video rounded-lg bg-black overflow-hidden relative">
            <video
                src="{{ $url }}"
                class="w-full h-full object-contain"
                preload="metadata"
                muted
                playsinline
            ></video>
            @if ($sizeMb)
                <div class="absolute bottom-2 right-2 bg-black/70 text-white text-xs px-2 py-1 rounded">
                    {{ $sizeMb }}
                </div>
            @endif
        </div>
    @else
        <div class="w-full aspect-video rounded-lg bg-gray-200 dark:bg-gray-800 flex items-center justify-center">
            <x-heroicon-o-video-camera class="w-12 h-12 text-gray-400" />
        </div>
    @endif
</div>
