<x-filament-panels::page
    @class([
        'fi-resource-edit-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
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
            @if($record->status === 'processed' || $record->video_path)
                <livewire:video-preview-manager :video-id="$record->id" wire:key="video-preview-{{ $record->id }}" />
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
            @capture($form)
                <x-filament-panels::form
                    id="form"
                    :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
                    wire:submit="save"
                >
                    {{ $this->form }}

                    <x-filament-panels::form.actions
                        :actions="$this->getCachedFormActions()"
                        :full-width="$this->hasFullWidthFormActions()"
                    />
                </x-filament-panels::form>
            @endcapture

            @php
                $relationManagers = $this->getRelationManagers();
                $hasCombinedRelationManagerTabsWithContent = $this->hasCombinedRelationManagerTabsWithContent();
            @endphp

            @if ((! $hasCombinedRelationManagerTabsWithContent) || (! count($relationManagers)))
                {{ $form() }}
            @endif

            @if (count($relationManagers))
                <x-filament-panels::resources.relation-managers
                    :active-locale="isset($activeLocale) ? $activeLocale : null"
                    :active-manager="$this->activeRelationManager ?? ($hasCombinedRelationManagerTabsWithContent ? null : array_key_first($relationManagers))"
                    :content-tab-label="$this->getContentTabLabel()"
                    :content-tab-icon="$this->getContentTabIcon()"
                    :content-tab-position="$this->getContentTabPosition()"
                    :managers="$relationManagers"
                    :owner-record="$record"
                    :page-class="static::class"
                >
                    @if ($hasCombinedRelationManagerTabsWithContent)
                        <x-slot name="content">
                            {{ $form() }}
                        </x-slot>
                    @endif
                </x-filament-panels::resources.relation-managers>
            @endif
        </div>
    </div>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
