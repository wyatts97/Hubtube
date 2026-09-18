{{--
    Admin Media Library.

    Styling lives in resources/css/filament/admin/theme.css under .ht-ml-*,
    alongthe rest of this panel's component CSS. It used to be ~100 lines of
    hand-rolled utility classes and ~40 hardcoded hex values inlined in a
    <style> block shipped with every response, which also meant an admin
    changing the panel's primary colour saw every page update except this one.

    Multi-selection is client-side (the mediaSelection Alpine component at the
    bottom): every card used to carry wire:click="selectFile(...)", so moving a
    2px border cost a full server round-trip that rebuilt the entire listing.
    The server is told only when a bulk action actually runs, and it
    re-validates every path it is handed.
--}}
<x-filament-panels::page>

@php
    $files = $this->getFilesProperty();
    $tree = $this->getFolderTree();
    $subfolders = $this->getSubfoldersProperty();
    $typeCounts = $this->getTypeCountsProperty();
    // Resolved by path rather than by scanning the current page, so the panel
    // no longer goes blank as soon as you turn the page.
    $selectedFileData = $this->getSelectedFileDataProperty();
    $pagePaths = collect($files->items())->pluck('path')->all();
    $scope = $searchScope;
@endphp

<div
    class="ht-ml"
    x-data="mediaSelection(@js($pagePaths))"
    x-on:keydown.escape="clear()"
>
    {{-- Staleness banner. The index is refreshed by every action this page
         takes, but files also arrive from the encoder, imports and the shell.
         One filemtime() on the current folder is enough to notice. --}}
    @if ($this->getDirectoryStaleProperty())
        <div class="ht-ml-banner" role="status">
            <x-phosphor-warning-circle class="ht-ml-icon" />
            <span class="ht-ml-banner__text">This folder has changed on disk since it was last indexed.</span>
            <x-filament::button wire:click="rescanCurrentDirectory" size="sm" color="gray" icon="phosphor-arrows-clockwise">
                Rescan folder
            </x-filament::button>
        </div>
    @endif

    @include('filament.pages.partials.media-library-storage')

    {{-- Flat mode is a whole-library view, so it drops the folder tree and the
         details pane and gives the table the full width. --}}
    <div @class(['ht-ml-layout', 'ht-ml-layout--flat' => $viewMode === 'flat'])>

        {{-- ── Sidebar: folder tree ─────────────────────────────────────── --}}
        @if ($viewMode !== 'flat')
        <aside class="ht-ml-sidebar ht-ml-panel" aria-label="Folders">
            <p class="ht-ml-sidebar__heading">Folders</p>
            <ul class="ht-ml-tree" role="tree">
                @foreach ($tree as $node)
                    @include('filament.pages.media-library-tree-node', ['node' => $node, 'level' => 0])
                @endforeach
            </ul>
        </aside>
        @endif

        {{-- ── Main ─────────────────────────────────────────────────────── --}}
        <div class="ht-ml-main">

            {{-- Toolbar --}}
            <div class="ht-ml-panel ht-ml-toolbar">
                <div class="ht-ml-toolbar__row">
                    <nav class="ht-ml-crumbs" aria-label="Breadcrumb">
                        @php
                            $crumbs = array_values(array_filter(explode('/', trim($currentDirectory, '/'))));
                            $crumbPath = '';
                        @endphp
                        @foreach ($crumbs as $index => $crumb)
                            @php $crumbPath .= ($crumbPath ? '/' : '').$crumb; @endphp
                            @if ($index > 0)
                                <span class="ht-ml-crumbs__sep" aria-hidden="true">/</span>
                            @endif
                            <button
                                type="button"
                                class="ht-ml-crumbs__link"
                                wire:click="openDirectory(@js($crumbPath))"
                                @if ($index === count($crumbs) - 1) aria-current="page" @endif
                            >{{ ucfirst($crumb) }}</button>
                        @endforeach
                    </nav>

                    <div class="ht-ml-spacer"></div>

                    <x-filament::input.wrapper class="ht-ml-search">
                        <x-filament::input
                            type="search"
                            wire:model.live.debounce.400ms="search"
                            placeholder="Search files..."
                            x-ref="search"
                        />
                    </x-filament::input.wrapper>

                    <select wire:model.live="searchScope" class="ht-ml-select" aria-label="Search scope">
                        <option value="folder">This folder</option>
                        <option value="subtree">This folder and below</option>
                        <option value="library">Whole library</option>
                    </select>

                    <select wire:model.live="sortBy" class="ht-ml-select" aria-label="Sort by">
                        <option value="modified">Modified</option>
                        <option value="name">Name</option>
                        <option value="size">Size</option>
                        <option value="type">Type</option>
                    </select>

                    <button
                        type="button"
                        wire:click="toggleSortDirection"
                        class="ht-ml-iconbtn"
                        aria-label="{{ $sortDirection === 'asc' ? 'Sort descending' : 'Sort ascending' }}"
                    >
                        @if ($sortDirection === 'asc')
                            <x-phosphor-sort-ascending class="ht-ml-icon" />
                        @else
                            <x-phosphor-sort-descending class="ht-ml-icon" />
                        @endif
                    </button>

                    <div class="ht-ml-viewtoggle" role="group" aria-label="View mode">
                        <button
                            type="button"
                            wire:click="setViewMode('grid')"
                            @class(['ht-ml-viewtoggle__btn', 'ht-ml-viewtoggle__btn--on' => $viewMode === 'grid'])
                            aria-pressed="{{ $viewMode === 'grid' ? 'true' : 'false' }}"
                            aria-label="Grid view"
                        >
                            <x-phosphor-squares-four class="ht-ml-icon" />
                        </button>
                        <button
                            type="button"
                            wire:click="setViewMode('list')"
                            @class(['ht-ml-viewtoggle__btn', 'ht-ml-viewtoggle__btn--on' => $viewMode === 'list'])
                            aria-pressed="{{ $viewMode === 'list' ? 'true' : 'false' }}"
                            aria-label="List view"
                        >
                            <x-phosphor-list class="ht-ml-icon" />
                        </button>
                        <button
                            type="button"
                            wire:click="setViewMode('flat')"
                            @class(['ht-ml-viewtoggle__btn', 'ht-ml-viewtoggle__btn--on' => $viewMode === 'flat'])
                            aria-pressed="{{ $viewMode === 'flat' ? 'true' : 'false' }}"
                            aria-label="All files, flat"
                        >
                            <x-phosphor-rows class="ht-ml-icon" />
                        </button>
                    </div>

                    <select wire:model.live="perPage" class="ht-ml-select" aria-label="Rows per page">
                        @foreach ($this->perPageOptions() as $option)
                            <option value="{{ $option }}">{{ $option }} / page</option>
                        @endforeach
                    </select>

                    <x-filament::button wire:click="openNewFolderModal" size="sm" icon="phosphor-folder-plus">
                        New Folder
                    </x-filament::button>

                    <x-filament::button
                        wire:click="rescanLibrary"
                        wire:confirm="Rescan the whole media library? This runs in the background."
                        size="sm"
                        color="gray"
                        icon="phosphor-arrows-clockwise"
                    >
                        Rescan library
                    </x-filament::button>
                </div>

                {{-- Type chips. Counts come from one grouped query. --}}
                <div class="ht-ml-chips" role="group" aria-label="Filter by type">
                    <button
                        type="button"
                        wire:click="$set('typeFilter', '')"
                        @class(['ht-ml-chip', 'ht-ml-chip--on' => $typeFilter === ''])
                        aria-pressed="{{ $typeFilter === '' ? 'true' : 'false' }}"
                    >All {{ $files->total() > 0 || $typeFilter !== '' ? '' : '' }}</button>

                    @foreach (['image' => 'Images', 'video' => 'Videos', 'audio' => 'Audio', 'document' => 'Documents', 'other' => 'Other'] as $type => $label)
                        @if (($typeCounts[$type] ?? 0) > 0 || $typeFilter === $type)
                            <button
                                type="button"
                                wire:click="$set('typeFilter', @js($type))"
                                @class(['ht-ml-chip', 'ht-ml-chip--on' => $typeFilter === $type])
                                aria-pressed="{{ $typeFilter === $type ? 'true' : 'false' }}"
                            >{{ $label }} ({{ $typeCounts[$type] ?? 0 }})</button>
                        @endif
                    @endforeach

                    <span class="ht-ml-chips__sep" aria-hidden="true"></span>

                    <button
                        type="button"
                        wire:click="$set('usageFilter', @js($usageFilter === 'used' ? '' : 'used'))"
                        @class(['ht-ml-chip', 'ht-ml-chip--on' => $usageFilter === 'used'])
                        aria-pressed="{{ $usageFilter === 'used' ? 'true' : 'false' }}"
                    >In use</button>
                    <button
                        type="button"
                        wire:click="$set('usageFilter', @js($usageFilter === 'unused' ? '' : 'unused'))"
                        @class(['ht-ml-chip', 'ht-ml-chip--on' => $usageFilter === 'unused'])
                        aria-pressed="{{ $usageFilter === 'unused' ? 'true' : 'false' }}"
                    >Unused</button>

                    @if ($this->getHasFiltersProperty())
                        {{-- Changing folder no longer wipes the search, so say
                             what is being searched and where. --}}
                        <span class="ht-ml-chips__note">
                            @if ($search !== '')
                                Searching “{{ $search }}”
                                {{ $scope === 'library' ? 'across the library' : ($scope === 'subtree' ? 'in this folder and below' : 'in '.$currentDirectory) }}
                            @endif
                        </span>
                        <button type="button" wire:click="clearFilters" class="ht-ml-chip ht-ml-chip--clear">
                            Clear filters
                        </button>
                    @endif
                </div>

                {{-- Upload dropzone, with real progress. Livewire emits these
                     events natively; the page used to freeze with no feedback
                     at all while an upload ran. --}}
                <div
                    class="ht-ml-dropzone"
                    x-data="{ dragging: false, progress: 0, uploading: false }"
                    x-on:dragover.prevent="dragging = true"
                    x-on:dragleave.prevent="dragging = false"
                    x-on:drop.prevent="dragging = false; $wire.upload('uploadedFiles', $event.dataTransfer.files)"
                    x-on:livewire-upload-start="uploading = true; progress = 0"
                    x-on:livewire-upload-finish="uploading = false; progress = 0"
                    x-on:livewire-upload-cancel="uploading = false"
                    x-on:livewire-upload-error="uploading = false"
                    x-on:livewire-upload-progress="progress = $event.detail.progress"
                    :class="dragging && 'ht-ml-dropzone--active'"
                >
                    <label class="ht-ml-dropzone__label">
                        <input
                            type="file"
                            wire:model="uploadedFiles"
                            multiple
                            accept="{{ collect($this->allowedUploadExtensions())->map(fn ($e) => '.'.$e)->implode(',') }}"
                            class="ht-ml-dropzone__input"
                        />
                        <x-phosphor-tray-arrow-up class="ht-ml-icon" />
                        <span class="ht-ml-dropzone__text" x-show="!uploading">Drop files here or click to upload</span>
                        <span class="ht-ml-dropzone__text" x-show="uploading" x-cloak>
                            Uploading… <span x-text="progress + '%'"></span>
                        </span>
                    </label>
                    <div class="ht-ml-progress" x-show="uploading" x-cloak>
                        <div class="ht-ml-progress__bar" :style="`width: ${progress}%`"></div>
                    </div>
                    @error('uploadedFiles.*')
                        <p class="ht-ml-dropzone__error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Bulk action bar, driven entirely by client-side selection. --}}
            <div class="ht-ml-panel ht-ml-bulkbar" x-show="count > 0" x-cloak>
                <span class="ht-ml-bulkbar__count" aria-live="polite" x-text="`${count} selected`"></span>
                <x-filament::button size="xs" color="gray" icon="phosphor-selection-all" x-on:click="selectPage()">
                    Select page
                </x-filament::button>
                <x-filament::button size="xs" color="gray" icon="phosphor-folder-open" x-on:click="$wire.startMove(paths())">
                    Move to…
                </x-filament::button>
                <x-filament::button size="xs" color="danger" icon="phosphor-trash" x-on:click="$wire.deleteSelectedFiles(paths())">
                    Delete
                </x-filament::button>
                <x-filament::button size="xs" color="gray" x-on:click="clear()">
                    Clear
                </x-filament::button>
            </div>

            {{-- Subfolders. The grid was files-only, so the sidebar was the
                 only way into a folder. --}}
            @if ($subfolders !== [])
                <div class="ht-ml-folders">
                    @foreach ($subfolders as $folder)
                        <div class="ht-ml-folder" wire:key="folder-{{ $folder['path'] }}">
                            <button
                                type="button"
                                class="ht-ml-folder__open"
                                wire:click="openDirectory(@js($folder['path']))"
                            >
                                <x-phosphor-folder class="ht-ml-icon-lg" />
                                <span class="ht-ml-folder__name" title="{{ $folder['name'] }}">{{ $folder['name'] }}</span>
                                <span class="ht-ml-folder__meta">{{ $folder['count'] }} files · {{ $folder['size'] }}</span>
                            </button>

                            <x-filament::dropdown placement="bottom-end">
                                <x-slot name="trigger">
                                    <button type="button" class="ht-ml-folder__menu" aria-label="Actions for {{ $folder['name'] }}">
                                        <x-phosphor-dots-three-vertical class="ht-ml-icon" />
                                    </button>
                                </x-slot>
                                <x-filament::dropdown.list>
                                    <x-filament::dropdown.list.item icon="phosphor-pencil-simple" wire:click="startFolderRename(@js($folder['path']))">
                                        Rename
                                    </x-filament::dropdown.list.item>
                                    <x-filament::dropdown.list.item icon="phosphor-trash" color="danger" wire:click="confirmFolderDelete(@js($folder['path']))">
                                        Delete
                                    </x-filament::dropdown.list.item>
                                </x-filament::dropdown.list>
                            </x-filament::dropdown>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Thumbnails are generated by a queue worker, so the listing
                 polls while — and only while — this folder has one
                 outstanding. The condition is the off switch. --}}
            <div @if ($this->getHasPendingThumbnailsProperty()) wire:poll.5s @endif>

                {{-- Loading overlay. Delayed so a fast response does not flash. --}}
                <div class="ht-ml-loading" wire:loading.delay.flex wire:target="gotoPage, nextPage, previousPage, search, sortBy, sortDirection, searchScope, typeFilter, usageFilter, openDirectory, rescanCurrentDirectory, sortByColumn, setViewMode, perPage, showBiggest">
                    <x-filament::loading-indicator class="ht-ml-icon-lg" />
                </div>

                @if ($viewMode === 'grid')
                    <div
                        class="ht-ml-grid"
                        role="listbox"
                        aria-multiselectable="true"
                        aria-label="Files"
                        wire:key="grid-{{ $currentDirectory }}-{{ $files->currentPage() }}-{{ $sortBy }}-{{ $sortDirection }}"
                    >
                        @forelse ($files as $index => $file)
                            <div
                                wire:key="grid-{{ $file['path'] }}"
                                class="ht-ml-card"
                                role="option"
                                tabindex="{{ $index === 0 ? '0' : '-1' }}"
                                :class="isSelected(@js($file['path'])) && 'ht-ml-card--selected'"
                                :aria-selected="isSelected(@js($file['path']))"
                                x-on:click="toggle(@js($file['path']), $event); $wire.selectFile(@js($file['path']))"
                                x-on:keydown.enter.prevent="$wire.selectFile(@js($file['path']))"
                                x-on:keydown.space.prevent="toggle(@js($file['path']), { ctrlKey: true })"
                                x-on:keydown.arrow-right.prevent="focusNext($el)"
                                x-on:keydown.arrow-left.prevent="focusPrevious($el)"
                                x-on:keydown.arrow-down.prevent="focusNext($el)"
                                x-on:keydown.arrow-up.prevent="focusPrevious($el)"
                            >
                                <div class="ht-ml-card__thumb">
                                    @if ($file['thumbnail_pending'])
                                        {{-- Not an <img> pointing at a file that does not exist yet. --}}
                                        <div class="ht-ml-card__pending" title="Generating thumbnail…">
                                            <img src="{{ $file['thumbnail'] }}" alt="" class="ht-ml-card__icon" />
                                        </div>
                                    @elseif ($file['type'] === 'image' || $file['type'] === 'video')
                                        <img
                                            src="{{ $file['thumbnail'] }}"
                                            alt="{{ $file['name'] }}"
                                            loading="lazy"
                                            decoding="async"
                                            class="ht-ml-card__img"
                                        />
                                    @else
                                        <img src="{{ $file['thumbnail'] }}" alt="" class="ht-ml-card__icon" />
                                    @endif

                                    @if ($file['type'] === 'video' && $file['duration'])
                                        <span class="ht-ml-card__duration">{{ $file['duration'] }}</span>
                                    @endif

                                    @if ($file['is_referenced'])
                                        <span class="ht-ml-card__badge" title="Used by a video or image record">In use</span>
                                    @endif
                                </div>
                                <div class="ht-ml-card__body">
                                    <p class="ht-ml-card__name" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
                                    <p class="ht-ml-card__meta">
                                        {{ $file['size_formatted'] }}
                                        @if ($scope !== 'folder')
                                            · {{ $file['directory'] }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @empty
                            @include('filament.pages.partials.media-library-empty')
                        @endforelse
                    </div>
                @elseif ($viewMode === 'flat')
                    {{-- Every file in the library, sortable from the headers.
                         This is the view for "what is eating the disk": pick a
                         column, biggest or oldest first, select and act. --}}
                    <div class="ht-ml-panel ht-ml-tablewrap">
                        <table class="ht-ml-table ht-ml-flat">
                            <thead>
                                <tr>
                                    <th class="ht-ml-table__check">
                                        <input
                                            type="checkbox"
                                            aria-label="Select all files on this page"
                                            :checked="allPageSelected"
                                            x-on:change="$event.target.checked ? selectPage() : clear()"
                                        />
                                    </th>
                                    @foreach ([
                                        'name' => 'Name',
                                        'root' => 'Folder',
                                        'type' => 'Type',
                                        'size' => 'Size',
                                        'modified' => 'Age',
                                    ] as $column => $label)
                                        @php
                                            $active = $this->sortKey() === $column;
                                            $ariaSort = $active
                                                ? ($sortDirection === 'asc' ? 'ascending' : 'descending')
                                                : 'none';
                                        @endphp
                                        <th
                                            @class(['ht-ml-flat__th', 'ht-ml-table__num' => $column === 'size'])
                                            aria-sort="{{ $ariaSort }}"
                                        >
                                            <button type="button" wire:click="sortByColumn(@js($column))" class="ht-ml-flat__sort">
                                                {{ $label }}
                                                @if ($active)
                                                    @if ($sortDirection === 'asc')
                                                        <x-phosphor-caret-up class="ht-ml-flat__caret" />
                                                    @else
                                                        <x-phosphor-caret-down class="ht-ml-flat__caret" />
                                                    @endif
                                                @endif
                                            </button>
                                        </th>
                                    @endforeach
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody wire:key="flat-{{ $files->currentPage() }}-{{ $sortBy }}-{{ $sortDirection }}-{{ $typeFilter }}">
                                @forelse ($files as $file)
                                    <tr
                                        wire:key="flat-{{ $file['path'] }}"
                                        :class="isSelected(@js($file['path'])) && 'ht-ml-table__row--selected'"
                                        :aria-selected="isSelected(@js($file['path']))"
                                    >
                                        <td>
                                            <input
                                                type="checkbox"
                                                aria-label="Select {{ $file['name'] }}"
                                                :checked="isSelected(@js($file['path']))"
                                                x-on:change="toggle(@js($file['path']), { ctrlKey: true })"
                                            />
                                        </td>
                                        <td>
                                            <button type="button" class="ht-ml-table__name" wire:click="selectFile(@js($file['path']))">
                                                {{ $file['name'] }}
                                            </button>
                                            <span class="ht-ml-table__dir">{{ $file['directory'] }}</span>
                                        </td>
                                        <td class="ht-ml-table__muted">{{ $file['root'] }}</td>
                                        <td class="ht-ml-table__muted">{{ $file['type'] }}</td>
                                        <td class="ht-ml-table__num ht-ml-flat__size">{{ $file['size_formatted'] }}</td>
                                        <td class="ht-ml-table__muted" title="{{ $file['modified_formatted'] }}">
                                            {{ $file['modified_relative'] }}
                                        </td>
                                        <td>
                                            @if ($file['is_referenced'])
                                                <span class="ht-ml-card__badge">In use</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7">
                                            @include('filament.pages.partials.media-library-empty')
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="ht-ml-panel ht-ml-tablewrap">
                        <table class="ht-ml-table">
                            <thead>
                                <tr>
                                    <th class="ht-ml-table__check">
                                        <input
                                            type="checkbox"
                                            aria-label="Select all files on this page"
                                            :checked="allPageSelected"
                                            x-on:change="$event.target.checked ? selectPage() : clear()"
                                        />
                                    </th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th class="ht-ml-table__num">Size</th>
                                    <th>Modified</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody wire:key="list-{{ $currentDirectory }}-{{ $files->currentPage() }}-{{ $sortBy }}-{{ $sortDirection }}">
                                @forelse ($files as $file)
                                    <tr
                                        wire:key="row-{{ $file['path'] }}"
                                        :class="isSelected(@js($file['path'])) && 'ht-ml-table__row--selected'"
                                        :aria-selected="isSelected(@js($file['path']))"
                                    >
                                        <td>
                                            <input
                                                type="checkbox"
                                                aria-label="Select {{ $file['name'] }}"
                                                :checked="isSelected(@js($file['path']))"
                                                x-on:change="toggle(@js($file['path']), { ctrlKey: true })"
                                            />
                                        </td>
                                        <td>
                                            <button type="button" class="ht-ml-table__name" wire:click="selectFile(@js($file['path']))">
                                                {{ $file['name'] }}
                                            </button>
                                            @if ($scope !== 'folder')
                                                <span class="ht-ml-table__dir">{{ $file['directory'] }}</span>
                                            @endif
                                        </td>
                                        <td class="ht-ml-table__muted">{{ $file['type'] }}</td>
                                        <td class="ht-ml-table__num ht-ml-table__muted">{{ $file['size_formatted'] }}</td>
                                        <td class="ht-ml-table__muted">{{ $file['modified_formatted'] }}</td>
                                        <td>
                                            @if ($file['is_referenced'])
                                                <span class="ht-ml-card__badge">In use</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            @include('filament.pages.partials.media-library-empty')
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Filament's component drives Livewire's gotoPage / nextPage /
                 previousPage, so paging no longer reloads the page (which used
                 to remount the component back on page 1). --}}
            @if ($files->hasPages())
                <div class="ht-ml-panel ht-ml-pagination">
                    <x-filament::pagination :paginator="$files" />
                </div>
            @endif
        </div>

        {{-- ── Details ──────────────────────────────────────────────────── --}}
        @if ($viewMode !== 'flat')
        <aside class="ht-ml-details ht-ml-panel" aria-label="File details">
            @include('filament.pages.partials.media-library-details', ['selectedFileData' => $selectedFileData])
        </aside>
        @endif
    </div>

    {{-- ── Dialogs ──────────────────────────────────────────────────────── --}}

    <x-filament::modal id="ml-delete-file" :visible="(bool) $deleteTarget" width="md" alignment="center" icon="phosphor-trash" icon-color="danger">
        <x-slot name="heading">Delete file?</x-slot>
        <x-slot name="description">{{ $deleteTarget ? basename($deleteTarget) : '' }}</x-slot>
        <x-slot name="footerActions">
            <x-filament::button wire:click="cancelDelete" color="gray">Cancel</x-filament::button>
            <x-filament::button wire:click="deleteFile" color="danger">Delete</x-filament::button>
        </x-slot>
    </x-filament::modal>

    <x-filament::modal id="ml-rename-file" :visible="(bool) $renameTarget" width="md" alignment="center">
        <x-slot name="heading">Rename file</x-slot>
        <x-filament::input.wrapper>
            <x-filament::input type="text" wire:model="renameNewName" wire:keydown.enter="confirmRename" autofocus />
        </x-filament::input.wrapper>
        <x-slot name="footerActions">
            <x-filament::button wire:click="cancelRename" color="gray">Cancel</x-filament::button>
            <x-filament::button wire:click="confirmRename">Rename</x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- Its visibility is its own flag: it used to render under
         @if ($newFolderName), so clearing the pre-filled name closed it. --}}
    <x-filament::modal id="ml-new-folder" :visible="$showNewFolderModal" width="md" alignment="center">
        <x-slot name="heading">New folder</x-slot>
        <x-filament::input.wrapper>
            <x-filament::input type="text" wire:model="newFolderName" wire:keydown.enter="createFolder" placeholder="Folder name" autofocus />
        </x-filament::input.wrapper>
        <x-slot name="footerActions">
            <x-filament::button wire:click="closeNewFolderModal" color="gray">Cancel</x-filament::button>
            <x-filament::button wire:click="createFolder">Create</x-filament::button>
        </x-slot>
    </x-filament::modal>

    <x-filament::modal id="ml-rename-folder" :visible="(bool) $folderRenameTarget" width="md" alignment="center">
        <x-slot name="heading">Rename folder</x-slot>
        <x-slot name="description">
            Every video and image record pointing inside this folder is updated to follow it.
        </x-slot>
        <x-filament::input.wrapper>
            <x-filament::input type="text" wire:model="folderRenameNewName" wire:keydown.enter="confirmFolderRename" autofocus />
        </x-filament::input.wrapper>
        <x-slot name="footerActions">
            <x-filament::button wire:click="cancelFolderRename" color="gray">Cancel</x-filament::button>
            <x-filament::button wire:click="confirmFolderRename">Rename</x-filament::button>
        </x-slot>
    </x-filament::modal>

    <x-filament::modal id="ml-delete-folder" :visible="(bool) $folderDeleteTarget" width="md" alignment="center" icon="phosphor-trash" icon-color="danger">
        <x-slot name="heading">Delete folder?</x-slot>
        <x-slot name="description">
            {{ $folderDeleteTarget }} and everything in it. A folder containing files still used by a video or image record cannot be deleted.
        </x-slot>
        <x-slot name="footerActions">
            <x-filament::button wire:click="cancelFolderDelete" color="gray">Cancel</x-filament::button>
            <x-filament::button wire:click="confirmFolderDeletion" color="danger">Delete</x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- A folder picker rather than drag-and-drop: it works from the keyboard,
         it works for a bulk selection, and it needs no drag library fighting
         Livewire's DOM morphing. --}}
    <x-filament::modal id="ml-move" :visible="$showMoveModal" width="md" alignment="center">
        <x-slot name="heading">Move {{ count($moveTargets) }} file{{ count($moveTargets) === 1 ? '' : 's' }}</x-slot>
        <select wire:model="moveDestination" class="ht-ml-select ht-ml-select--block" aria-label="Destination folder" size="10">
            <option value="">Choose a destination…</option>
            @foreach ($this->getMoveDestinationsProperty() as $destination)
                <option value="{{ $destination }}">{{ $destination }}</option>
            @endforeach
        </select>
        <x-slot name="footerActions">
            <x-filament::button wire:click="cancelMove" color="gray">Cancel</x-filament::button>
            <x-filament::button wire:click="confirmMove">Move</x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- Lightbox and video preview. Alpine-local: no server round-trip to open
         a picture. --}}
    <div x-data="{ open: false, src: null, name: '', video: false }"
         x-on:ml-preview.window="open = true; src = $event.detail.src; name = $event.detail.name; video = $event.detail.video">
        <div class="ht-ml-lightbox" x-show="open" x-cloak x-on:click.self="open = false" x-on:keydown.escape.window="open = false">
            <button type="button" class="ht-ml-lightbox__close" x-on:click="open = false" aria-label="Close preview">
                <x-phosphor-x class="ht-ml-icon-lg" />
            </button>
            <template x-if="!video">
                <img :src="src" :alt="name" class="ht-ml-lightbox__img" />
            </template>
            <template x-if="video">
                <div class="ht-ml-lightbox__video">
                    <p class="ht-ml-lightbox__name" x-text="name"></p>
                    <video :src="src" controls></video>
                </div>
            </template>
        </div>
    </div>
</div>

@script
<script>
    /**
     * Client-side multi-selection for the file grid and list.
     *
     * Every card used to carry wire:click="selectFile(...)", which meant a full
     * server round-trip — rebuilding the entire listing — just to move a 2px
     * border. Selection is a rendering concern, so it lives here; the server is
     * told only when a bulk action runs, and it re-validates every path it is
     * handed (see MediaLibrary::resolveBulkPaths).
     *
     * The click semantics are the conventional ones. The old server-side
     * version *toggled* on a plain click, so clicking a second file added it to
     * the selection instead of replacing it.
     */
    Alpine.data('mediaSelection', (pagePaths = []) => ({
        selected: new Set(),
        pagePaths,
        anchor: null,

        get count() {
            return this.selected.size;
        },

        get allPageSelected() {
            return this.pagePaths.length > 0
                && this.pagePaths.every((path) => this.selected.has(path));
        },

        isSelected(path) {
            return this.selected.has(path);
        },

        paths() {
            return Array.from(this.selected);
        },

        /**
         * Plain click replaces, Ctrl/Cmd toggles, Shift extends from the
         * anchor — the same rules as every file manager.
         */
        toggle(path, event = {}) {
            const index = this.pagePaths.indexOf(path);

            if (event.shiftKey && this.anchor !== null && index !== -1) {
                const [from, to] = [this.anchor, index].sort((a, b) => a - b);
                this.pagePaths.slice(from, to + 1).forEach((p) => this.selected.add(p));
                return;
            }

            if (event.ctrlKey || event.metaKey) {
                this.selected.has(path) ? this.selected.delete(path) : this.selected.add(path);
            } else {
                this.selected = new Set([path]);
            }

            this.anchor = index === -1 ? null : index;
        },

        selectPage() {
            this.pagePaths.forEach((path) => this.selected.add(path));
        },

        clear() {
            this.selected = new Set();
            this.anchor = null;
        },

        /** Roving tabindex, so arrow keys walk the grid. */
        focusNext(el) {
            this.moveFocus(el, 1);
        },

        focusPrevious(el) {
            this.moveFocus(el, -1);
        },

        moveFocus(el, delta) {
            const cards = Array.from(el.parentElement.querySelectorAll('[role="option"]'));
            const next = cards[cards.indexOf(el) + delta];

            if (!next) return;

            cards.forEach((card) => card.setAttribute('tabindex', '-1'));
            next.setAttribute('tabindex', '0');
            next.focus();
        },
    }));
</script>
@endscript

</x-filament-panels::page>
