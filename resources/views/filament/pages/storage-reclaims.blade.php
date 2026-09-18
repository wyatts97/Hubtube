{{--
    Storage reclaim review.

    The two figures at the top are deliberately separate. "Reclaimed" is space
    that is actually free. "Awaiting review" is space a smaller file *would*
    free — while it waits, both copies are on disk, so the site is using more
    storage than before, not less. Presenting them as one number would be a
    lie about how much room the box has.
--}}
<x-filament-panels::page>
    @php
        $totals = $this->getTotalsProperty();
    @endphp

    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-3">
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">Reclaimed</p>
                <p class="mt-1 text-2xl font-semibold text-success-600 dark:text-success-400">
                    {{ \App\Support\Bytes::format($totals['reclaimed']) }}
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Space actually freed by accepted reclaims.</p>
            </x-filament::section>

            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">Awaiting review</p>
                <p class="mt-1 text-2xl font-semibold text-warning-600 dark:text-warning-400">
                    {{ \App\Support\Bytes::format($totals['awaiting']) }}
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ $totals['awaiting_count'] }} waiting. Not saved yet — the old file is still on disk until you accept.
                </p>
            </x-filament::section>

            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">In progress</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                    {{ $totals['running_count'] }}
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Reclaim encoding runs one at a time, niced below everything else.
                </p>
            </x-filament::section>
        </div>

        <x-filament::section
            heading="Reclaim history"
            icon="phosphor-recycle"
            description="Every reclaim keeps the file it replaced until it is accepted here. Reverting puts the old file back exactly as it was; accepting deletes it permanently, and there are no backups of media files."
        >
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
