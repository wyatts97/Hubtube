<x-filament-panels::page>
    <form wire:submit="save">
        @include('filament.partials.unsaved-banner')

        {{ $this->form }}

        <div class="mt-6 flex items-center gap-3">
            <x-filament::button type="submit" icon="heroicon-m-check">
                Save Settings
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
