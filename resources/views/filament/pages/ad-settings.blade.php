<x-filament-panels::page>
    <form wire:submit="save">
        @include('filament.partials.unsaved-banner')

        {{ $this->form }}

        <div class="mt-6 flex items-center gap-4">
            <x-filament::button type="submit" icon="phosphor-check">
                Save Ad Settings
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ route('filament.admin.resources.video-ads.index') }}"
                color="gray"
                icon="phosphor-film-strip">
                Manage Video Ad Creatives
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
