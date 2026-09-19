{{--
    Admin Media Library, laid out like a file explorer.

    Styling is in resources/css/filament/admin/theme.css under .ht-ml-*.

    Multi-selection is client-side (the mediaSelection Alpine component at the
    bottom), so moving a highlight never costs a round-trip. The server hears
    about a selection only when an action runs, and every action re-validates
    the paths it is handed. Dialogs are Filament actions on the page class.
--}}
<x-filament-panels::page>

@php
    $files = $this->getFilesProperty();
    $subfolders = $this->getSubfoldersProperty();
    $selectedFileData = $this->getSelectedFileDataProperty();
    $directory = $this->directory();
    $pageFiles = collect($files->items());
    $statuses = $this->compressStatuses($pageFiles->pluck('path')->all());
    $compressing = collect($statuses)->contains(fn ($s) => in_array($s['state'] ?? '', ['queued', 'running'], true));
    $poll = $compressing || $pageFiles->contains('thumbnail_pending', true);
    $searching = $this->isSearching();
    $sortKey = $this->sortKey();
    $crumbs = $directory === '' ? [] : explode('/', $directory);
@endphp

{{-- data-ml-sizes, not an x-data argument: Alpine keeps component state across
     Livewire morphs, so an argument would go stale after the first page turn. --}}
<div
    class="ht-ml"
    x-data="mediaSelection"
    data-ml-sizes="{{ json_encode((object) $pageFiles->pluck('size', 'path')->all()) }}"
    x-on:keydown.escape="clear()"
    @if ($poll) wire:poll.5s @endif
>
    {{-- ── Address bar ─────────────────────────────────────────────────── --}}
    <div class="ht-ml-panel ht-ml-toolbar">
        <div class="ht-ml-toolbar__row">
            <button type="button" class="ht-ml-iconbtn" x-on:click="history.back()" aria-label="Back">
                <x-phosphor-arrow-left class="ht-ml-icon" />
            </button>
            <button
                type="button"
                class="ht-ml-iconbtn"
                @if ($this->parentDirectory === null) disabled @else wire:click="openDirectory(@js($this->parentDirectory))" @endif
                aria-label="Up one folder"
            >
                <x-phosphor-arrow-up class="ht-ml-icon" />
            </button>

            <nav class="ht-ml-crumbs ht-ml-address" aria-label="Current folder">
                <button type="button" class="ht-ml-crumbs__link" wire:click="openDirectory('')" @if ($directory === '') aria-current="page" @endif>
                    All files
                </button>
                @foreach ($crumbs as $index => $crumb)
                    <x-phosphor-caret-right class="ht-ml-crumbs__sep ht-ml-icon-sm" aria-hidden="true" />
                    <button
                        type="button"
                        class="ht-ml-crumbs__link"
                        wire:click="openDirectory(@js(implode('/', array_slice($crumbs, 0, $index + 1))))"
                        @if ($index === count($crumbs) - 1) aria-current="page" @endif
                    >{{ $index === 0 ? ucfirst($crumb) : $crumb }}</button>
                @endforeach
            </nav>

            <x-filament::input.wrapper class="ht-ml-search" prefix-icon="phosphor-magnifying-glass">
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Search {{ $directory === '' ? 'all files' : basename($directory) }}"
                />
            </x-filament::input.wrapper>
        </div>

        {{-- ── Command bar ─────────────────────────────────────────────── --}}
        <div class="ht-ml-toolbar__row">
            <x-filament::button size="sm" color="gray" icon="phosphor-folder-plus" wire:click="mountAction('newFolder')" :disabled="$directory === ''">
                New folder
            </x-filament::button>

            {{-- Upload, with real progress from Livewire's upload events. --}}
            <label
                @class(['ht-ml-upload', 'ht-ml-upload--disabled' => $directory === ''])
                x-data="{ progress: 0, uploading: false }"
                x-on:livewire-upload-start="uploading = true; progress = 0"
                x-on:livewire-upload-finish="uploading = false"
                x-on:livewire-upload-cancel="uploading = false"
                x-on:livewire-upload-error="uploading = false"
                x-on:livewire-upload-progress="progress = $event.detail.progress"
            >
                <input
                    type="file"
                    wire:model="uploadedFiles"
                    multiple
                    accept="{{ collect($this->allowedUploadExtensions())->map(fn ($e) => '.'.$e)->implode(',') }}"
                    class="ht-ml-upload__input"
                    @disabled($directory === '')
                />
                <x-phosphor-tray-arrow-up class="ht-ml-icon" />
                <span x-show="!uploading">Upload</span>
                <span x-show="uploading" x-cloak x-text="`Uploading ${progress}%`"></span>
            </label>

            <span class="ht-ml-toolbar__sep" aria-hidden="true"></span>

            <select wire:model.live="typeFilter" class="ht-ml-select" aria-label="Type">
                <option value="">All types</option>
                <option value="video">Videos</option>
                <option value="image">Images</option>
                <option value="audio">Audio</option>
                <option value="document">Documents</option>
                <option value="other">Other</option>
            </select>

            <select wire:model.live="minSize" class="ht-ml-select" aria-label="Size">
                <option value="">Any size</option>
                <option value="100mb">100 MB or more</option>
                <option value="1gb">1 GB or more</option>
                <option value="10gb">10 GB or more</option>
            </select>

            @if ($directory !== '')
                <label class="ht-ml-check">
                    <input type="checkbox" wire:model.live="includeSubfolders" />
                    Include subfolders
                </label>
            @endif

            @if ($this->hasFilters())
                <button type="button" class="ht-ml-chip ht-ml-chip--clear" wire:click="clearFilters">Clear filters</button>
            @endif

            <div class="ht-ml-spacer"></div>

            <div class="ht-ml-viewtoggle" role="group" aria-label="Layout">
                <button
                    type="button"
                    wire:click="setViewMode('details')"
                    @class(['ht-ml-viewtoggle__btn', 'ht-ml-viewtoggle__btn--on' => $viewMode !== 'icons'])
                    aria-pressed="{{ $viewMode !== 'icons' ? 'true' : 'false' }}"
                    aria-label="Details"
                ><x-phosphor-list class="ht-ml-icon" /></button>
                <button
                    type="button"
                    wire:click="setViewMode('icons')"
                    @class(['ht-ml-viewtoggle__btn', 'ht-ml-viewtoggle__btn--on' => $viewMode === 'icons'])
                    aria-pressed="{{ $viewMode === 'icons' ? 'true' : 'false' }}"
                    aria-label="Icons"
                ><x-phosphor-squares-four class="ht-ml-icon" /></button>
            </div>

            <x-filament::dropdown placement="bottom-end">
                <x-slot name="trigger">
                    <button type="button" class="ht-ml-iconbtn" aria-label="Refresh">
                        <x-phosphor-arrows-clockwise class="ht-ml-icon" />
                    </button>
                </x-slot>
                <x-filament::dropdown.list>
                    @if ($directory !== '')
                        <x-filament::dropdown.list.item icon="phosphor-arrows-clockwise" wire:click="rescanCurrentDirectory">
                            Refresh this folder
                        </x-filament::dropdown.list.item>
                    @endif
                    <x-filament::dropdown.list.item icon="phosphor-database" wire:click="rescanLibrary">
                        Rescan whole library
                    </x-filament::dropdown.list.item>
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        </div>

        @error('uploadedFiles.*')
            <p class="ht-ml-dropzone__error">{{ $message }}</p>
        @enderror
    </div>

    <div class="ht-ml-layout">
        {{-- ── Folder tree ─────────────────────────────────────────────── --}}
        <aside class="ht-ml-sidebar ht-ml-panel" aria-label="Folders">
            <ul class="ht-ml-tree" role="tree">
                <li role="treeitem">
                    <div @class(['ht-ml-tree__row', 'ht-ml-tree__row--current' => $directory === ''])>
                        <span class="ht-ml-tree__caret ht-ml-tree__caret--empty" aria-hidden="true"></span>
                        <button type="button" class="ht-ml-tree__label" wire:click="openDirectory('')">
                            <x-phosphor-hard-drives class="ht-ml-icon-sm" />
                            <span class="ht-ml-tree__name">All files</span>
                        </button>
                    </div>
                </li>
                @foreach ($this->getFolderTree() as $node)
                    @include('filament.pages.media-library-tree-node', ['node' => $node, 'level' => 0])
                @endforeach
            </ul>
        </aside>

        {{-- ── Contents ────────────────────────────────────────────────── --}}
        <div
            class="ht-ml-main ht-ml-panel"
            x-data="{ dragging: false }"
            @if ($directory !== '')
                x-on:dragover.prevent="dragging = true"
                x-on:dragleave.prevent="dragging = false"
                x-on:drop.prevent="dragging = false; $wire.upload('uploadedFiles', $event.dataTransfer.files)"
            @endif
            :class="dragging && 'ht-ml-main--drop'"
        >
            {{-- A new page or folder is a new set of rows: drop the selection. --}}
            <span hidden wire:key="page-{{ md5($directory.'|'.$files->currentPage().'|'.$pageFiles->pluck('path')->implode('|')) }}" x-init="clear()"></span>

            <div class="ht-ml-loading" wire:loading.delay.flex wire:target="gotoPage, nextPage, previousPage, search, typeFilter, minSize, includeSubfolders, openDirectory, sortByColumn, setViewMode, clearFilters">
                <x-filament::loading-indicator class="ht-ml-icon-lg" />
            </div>

            @if ($viewMode === 'icons')
                <div class="ht-ml-grid" role="listbox" aria-multiselectable="true" aria-label="Files">
                    @foreach ($subfolders as $folder)
                        <button
                            type="button"
                            class="ht-ml-card ht-ml-card--folder"
                            wire:key="icon-folder-{{ $folder['path'] }}"
                            wire:click="openDirectory(@js($folder['path']))"
                            title="{{ $folder['count'] }} files · {{ $folder['size_formatted'] }}"
                        >
                            <div class="ht-ml-card__thumb"><x-phosphor-folder-fill class="ht-ml-card__foldericon" /></div>
                            <div class="ht-ml-card__body">
                                <p class="ht-ml-card__name">{{ $folder['name'] }}</p>
                                <p class="ht-ml-card__meta">{{ $folder['size_formatted'] }}</p>
                            </div>
                        </button>
                    @endforeach

                    @foreach ($files as $index => $file)
                        @php $status = $statuses[$file['path']] ?? null; @endphp
                        <div
                            wire:key="icon-{{ $file['path'] }}"
                            class="ht-ml-card"
                            role="option"
                            tabindex="{{ $index === 0 ? '0' : '-1' }}"
                            :class="isSelected(@js($file['path'])) && 'ht-ml-card--selected'"
                            :aria-selected="isSelected(@js($file['path']))"
                            x-on:click="pick(@js($file['path']), $event)"
                            x-on:dblclick="preview(@js($file))"
                            x-on:keydown.space.prevent="toggle(@js($file['path']), { ctrlKey: true })"
                            x-on:keydown.enter.prevent="preview(@js($file))"
                            x-on:keydown.arrow-right.prevent="focusBy($el, 1)"
                            x-on:keydown.arrow-left.prevent="focusBy($el, -1)"
                        >
                            <div class="ht-ml-card__thumb">
                                @if ($file['thumbnail_pending'])
                                    <div class="ht-ml-card__pending"><img src="{{ $file['thumbnail'] }}" alt="" class="ht-ml-card__icon" /></div>
                                @elseif (in_array($file['type'], ['image', 'video'], true))
                                    <img src="{{ $file['thumbnail'] }}" alt="" loading="lazy" decoding="async" class="ht-ml-card__img" />
                                @else
                                    <img src="{{ $file['thumbnail'] }}" alt="" class="ht-ml-card__icon" />
                                @endif

                                @if ($file['duration'])
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
                                    @if ($status) · @include('filament.pages.partials.media-library-compress-status', ['status' => $status]) @endif
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="ht-ml-tablewrap">
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
                                @foreach (['name' => 'Name', 'modified' => 'Date modified', 'type' => 'Type', 'size' => 'Size'] as $column => $label)
                                    <th
                                        @class(['ht-ml-table__num' => $column === 'size'])
                                        aria-sort="{{ $sortKey === $column ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"
                                    >
                                        <button type="button" class="ht-ml-sort" wire:click="sortByColumn(@js($column))">
                                            {{ $label }}
                                            @if ($sortKey === $column)
                                                @if ($sortDirection === 'asc')
                                                    <x-phosphor-caret-up class="ht-ml-sort__caret" />
                                                @else
                                                    <x-phosphor-caret-down class="ht-ml-sort__caret" />
                                                @endif
                                            @endif
                                        </button>
                                    </th>
                                @endforeach
                                <th><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subfolders as $folder)
                                <tr wire:key="row-folder-{{ $folder['path'] }}" class="ht-ml-table__row">
                                    <td></td>
                                    <td>
                                        <button type="button" class="ht-ml-table__name" wire:click="openDirectory(@js($folder['path']))">
                                            <x-phosphor-folder-fill class="ht-ml-table__foldericon" />
                                            {{ $folder['name'] }}
                                        </button>
                                    </td>
                                    <td class="ht-ml-table__muted">{{ number_format($folder['count']) }} {{ \Illuminate\Support\Str::plural('file', $folder['count']) }}</td>
                                    <td class="ht-ml-table__muted">Folder</td>
                                    <td class="ht-ml-table__num ht-ml-table__size">{{ $folder['size_formatted'] }}</td>
                                    <td class="ht-ml-table__actions">
                                        @if ($directory !== '')
                                            <x-filament::dropdown placement="bottom-end">
                                                <x-slot name="trigger">
                                                    <button type="button" class="ht-ml-rowbtn" aria-label="Actions for {{ $folder['name'] }}">
                                                        <x-phosphor-dots-three class="ht-ml-icon" />
                                                    </button>
                                                </x-slot>
                                                <x-filament::dropdown.list>
                                                    <x-filament::dropdown.list.item icon="phosphor-pencil-simple" wire:click="mountAction('rename', {{ \Illuminate\Support\Js::from(['path' => $folder['path']]) }})">Rename</x-filament::dropdown.list.item>
                                                    <x-filament::dropdown.list.item icon="phosphor-trash" color="danger" wire:click="mountAction('delete', {{ \Illuminate\Support\Js::from(['paths' => [$folder['path']]]) }})">Delete</x-filament::dropdown.list.item>
                                                </x-filament::dropdown.list>
                                            </x-filament::dropdown>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach

                            @foreach ($files as $file)
                                @php $status = $statuses[$file['path']] ?? null; @endphp
                                <tr
                                    wire:key="row-{{ $file['path'] }}"
                                    class="ht-ml-table__row"
                                    :class="isSelected(@js($file['path'])) && 'ht-ml-table__row--selected'"
                                    :aria-selected="isSelected(@js($file['path']))"
                                    x-on:click="if (! $event.target.closest('input, button, a')) pick(@js($file['path']), $event)"
                                    x-on:dblclick="preview(@js($file))"
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
                                        <span class="ht-ml-table__name">
                                            <img src="{{ $file['thumbnail'] }}" alt="" class="ht-ml-table__thumb" loading="lazy" />
                                            <span class="ht-ml-table__label">
                                                {{ $file['name'] }}
                                                @if ($searching)
                                                    <span class="ht-ml-table__dir">{{ $file['directory'] }}</span>
                                                @endif
                                            </span>
                                            @if ($file['is_referenced'])
                                                <span class="ht-ml-tag" title="Used by a video or image record">In use</span>
                                            @endif
                                            @if ($status)
                                                @include('filament.pages.partials.media-library-compress-status', ['status' => $status])
                                            @endif
                                        </span>
                                    </td>
                                    <td class="ht-ml-table__muted" title="{{ $file['modified_formatted'] }}">{{ $file['modified_relative'] }}</td>
                                    <td class="ht-ml-table__muted">{{ $file['extension'] !== '' ? strtoupper($file['extension']) : ucfirst($file['type']) }}{{ $file['duration'] ? ' · '.$file['duration'] : '' }}</td>
                                    <td class="ht-ml-table__num ht-ml-table__size">{{ $file['size_formatted'] }}</td>
                                    <td class="ht-ml-table__actions">
                                        @if ($file['type'] === 'video')
                                            <button type="button" class="ht-ml-rowbtn" title="Compress…" aria-label="Compress {{ $file['name'] }}" wire:click="mountAction('compress', {{ \Illuminate\Support\Js::from(['paths' => [$file['path']]]) }})">
                                                <x-phosphor-film-strip class="ht-ml-icon" />
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($subfolders === [] && $files->isEmpty())
                @include('filament.pages.partials.media-library-empty')
            @endif

            @if ($files->hasPages())
                <div class="ht-ml-pagination">
                    <x-filament::pagination :paginator="$files" />
                </div>
            @endif

            {{-- ── Status bar ──────────────────────────────────────────── --}}
            <div class="ht-ml-statusbar">
                <span>
                    {{ number_format($files->total() + count($subfolders)) }} {{ \Illuminate\Support\Str::plural('item', $files->total() + count($subfolders)) }}
                    @if ($searching && $directory !== '')
                        in {{ basename($directory) }} and below
                    @endif
                </span>
                <span class="ht-ml-statusbar__selection" x-show="count > 0" x-cloak aria-live="polite">
                    <span x-text="`${count} selected · ${formatBytes(selectedBytes)}`"></span>
                    <x-filament::button size="xs" color="gray" icon="phosphor-film-strip" x-on:click="$wire.mountAction('compress', { paths: paths() })">Compress…</x-filament::button>
                    <x-filament::button size="xs" color="gray" icon="phosphor-folder-open" x-on:click="$wire.mountAction('move', { paths: paths() })">Move…</x-filament::button>
                    <x-filament::button size="xs" color="gray" icon="phosphor-pencil-simple" x-show="count === 1" x-on:click="$wire.mountAction('rename', { path: paths()[0] })">Rename</x-filament::button>
                    <x-filament::button size="xs" color="danger" icon="phosphor-trash" x-on:click="$wire.mountAction('delete', { paths: paths() })">Delete</x-filament::button>
                    <x-filament::button size="xs" color="gray" x-on:click="clear()">Clear</x-filament::button>
                </span>
            </div>
        </div>

        {{-- ── Details ─────────────────────────────────────────────────── --}}
        <aside class="ht-ml-details ht-ml-panel" aria-label="File details">
            @include('filament.pages.partials.media-library-details', ['selectedFileData' => $selectedFileData])
        </aside>
    </div>

    {{-- Lightbox. Alpine-local: no round-trip to open a picture. --}}
    <div x-data="{ open: false, src: null, name: '', video: false }"
         x-on:ml-preview.window="open = true; src = $event.detail.src; name = $event.detail.name; video = $event.detail.video">
        <div class="ht-ml-lightbox" x-show="open" x-cloak x-on:click.self="open = false" x-on:keydown.escape.window="open = false">
            <button type="button" class="ht-ml-lightbox__close" x-on:click="open = false" aria-label="Close preview">
                <x-phosphor-x class="ht-ml-icon-lg" />
            </button>
            <template x-if="open && !video"><img :src="src" :alt="name" class="ht-ml-lightbox__img" /></template>
            <template x-if="open && video">
                <div class="ht-ml-lightbox__video">
                    <p class="ht-ml-lightbox__name" x-text="name"></p>
                    <video :src="src" controls autoplay></video>
                </div>
            </template>
        </div>
    </div>
</div>

@script
<script>
    /**
     * Client-side multi-selection, with the conventional rules: a plain click
     * replaces the selection, Ctrl/Cmd toggles, Shift extends from the anchor.
     * A plain click also opens the details pane — the only part that needs
     * the server, because it resolves the records pointing at the file.
     */
    Alpine.data('mediaSelection', () => ({
        selected: new Set(),
        anchor: null,

        /**
         * path => bytes for this page, read live from the morphed attribute.
         * Found by selector, not $root: $root is the *nearest* x-data, and the
         * listing sits inside the drop zone's own.
         */
        get sizes() {
            try {
                return JSON.parse(document.querySelector('[data-ml-sizes]')?.dataset.mlSizes || '{}');
            } catch {
                return {};
            }
        },

        get pagePaths() {
            return Object.keys(this.sizes);
        },

        get count() {
            return this.selected.size;
        },

        get selectedBytes() {
            const sizes = this.sizes;
            let total = 0;
            this.selected.forEach((path) => { total += sizes[path] ?? 0; });
            return total;
        },

        get allPageSelected() {
            const paths = this.pagePaths;
            return paths.length > 0 && paths.every((path) => this.selected.has(path));
        },

        isSelected(path) {
            return this.selected.has(path);
        },

        paths() {
            return Array.from(this.selected);
        },

        pick(path, event) {
            this.toggle(path, event);

            if (!event.ctrlKey && !event.metaKey && !event.shiftKey) {
                this.$wire.selectFile(path);
            }
        },

        toggle(path, event = {}) {
            const paths = this.pagePaths;
            const index = paths.indexOf(path);
            const next = new Set(this.selected);

            if (event.shiftKey && this.anchor !== null && index !== -1) {
                const [from, to] = [this.anchor, index].sort((a, b) => a - b);
                paths.slice(from, to + 1).forEach((p) => next.add(p));
                this.selected = next;
                return;
            }

            if (event.ctrlKey || event.metaKey) {
                next.has(path) ? next.delete(path) : next.add(path);
                this.selected = next;
            } else {
                this.selected = new Set([path]);
            }

            this.anchor = index === -1 ? null : index;
        },

        selectPage() {
            this.selected = new Set(this.pagePaths);
        },

        clear() {
            this.selected = new Set();
            this.anchor = null;
        },

        preview(file) {
            if (file.type === 'image' || file.type === 'video') {
                this.$dispatch('ml-preview', { src: file.url, name: file.name, video: file.type === 'video' });
            }
        },

        /** Roving tabindex, so arrow keys walk the icon grid. */
        focusBy(el, delta) {
            const cards = Array.from(el.parentElement.querySelectorAll('[role="option"]'));
            const next = cards[cards.indexOf(el) + delta];

            if (!next) return;

            cards.forEach((card) => card.setAttribute('tabindex', '-1'));
            next.setAttribute('tabindex', '0');
            next.focus();
        },

        formatBytes(bytes) {
            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            let i = 0;
            while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
            return `${bytes.toFixed(i === 0 ? 0 : 2)} ${units[i]}`;
        },
    }));
</script>
@endscript

</x-filament-panels::page>
