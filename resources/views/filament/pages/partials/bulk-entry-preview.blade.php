@php
    $filePath = $filePath ?? null;
    $fileSize = $fileSize ?? 0;
    $fileName = $fileName ?? '';
    $exists = $filePath && \Illuminate\Support\Facades\Storage::disk('public')->exists($filePath);
    $url = $exists ? \Illuminate\Support\Facades\Storage::disk('public')->url($filePath) : null;
    $sizeMb = $fileSize ? number_format($fileSize / 1048576, 1) . ' MB' : '';
@endphp

<div class="flex flex-col items-center gap-2 w-full">
    @if ($exists)
        <div class="w-20 h-14 shrink-0 rounded bg-gray-900 overflow-hidden relative">
            <img
                src="{{ $url }}#t=0.1"
                alt=""
                class="w-full h-full object-cover"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
            >
            <div class="absolute inset-0 flex items-center justify-center bg-gray-900 hidden">
                <x-heroicon-o-video-camera class="w-5 h-5 text-gray-400" />
            </div>
            @if ($sizeMb)
                <div class="absolute bottom-0.5 right-0.5 bg-black/70 text-white text-[9px] px-0.5 py-0.5 rounded">
                    {{ $sizeMb }}
                </div>
            @endif
        </div>
    @else
        <div class="w-20 h-14 shrink-0 rounded bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
            <x-heroicon-o-video-camera class="w-5 h-5 text-gray-400" />
        </div>
    @endif
    <div class="min-w-0 max-w-full w-full">
        <p class="text-[9px] text-gray-500 dark:text-gray-400 truncate text-center leading-tight">{{ $fileName }}</p>
    </div>
</div>
