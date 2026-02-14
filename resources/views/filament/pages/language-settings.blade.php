<x-filament-panels::page>
    @if($this->regenerating)
        <div wire:poll.2s="processRegeneration"></div>
    @endif

    {{-- Language Settings Form --}}
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Save Language Settings
            </x-filament::button>
        </div>
    </form>

    {{-- Translation Overrides Table (Filament) --}}
    <div class="mt-8">
        {{ $this->table }}
    </div>

    {{-- Regenerate Translations --}}
    <x-filament::section class="mt-8" heading="Generate Translations" description="Sync new keys or fully regenerate all translation files.">
        <div class="flex flex-wrap items-center gap-5">
            @if($this->regenerating)
                <x-filament::button disabled color="gray" size="sm" icon="heroicon-o-arrow-path">
                    {{ $this->regenerationStatus }}
                </x-filament::button>
            @else
                <x-filament::button wire:click="syncTranslations" color="primary" size="sm" icon="heroicon-o-arrow-path">
                    Sync New Keys &amp; Rebuild
                </x-filament::button>
                <x-filament::button wire:click="regenerateTranslations" color="warning" size="sm" icon="heroicon-o-arrow-path">
                    Full Regenerate &amp; Rebuild
                </x-filament::button>
            @endif
        </div>
        @if($this->generationOutput)
            <pre class="mt-3 p-3 rounded-lg bg-gray-900 text-green-400 text-xs font-mono max-h-48 overflow-y-auto">{{ $this->generationOutput }}</pre>
        @endif
    </x-filament::section>
</x-filament-panels::page>
