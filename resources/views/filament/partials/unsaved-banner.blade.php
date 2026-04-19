{{-- Reusable unsaved-changes toast for Filament settings pages.
     Include inside a <form wire:submit="..."> block.
     Desktop: fixed bottom-right glass toast. Mobile: centered bottom. --}}
@php($submitAction = $submitAction ?? 'save')
<div
    wire:dirty.class.remove="ht-unsaved-toast--hidden"
    wire:dirty.class="ht-unsaved-toast--visible"
    class="ht-unsaved-toast ht-unsaved-toast--hidden"
    role="status"
    aria-live="polite"
>
    <div class="ht-unsaved-toast__icon">
        <x-heroicon-m-exclamation-triangle class="w-5 h-5" />
    </div>
    <div class="ht-unsaved-toast__text">
        <span class="ht-unsaved-toast__title">Unsaved changes</span>
        <span class="ht-unsaved-toast__sub" wire:loading.remove wire:target="{{ $submitAction }}">
            You have pending edits
        </span>
        <span class="ht-unsaved-toast__sub ht-unsaved-toast__sub--saving" wire:loading.flex wire:target="{{ $submitAction }}">
            <svg class="animate-spin h-3 w-3" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <span>Saving…</span>
        </span>
    </div>
    <button
        type="submit"
        class="ht-unsaved-toast__btn"
        wire:loading.attr="disabled"
        wire:target="{{ $submitAction }}"
    >
        <x-heroicon-m-check class="w-4 h-4" />
        <span>Save Now</span>
    </button>
</div>
