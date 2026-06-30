<x-filament-panels::page>
<style>
    .ht-layout { display: flex; gap: 16px; min-height: calc(100vh - 12rem); }
    .ht-sidebar { width: 260px; flex-shrink: 0; }
    .ht-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 16px; }
    .ht-details { width: 300px; flex-shrink: 0; }
    .ht-flex { display: flex; }
    .ht-flex-col { display: flex; flex-direction: column; }
    .ht-flex-wrap { display: flex; flex-wrap: wrap; }
    .ht-items-center { align-items: center; }
    .ht-justify-center { justify-content: center; }
    .ht-justify-between { justify-content: space-between; }
    .ht-gap-1 { gap: 4px; }
    .ht-gap-2 { gap: 8px; }
    .ht-gap-3 { gap: 12px; }
    .ht-gap-4 { gap: 16px; }
    .ht-flex-1 { flex: 1; }
    .ht-min-w-0 { min-width: 0; }
    .ht-w-full { width: 100%; }
    .ht-h-full { height: 100%; }
    .ht-text-left { text-align: left; }
    .ht-text-center { text-align: center; }
    .ht-text-xs { font-size: 12px; }
    .ht-text-sm { font-size: 14px; }
    .ht-text-10 { font-size: 10px; }
    .ht-font-medium { font-weight: 500; }
    .ht-font-semibold { font-weight: 600; }
    .ht-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ht-rounded { border-radius: 6px; }
    .ht-rounded-lg { border-radius: 8px; }
    .ht-rounded-xl { border-radius: 12px; }
    .ht-rounded-full { border-radius: 9999px; }
    .ht-p-2 { padding: 8px; }
    .ht-px-3 { padding-left: 12px; padding-right: 12px; }
    .ht-px-4 { padding-left: 16px; padding-right: 16px; }
    .ht-py-2 { padding-top: 8px; padding-bottom: 8px; }
    .ht-py-4 { padding-top: 16px; padding-bottom: 16px; }
    .ht-py-12 { padding-top: 48px; padding-bottom: 48px; }
    .ht-py-16 { padding-top: 64px; padding-bottom: 64px; }
    .ht-mb-2 { margin-bottom: 8px; }
    .ht-mb-3 { margin-bottom: 12px; }
    .ht-mb-4 { margin-bottom: 16px; }
    .ht-mt-1 { margin-top: 4px; }
    .ht-mt-2 { margin-top: 8px; }
    .ht-mt-3 { margin-top: 12px; }
    .ht-ml-auto { margin-left: auto; }
    .ht-object-cover { object-fit: cover; }
    .ht-cursor-pointer { cursor: pointer; }
    .ht-relative { position: relative; }
    .ht-absolute { position: absolute; }
    .ht-inset-0 { inset: 0; }
    .ht-top-4 { top: 16px; }
    .ht-right-4 { right: 16px; }
    .ht-pointer-events-none { pointer-events: none; }
    .ht-overflow-hidden { overflow: hidden; }
    .ht-opacity-60 { opacity: 0.6; }
    .ht-opacity-80 { opacity: 0.8; }
    .ht-break-all { word-break: break-all; }
    .ht-grid { display: grid; }
    .ht-file-grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
    .ht-file-card { background: #18181b; border-radius: 12px; overflow: hidden; cursor: pointer; transition: border-color 0.15s; }
    .ht-file-card-thumb { height: 130px; background: #000; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
    .ht-empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 64px 0; text-align: center; gap: 8px; grid-column: 1 / -1; }
    .ht-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
    .ht-table th, .ht-table td { padding: 8px 16px; }
    .ht-table thead { background: #27272a; }
    .ht-table tbody tr { border-top: 1px solid #3f3f46; }
    .ht-table .ht-selected { background: rgba(244, 63, 94, 0.08); }
    .ht-badge { font-size: 10px; padding: 2px 6px; border-radius: 9999px; background: #7f1d1d; color: #fca5a5; }
    .ht-btn-icon { width: 16px; height: 16px; }
    .ht-btn-icon-sm { width: 20px; height: 20px; }
    .ht-btn-icon-md { width: 24px; height: 24px; }
    .ht-btn-icon-lg { width: 32px; height: 32px; }
    .ht-btn-icon-xl { width: 40px; height: 40px; }
    .ht-btn-icon-2xl { width: 64px; height: 64px; }
    .ht-modal-overlay { position: fixed; inset: 0; z-index: 50; display: flex; align-items: center; justify-content: center; background: rgba(0, 0, 0, 0.7); }
    .ht-modal-box { background: #27272a; border: 1px solid #3f3f46; border-radius: 12px; box-shadow: 0 25px 50px rgba(0,0,0,0.5); padding: 24px; max-width: 420px; width: 100%; margin: 0 16px; }
    .ht-lightbox { position: fixed; inset: 0; z-index: 50; display: flex; align-items: center; justify-content: center; background: rgba(0, 0, 0, 0.9); }
    .ht-lightbox-img { max-width: 90vw; max-height: 90vh; object-fit: contain; border-radius: 8px; }
    .ht-link-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 8px 12px; border-radius: 8px; font-size: 14px; font-weight: 500; }
    .ht-panel { background: #18181b; border: 1px solid #3f3f46; border-radius: 12px; }
    .ht-panel-dark { background: #27272a; border: 1px solid #3f3f46; border-radius: 8px; }
    .ht-input { background: #27272a; color: #d4d4d8; border: none; border-radius: 8px; padding: 8px 12px; font-size: 14px; }
    .ht-input:focus { outline: 2px solid var(--color-primary-500); }
    .ht-select { background: #27272a; color: #d4d4d8; border: none; border-radius: 8px; padding: 8px 12px; font-size: 14px; }
    .ht-toggle-btn { padding: 8px; border-radius: 6px; }
    .ht-view-toggle { display: flex; border-radius: 6px; overflow: hidden; background: #27272a; }
    .ht-view-toggle button { padding: 8px; }
    .ht-upload-zone { margin-top: 12px; position: relative; border: 2px dashed #3f3f46; border-radius: 8px; transition: border-color 0.15s, background 0.15s; cursor: pointer; }
    .ht-upload-zone:hover { border-color: #52525b; }
    .ht-upload-zone-active { border-color: var(--color-primary-500) !important; background: rgba(244, 63, 94, 0.1) !important; }
    .ht-folder-row { display: flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 6px; cursor: pointer; }
    .ht-folder-row:hover { background: rgba(255, 255, 255, 0.04); }
    .ht-folder-row-active { background: rgba(244, 63, 94, 0.12); }
    .ht-details-preview { height: 160px; background: #000; border-radius: 8px; overflow: hidden; position: relative; }
    @media (max-width: 1024px) {
        .ht-layout { flex-direction: column; }
        .ht-sidebar, .ht-details { width: 100%; max-height: none; }
    }
    @media (max-width: 640px) {
        .ht-file-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
    }
</style>

<div x-data="{
    lightboxOpen: false,
    lightboxSrc: null,
    videoOpen: false,
    videoSrc: null,
    videoName: ''
}" style="display:flex;flex-direction:column;gap:16px;" @keydown.escape.window="lightboxOpen = false; videoOpen = false; $wire.clearSelection()">

    @php
        $files = $this->getFilesProperty();
        $tree = $this->getFolderTree();
        $selectedFileData = null;
        if ($selectedFile) {
            foreach ($files->items() as $item) {
                if ($item['path'] === $selectedFile) {
                    $selectedFileData = $item;
                    break;
                }
            }
        }
    @endphp

    {{-- Delete confirmation modal --}}
    @if ($deleteTarget)
    <div class="ht-modal-overlay">
        <div class="ht-modal-box">
            <div class="ht-flex-col ht-items-center ht-gap-3 ht-text-center">
                <div class="ht-flex ht-items-center ht-justify-center" style="width:40px;height:40px;border-radius:9999px;background:rgba(244,63,94,0.2);">
                    <x-phosphor-trash class="ht-btn-icon-sm" style="color:#f87171;" />
                </div>
                <div>
                    <p class="ht-text-sm ht-font-semibold" style="color:#fff;">Delete File?</p>
                    <p class="ht-text-xs ht-mt-1 ht-break-all" style="color:#a1a1aa;">{{ basename($deleteTarget) }}</p>
                </div>
                <div class="ht-flex ht-gap-3 ht-w-full">
                    <x-filament::button wire:click="cancelDelete" color="gray" size="sm" style="flex:1;">Cancel</x-filament::button>
                    <x-filament::button wire:click="deleteFile" color="danger" size="sm" style="flex:1;">Delete</x-filament::button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Rename modal --}}
    @if ($renameTarget)
    <div class="ht-modal-overlay">
        <div class="ht-modal-box">
            <p class="ht-text-sm ht-font-semibold ht-mb-3" style="color:#fff;">Rename File</p>
            <x-filament::input.wrapper style="margin-bottom:16px;">
                <x-filament::input type="text" wire:model="renameNewName" />
            </x-filament::input.wrapper>
            <div class="ht-flex ht-gap-3 ht-w-full">
                <x-filament::button wire:click="cancelRename" color="gray" size="sm" style="flex:1;">Cancel</x-filament::button>
                <x-filament::button wire:click="confirmRename" color="primary" size="sm" style="flex:1;">Rename</x-filament::button>
            </div>
        </div>
    </div>
    @endif

    {{-- New folder modal --}}
    @if ($newFolderName)
    <div class="ht-modal-overlay">
        <div class="ht-modal-box">
            <p class="ht-text-sm ht-font-semibold ht-mb-3" style="color:#fff;">New Folder</p>
            <x-filament::input.wrapper style="margin-bottom:16px;">
                <x-filament::input type="text" wire:model="newFolderName" placeholder="Folder name" />
            </x-filament::input.wrapper>
            <div class="ht-flex ht-gap-3 ht-w-full">
                <x-filament::button wire:click="$set('newFolderName', '')" color="gray" size="sm" style="flex:1;">Cancel</x-filament::button>
                <x-filament::button wire:click="createFolder" color="primary" size="sm" style="flex:1;">Create</x-filament::button>
            </div>
        </div>
    </div>
    @endif

    {{-- Lightbox --}}
    <div x-show="lightboxOpen" class="ht-lightbox" style="display:none;" x-on:click.self="lightboxOpen = false">
        <button x-on:click="lightboxOpen = false" class="ht-absolute" style="top:16px;right:16px;color:#fff;padding:8px;">
            <x-phosphor-x class="ht-btn-icon-md" />
        </button>
        <img :src="lightboxSrc" class="ht-lightbox-img" />
    </div>

    {{-- Video preview modal --}}
    <div x-show="videoOpen" class="ht-lightbox" style="display:none;" x-on:click.self="videoOpen = false">
        <button x-on:click="videoOpen = false" class="ht-absolute" style="top:16px;right:16px;color:#fff;padding:8px;">
            <x-phosphor-x class="ht-btn-icon-md" />
        </button>
        <div style="width:100%;max-width:56rem;padding:16px;">
            <p x-text="videoName" class="ht-text-sm ht-mb-2 ht-truncate" style="color:#fff;"></p>
            <video :src="videoSrc" controls style="width:100%;max-height:80vh;border-radius:8px;background:#000;"></video>
        </div>
    </div>

    {{-- Main layout: sidebar | content | details --}}
    <div class="ht-layout">

        {{-- Sidebar: folder tree --}}
        <div class="ht-sidebar ht-panel" style="overflow-y:auto;max-height:calc(100vh - 10rem);padding:12px;">
            <p class="ht-text-xs ht-font-semibold ht-mb-2" style="color:#a1a1aa;text-transform:uppercase;letter-spacing:0.05em;">Folders</p>
            <ul style="display:flex;flex-direction:column;gap:4px;list-style:none;margin:0;padding:0;">
                @foreach ($tree as $node)
                    @include('filament.pages.media-library-tree-node', ['node' => $node, 'level' => 0])
                @endforeach
            </ul>
        </div>

        {{-- Main content --}}
        <div class="ht-main">

            {{-- Toolbar --}}
            <div class="ht-panel" style="padding:12px 16px;">
                <div class="ht-flex-wrap ht-items-center ht-gap-3">
                    {{-- Breadcrumbs --}}
                    <div class="ht-flex ht-items-center ht-gap-1 ht-text-sm" style="color:#a1a1aa;">
                        <button wire:click="$set('currentDirectory', 'media')" style="color:#d4d4d8;background:none;border:none;cursor:pointer;">Media</button>
                        @php
                            $crumbs = explode('/', trim($currentDirectory, '/'));
                            $crumbPath = '';
                        @endphp
                        @foreach ($crumbs as $crumb)
                            @php $crumbPath .= ($crumbPath ? '/' : '') . $crumb; @endphp
                            <span class="ht-text-xs">/</span>
                            <button wire:click="$set('currentDirectory', '{{ $crumbPath }}')" style="color:#d4d4d8;background:none;border:none;cursor:pointer;">{{ ucfirst($crumb) }}</button>
                        @endforeach
                    </div>

                    <div class="ht-flex-1"></div>

                    {{-- Search --}}
                    <x-filament::input.wrapper style="width:200px;">
                        <x-filament::input type="text" wire:model.live.debounce.300ms="search" placeholder="Search files..." />
                    </x-filament::input.wrapper>

                    {{-- Sort --}}
                    <select wire:model.live="sortBy" class="ht-select" style="width:140px;">
                        <option value="modified">Modified</option>
                        <option value="name">Name</option>
                        <option value="size">Size</option>
                        <option value="type">Type</option>
                    </select>

                    <button wire:click="toggleSortDirection" class="ht-toggle-btn" style="background:#27272a;color:#d4d4d8;">
                        @if ($sortDirection === 'asc')
                            <x-phosphor-sort-ascending class="ht-btn-icon" />
                        @else
                            <x-phosphor-sort-descending class="ht-btn-icon" />
                        @endif
                    </button>

                    {{-- View toggle --}}
                    <div class="ht-view-toggle">
                        <button wire:click="$set('viewMode', 'grid')" style="background:{{ $viewMode === 'grid' ? 'var(--color-primary-500)' : 'transparent' }};color:{{ $viewMode === 'grid' ? '#fff' : '#a1a1aa' }};border:none;cursor:pointer;">
                            <x-phosphor-squares-four class="ht-btn-icon" />
                        </button>
                        <button wire:click="$set('viewMode', 'list')" style="background:{{ $viewMode === 'list' ? 'var(--color-primary-500)' : 'transparent' }};color:{{ $viewMode === 'list' ? '#fff' : '#a1a1aa' }};border:none;cursor:pointer;">
                            <x-phosphor-list class="ht-btn-icon" />
                        </button>
                    </div>

                    {{-- New folder + upload --}}
                    <x-filament::button wire:click="$set('newFolderName', 'New Folder')" size="sm" icon="phosphor-folder-plus">
                        New Folder
                    </x-filament::button>
                </div>

                {{-- Upload dropzone --}}
                <div
                    x-data="{ dragging: false }"
                    x-on:dragover.prevent="dragging = true"
                    x-on:dragleave.prevent="dragging = false"
                    x-on:drop.prevent="dragging = false; $wire.$upload('uploadedFiles', $event.dataTransfer.files)"
                    :class="dragging ? 'ht-upload-zone-active' : ''"
                    class="ht-upload-zone"
                >
                    <label style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:16px;cursor:pointer;">
                        <input type="file" wire:model="uploadedFiles" multiple style="position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;">
                        <x-phosphor-tray-arrow-up class="ht-btn-icon" style="color:#a1a1aa;" />
                        <span class="ht-text-sm ht-font-medium" style="color:#d4d4d8;">Drop files here or click to upload</span>
                    </label>
                </div>

                @if ($uploadedFiles)
                    <div class="ht-flex ht-items-center ht-gap-3 ht-mt-2">
                        <span class="ht-text-sm" style="color:#a1a1aa;">{{ count($uploadedFiles) }} file(s) ready</span>
                        <x-filament::button wire:click="uploadFiles" size="sm" icon="phosphor-tray-arrow-up">Upload Now</x-filament::button>
                    </div>
                @endif
            </div>

            {{-- Bulk actions bar --}}
            @if (!empty($selectedFiles))
                <div class="ht-flex ht-items-center ht-justify-between ht-px-3 ht-py-2 ht-rounded-lg" style="background:#27272a;border:1px solid #3f3f46;">
                    <span class="ht-text-sm" style="color:#d4d4d8;">{{ count($selectedFiles) }} selected</span>
                    <div class="ht-flex ht-gap-2">
                        <x-filament::button wire:click="clearSelection" size="xs" color="gray">Clear</x-filament::button>
                        <x-filament::button wire:click="deleteSelectedFiles" size="xs" color="danger" icon="phosphor-trash">Delete</x-filament::button>
                    </div>
                </div>
            @endif

            {{-- File grid --}}
            @if ($viewMode === 'grid')
                <div class="ht-file-grid">
                    @forelse ($files as $file)
                        <div wire:key="grid-{{ $file['path'] }}"
                             wire:click="selectFile('{{ $file['path'] }}')"
                             class="ht-file-card"
                             style="border:2px solid {{ in_array($file['path'], $selectedFiles) ? 'var(--color-primary-500)' : '#3f3f46' }};"
                             onmouseenter="this.style.borderColor='var(--color-primary-500)'" onmouseleave="this.style.borderColor='{{ in_array($file['path'], $selectedFiles) ? 'var(--color-primary-500)' : '#3f3f46' }}'">
                            <div class="ht-file-card-thumb">
                                @if ($file['type'] === 'image')
                                    <img src="{{ $file['thumbnail'] }}" alt="{{ $file['name'] }}" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
                                @elseif ($file['type'] === 'video')
                                    <img src="{{ $file['thumbnail'] }}" alt="{{ $file['name'] }}" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
                                    @if ($file['duration'])
                                        <span style="position:absolute;bottom:6px;right:6px;background:rgba(0,0,0,0.85);color:#fff;font-size:10px;font-weight:600;padding:2px 5px;border-radius:4px;">{{ $file['duration'] }}</span>
                                    @endif
                                @else
                                    <img src="{{ $file['thumbnail'] }}" alt="" style="width:48px;height:48px;opacity:0.6;">
                                @endif
                            </div>
                            <div class="ht-p-2">
                                <p class="ht-text-xs ht-font-medium ht-truncate" style="color:#d4d4d8;" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
                                <div class="ht-flex ht-justify-between ht-items-center ht-mt-1">
                                    <span class="ht-text-xs" style="color:#71717a;">{{ $file['size_formatted'] }}</span>
                                    @if (!empty($file['references']))
                                        <span class="ht-badge">In use</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="ht-empty-state">
                            <x-phosphor-folder-open class="ht-btn-icon-xl" style="color:#52525b;" />
                            <p class="ht-text-sm" style="color:#a1a1aa;">No files in this directory</p>
                        </div>
                    @endforelse
                </div>
            @else
                {{-- List view --}}
                <div class="ht-panel ht-overflow-hidden">
                    <table class="ht-table">
                        <thead>
                            <tr>
                                <th style="width:32px;"><input type="checkbox" wire:click="selectAllFiles" style="border-radius:4px;accent-color:var(--color-primary-500);" onclick="event.stopPropagation()"></th>
                                <th style="color:#a1a1aa;font-weight:500;">Name</th>
                                <th style="width:96px;color:#a1a1aa;font-weight:500;">Type</th>
                                <th style="width:96px;color:#a1a1aa;font-weight:500;">Size</th>
                                <th style="width:160px;color:#a1a1aa;font-weight:500;">Modified</th>
                                <th style="width:80px;color:#a1a1aa;font-weight:500;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($files as $file)
                                <tr wire:key="list-{{ $file['path'] }}" wire:click="selectFile('{{ $file['path'] }}')" style="cursor:pointer;{{ in_array($file['path'], $selectedFiles) ? 'background:rgba(244,63,94,0.08);' : '' }}">
                                    <td>
                                        <input type="checkbox" @if (in_array($file['path'], $selectedFiles)) checked @endif style="border-radius:4px;accent-color:var(--color-primary-500);" onclick="event.stopPropagation()">
                                    </td>
                                    <td>
                                        <div class="ht-flex ht-items-center ht-gap-2">
                                            @if ($file['type'] === 'image' || $file['type'] === 'video')
                                                <img src="{{ $file['thumbnail'] }}" style="width:32px;height:32px;border-radius:6px;object-fit:cover;background:#000;">
                                            @else
                                                <img src="{{ $file['thumbnail'] }}" style="width:32px;height:32px;opacity:0.6;">
                                            @endif
                                            <span class="ht-truncate" style="color:#d4d4d8;" title="{{ $file['name'] }}">{{ $file['name'] }}</span>
                                        </div>
                                    </td>
                                    <td style="color:#a1a1aa;text-transform:uppercase;">{{ $file['extension'] }}</td>
                                    <td style="color:#a1a1aa;">{{ $file['size_formatted'] }}</td>
                                    <td style="color:#a1a1aa;">{{ $file['modified_formatted'] }}</td>
                                    <td>
                                        @if (!empty($file['references']))
                                            <span class="ht-badge">In use</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="ht-px-4 ht-py-12 ht-text-center" style="color:#a1a1aa;">No files in this directory</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Pagination --}}
            @if ($files->hasPages())
                <div class="ht-flex ht-justify-center">
                    {{ $files->links() }}
                </div>
            @endif
        </div>

        {{-- Details panel --}}
        <div class="ht-details ht-panel" style="overflow-y:auto;max-height:calc(100vh - 10rem);padding:16px;">
            @if ($selectedFileData)
                <div style="display:flex;flex-direction:column;gap:16px;">
                    <div class="ht-details-preview">
                        @if ($selectedFileData['type'] === 'image')
                            <img src="{{ $selectedFileData['thumbnail'] }}" style="width:100%;height:100%;object-fit:cover;cursor:pointer;" x-on:click="lightboxSrc = '{{ $selectedFileData['url'] }}'; lightboxOpen = true">
                        @elseif ($selectedFileData['type'] === 'video')
                            <img src="{{ $selectedFileData['thumbnail'] }}" style="width:100%;height:100%;object-fit:cover;cursor:pointer;" x-on:click="videoSrc = '{{ $selectedFileData['url'] }}'; videoName = '{{ $selectedFileData['name'] }}'; videoOpen = true">
                            <div class="ht-absolute ht-inset-0 ht-flex ht-items-center ht-justify-center ht-pointer-events-none">
                                <x-phosphor-play-circle class="ht-btn-icon-xl" style="color:#fff;opacity:0.8;" />
                            </div>
                        @else
                            <div class="ht-w-full ht-h-full ht-flex ht-items-center ht-justify-center">
                                <img src="{{ $selectedFileData['thumbnail'] }}" style="width:64px;height:64px;opacity:0.6;">
                            </div>
                        @endif
                    </div>

                    <div>
                        <p class="ht-text-sm ht-font-medium ht-truncate" style="color:#fff;" title="{{ $selectedFileData['name'] }}">{{ $selectedFileData['name'] }}</p>
                        <p class="ht-text-xs ht-mt-1" style="color:#a1a1aa;">{{ $selectedFileData['size_formatted'] }} · {{ $selectedFileData['modified_formatted'] }}</p>
                    </div>

                    @if (!empty($selectedFileData['references']))
                        <div class="ht-panel-dark" style="padding:12px;">
                            <p class="ht-text-xs ht-font-semibold ht-mb-2" style="color:#fca5a5;">Referenced by</p>
                            <ul style="display:flex;flex-direction:column;gap:4px;list-style:none;margin:0;padding:0;">
                                @foreach ($selectedFileData['references'] as $ref)
                                    <li class="ht-text-xs" style="color:#d4d4d8;">
                                        {{ $ref['model'] }} #{{ $ref['id'] }}
                                        <span style="color:#71717a;">({{ $ref['field'] }})</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <x-filament::button wire:click="startRename('{{ $selectedFileData['path'] }}')" size="sm" icon="phosphor-pencil-simple" style="width:100%;" :disabled="$selectedFileData['type'] === 'video' && str_starts_with($selectedFileData['path'], 'videos/')">
                            Rename
                        </x-filament::button>

                        <x-filament::button wire:click="confirmDelete('{{ $selectedFileData['path'] }}')" size="sm" color="danger" icon="phosphor-trash" style="width:100%;" :disabled="!empty($selectedFileData['references'])">
                            Delete
                        </x-filament::button>

                        <x-filament::button size="sm" color="gray" icon="phosphor-copy" style="width:100%;"
                            onclick="navigator.clipboard.writeText('{{ $selectedFileData['url'] }}')">
                            Copy URL
                        </x-filament::button>

                        <a href="{{ $selectedFileData['url'] }}" download target="_blank" class="ht-link-btn" style="background:#27272a;color:#d4d4d8;">
                            <x-phosphor-download class="ht-btn-icon" /> Download
                        </a>
                    </div>
                </div>
            @else
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;text-align:center;gap:8px;">
                    <x-phosphor-file class="ht-btn-icon-lg" style="color:#52525b;" />
                    <p class="ht-text-sm" style="color:#a1a1aa;">Select a file to view details</p>
                </div>
            @endif
        </div>
    </div>
</div>
</x-filament-panels::page>