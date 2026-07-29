<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Upload Zone --}}
        @if (!$isCreating)
            <x-filament::section heading="Upload Images" icon="phosphor-images"
                description="Select multiple image files to upload. After uploading, fill in the details for each image below.">
                <x-slot:afterHeader>
                    <x-filament::button wire:click="addUploadedFiles" icon="phosphor-plus-circle">
                        Add to Queue
                    </x-filament::button>
                </x-slot:afterHeader>

                <form id="bulk-upload-form" class="space-y-4">
                    {{ $this->uploadForm }}
                </form>
            </x-filament::section>

            {{-- Apply to All Bar --}}
            @if (count($entries) > 0)
                <x-filament::section heading="Apply to All" icon="phosphor-sliders-horizontal"
                    description="Defaults applied to each newly added file.">
                    <x-slot:afterHeader>
                        <x-filament::button wire:click="applyBulkSettings" icon="phosphor-check" color="gray" size="sm">
                            Apply to All Entries
                        </x-filament::button>
                    </x-slot:afterHeader>

                    <form id="bulk-apply-form" class="space-y-4">
                        {{ $this->bulkSettingsForm }}
                    </form>
                </x-filament::section>
            @endif

            {{-- Image Entries --}}
            @if (count($entries) > 0)
                <x-filament::section heading="Image Queue ({{ count($entries) }})" icon="phosphor-list-numbers">
                    <x-slot:afterHeader>
                        <x-filament::button
                            wire:click="createAllImages"
                            wire:confirm="Create {{ count($entries) }} image(s)?"
                            icon="phosphor-rocket-launch"
                            size="sm"
                        >
                            Create {{ count($entries) }} Image(s)
                        </x-filament::button>
                    </x-slot:afterHeader>

                    {{ $this->entriesForm }}
                </x-filament::section>
            @endif
        @endif

        {{-- Processing Status --}}
        @if (!empty($createdImageIds))
            <x-filament::section heading="Created Images" icon="phosphor-check-circle"
                description="Images have been created and processed.">
                <div class="ht-bulkproc">
                    @foreach ($this->createdImages as $image)
                        <div class="ht-bulkproc__row" wire:key="proc-{{ $image->id }}">
                            <div class="ht-bulkproc__info">
                                @if ($image->thumbnail_url)
                                    <img src="{{ $image->thumbnail_url }}" alt="" class="ht-bulkproc__thumb">
                                @else
                                    <div class="ht-bulkproc__thumb ht-bulkproc__thumb--empty">
                                        <x-phosphor-image />
                                    </div>
                                @endif
                                <div class="ht-bulkproc__meta">
                                    <p class="ht-bulkproc__title">{{ $image->title }}</p>
                                    <p class="ht-bulkproc__user">{{ $image->user?->username ?? '—' }}</p>
                                </div>
                            </div>
                            <div class="ht-bulkproc__status">
                                @if ($image->is_approved)
                                    <x-filament::badge color="success">Published</x-filament::badge>
                                @else
                                    <x-filament::badge color="warning">Needs Moderation</x-filament::badge>
                                @endif

                                <p class="text-sm text-gray-500 mt-1">
                                    {{ $image->width }}×{{ $image->height }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="pt-4 border-t border-gray-200 dark:border-gray-700 mt-4">
                    <x-filament::button
                        wire:click="$set('createdImageIds', []); $set('isCreating', false)"
                        icon="phosphor-plus"
                        color="gray"
                    >
                        Upload More Images
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>
