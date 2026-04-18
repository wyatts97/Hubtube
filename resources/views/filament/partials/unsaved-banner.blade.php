{{-- Reusable unsaved-changes banner for Filament settings pages.
     Include inside a <form wire:submit="..."> block.
     The default submit action is "save" but callers can pass a different one via $submitAction. --}}
@php($submitAction = $submitAction ?? 'save')
<div
    wire:dirty.class.remove="hidden"
    wire:dirty.class="flex"
    class="hidden sticky top-0 z-30 mb-4 items-center justify-between gap-3 px-4 py-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-400 text-sm font-medium backdrop-blur"
>
    <div class="flex items-center gap-2">
        <x-heroicon-o-exclamation-triangle class="w-4 h-4" />
        <span>You have unsaved changes</span>
    </div>
    <x-filament::button type="submit" size="sm" color="warning" icon="heroicon-m-check">
        Save Now
    </x-filament::button>
</div>

<span class="text-xs text-gray-500 dark:text-gray-400 inline-flex items-center gap-1.5" wire:loading wire:target="{{ $submitAction }}">
    <svg class="animate-spin h-3 w-3" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
    Saving...
</span>
