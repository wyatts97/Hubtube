{{--
    Three distinct empty states.

    "Not indexed" matters most: without it a missing index looks exactly like
    an empty folder, which is the one way this page could lie to you.
--}}
<div class="ht-ml-empty">
    @if (! $this->getDirectoryIndexedProperty())
        <x-phosphor-database class="ht-ml-icon-xl" />
        <p class="ht-ml-empty__title">This folder hasn't been indexed yet</p>
        <p class="ht-ml-empty__body">Run <code>php artisan media:index</code>, or rescan it now.</p>
        <x-filament::button wire:click="rescanCurrentDirectory" size="sm" color="gray" icon="phosphor-arrows-clockwise">
            Rescan folder
        </x-filament::button>
    @elseif ($this->getHasFiltersProperty())
        <x-phosphor-magnifying-glass class="ht-ml-icon-xl" />
        <p class="ht-ml-empty__title">Nothing matches these filters</p>
        <p class="ht-ml-empty__body">
            @if ($searchScope === 'folder')
                Try searching this folder and below, or the whole library.
            @else
                Try a different search term or file type.
            @endif
        </p>
        <x-filament::button wire:click="clearFilters" size="sm" color="gray">
            Clear filters
        </x-filament::button>
    @else
        <x-phosphor-folder-open class="ht-ml-icon-xl" />
        <p class="ht-ml-empty__title">No files in this folder</p>
        <p class="ht-ml-empty__body">Drop files on the upload area above to add some.</p>
    @endif
</div>
