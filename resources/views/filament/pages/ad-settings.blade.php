<x-filament-panels::page>
    {{-- Global Ad Settings Form --}}
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Save Ad Settings
            </x-filament::button>
        </div>
    </form>

    {{-- Video Ad Creatives Table --}}
    <div class="mt-8">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
