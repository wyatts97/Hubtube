<x-filament-panels::page>
    <style>
        .ht-edit-grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
        @media (min-width: 1280px) {
            .ht-edit-grid { grid-template-columns: minmax(0, 7fr) minmax(0, 5fr); align-items: start; }
            .ht-edit-grid > .ht-edit-left { position: sticky; top: 1rem; }
        }
        .ht-edit-left, .ht-edit-right { min-width: 0; }
    </style>

    <div class="ht-edit-grid">
        <div class="ht-edit-left">
            @if($this->record->status === 'processed' || $this->record->video_path)
                <livewire:video-preview-manager :video-id="$this->record->id" wire:key="video-preview-{{ $this->record->id }}" />
            @else
                <div class="fi-section rounded-xl bg-gray-900 shadow-sm ring-1 ring-white/10">
                    <div class="px-6 py-8 text-center">
                        <x-heroicon-o-video-camera class="mx-auto h-12 w-12 text-gray-500" />
                        <p class="mt-2 text-sm text-gray-400">No video file available for preview yet.</p>
                    </div>
                </div>
            @endif
        </div>

        <div class="ht-edit-right">
            {{ $this->content }}
        </div>
    </div>
</x-filament-panels::page>
