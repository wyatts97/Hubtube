<div class="flex items-center gap-4 text-xs">
    {{-- Failed Jobs only --}}
    @if($metrics['queue']['failed'] > 0)
        <a href="{{ url('/admin/failed-jobs') }}" class="flex items-center gap-1.5 hover:opacity-80 transition-opacity" title="{{ $metrics['queue']['failed'] }} failed jobs — click to view details">
            <x-heroicon-m-exclamation-triangle class="w-4 h-4 text-red-500" />
            <span class="px-1.5 py-0.5 rounded bg-red-500/20 text-red-600 dark:text-red-400 font-medium">
                {{ $metrics['queue']['failed'] }} failed
            </span>
        </a>
    @endif
</div>
