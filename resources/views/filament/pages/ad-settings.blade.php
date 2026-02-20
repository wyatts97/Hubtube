<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-4">
            <x-filament::button type="submit">
                Save Ad Settings
            </x-filament::button>

            <a href="{{ route('filament.admin.resources.video-ads.index') }}"
               style="font-size:13px;color:#818cf8;text-decoration:underline;">
                Manage Video Ad Creatives →
            </a>
        </div>
    </form>
</x-filament-panels::page>
