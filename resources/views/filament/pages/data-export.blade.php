<x-filament-panels::page>
    <form wire:submit="export">
        @include('filament.partials.unsaved-banner')

        {{ $this->form }}

        <div class="mt-6 flex items-center gap-3">
            <x-filament::button type="submit" icon="heroicon-m-arrow-down-tray">
                Export Data
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
