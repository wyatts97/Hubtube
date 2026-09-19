{{--
    Empty states. "Not indexed" matters most: without it a missing index looks
    exactly like an empty folder.
--}}
<div class="ht-ml-empty">
    @if (! $this->getDirectoryIndexedProperty())
        <x-phosphor-database class="ht-ml-icon-xl" />
        <p class="ht-ml-empty__title">This folder hasn't been indexed yet</p>
        <p class="ht-ml-empty__body">Run <code>php artisan media:index</code>, or refresh it now.</p>
        <x-filament::button wire:click="rescanCurrentDirectory" size="sm" color="gray" icon="phosphor-arrows-clockwise">
            Refresh folder
        </x-filament::button>
    @elseif ($this->hasFilters())
        <x-phosphor-magnifying-glass class="ht-ml-icon-xl" />
        <p class="ht-ml-empty__title">Nothing matches these filters</p>
        <x-filament::button wire:click="clearFilters" size="sm" color="gray">Clear filters</x-filament::button>
    @else
        <x-phosphor-folder-open class="ht-ml-icon-xl" />
        <p class="ht-ml-empty__title">This folder is empty</p>
        <p class="ht-ml-empty__body">Drop files here or use Upload to add some.</p>
    @endif
</div>
