<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-4">
            <x-filament::button type="submit">
                Save Ad Settings
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ route('filament.admin.resources.video-ads.index') }}"
                color="gray"
                icon="heroicon-o-film">
                Manage Video Ad Creatives
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
