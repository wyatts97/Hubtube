<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        @include('filament.partials.unsaved-banner')

        {{ $this->form }}

        <div class="flex justify-end gap-3 items-center">
            <x-filament::button type="submit" icon="heroicon-m-check">
                Save Settings
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
