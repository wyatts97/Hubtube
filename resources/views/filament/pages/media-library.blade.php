<x-filament-panels::page>
<div x-data="{
    lightboxOpen: false,
    lightboxSrc: null,
    videoOpen: false,
    videoSrc: null,
    videoName: ''
}" class="space-y-4" @keydown.escape.window="lightboxOpen = false; videoOpen = false; $wire.clearSelection()">

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
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
        <div style="background:#27272a;border:1px solid #3f3f46;border-radius:12px;box-shadow:0 25px 50px rgba(0,0,0,0.5);padding:24px;max-width:420px;width:100%;margin:0 16px;">
            <div class="flex flex-col items-center gap-3 text-center">
                <div class="w-10 h-10 rounded-full bg-danger-500/20 flex items-center justify-center">
                    <x-phosphor-trash class="w-5 h-5 text-danger-400" style="width:20px;height:20px;" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-white">Delete File?</p>
                    <p class="text-xs mt-1 break-all" style="color:#a1a1aa;">{{ basename($deleteTarget) }}</p>
                </div>
                <div class="flex gap-3 w-full">
                    <x-filament::button wire:click="cancelDelete" color="gray" size="sm" class="flex-1">Cancel</x-filament::button>
                    <x-filament::button wire:click="deleteFile" color="danger" size="sm" class="flex-1">Delete</x-filament::button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Rename modal --}}
    @if ($renameTarget)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
        <div style="background:#27272a;border:1px solid #3f3f46;border-radius:12px;box-shadow:0 25px 50px rgba(0,0,0,0.5);padding:24px;max-width:420px;width:100%;margin:0 16px;">
            <p class="text-sm font-semibold text-white mb-3">Rename File</p>
            <x-filament::input.wrapper class="mb-4">
                <x-filament::input type="text" wire:model="renameNewName" />
            </x-filament::input.wrapper>
            <div class="flex gap-3 w-full">
                <x-filament::button wire:click="cancelRename" color="gray" size="sm" class="flex-1">Cancel</x-filament::button>
                <x-filament::button wire:click="confirmRename" color="primary" size="sm" class="flex-1">Rename</x-filament::button>
            </div>
        </div>
    </div>
    @endif

    {{-- New folder modal --}}
    @if ($newFolderName)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
        <div style="background:#27272a;border:1px solid #3f3f46;border-radius:12px;box-shadow:0 25px 50px rgba(0,0,0,0.5);padding:24px;max-width:420px;width:100%;margin:0 16px;">
            <p class="text-sm font-semibold text-white mb-3">New Folder</p>
            <x-filament::input.wrapper class="mb-4">
                <x-filament::input type="text" wire:model="newFolderName" placeholder="Folder name" />
            </x-filament::input.wrapper>
            <div class="flex gap-3 w-full">
                <x-filament::button wire:click="$set('newFolderName', '')" color="gray" size="sm" class="flex-1">Cancel</x-filament::button>
                <x-filament::button wire:click="createFolder" color="primary" size="sm" class="flex-1">Create</x-filament::button>
            </div>
        </div>
    </div>
    @endif

    {{-- Lightbox --}}
    <div x-show="lightboxOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/90" style="display:none;" x-on:click.self="lightboxOpen = false">
        <button x-on:click="lightboxOpen = false" class="absolute top-4 right-4 text-white p-2">
            <x-phosphor-x class="w-6 h-6" />
        </button>
        <img :src="lightboxSrc" class="max-w-[90vw] max-h-[90vh] object-contain rounded-lg" />
    </div>

    {{-- Video preview modal --}}
    <div x-show="videoOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/90" style="display:none;" x-on:click.self="videoOpen = false">
        <button x-on:click="videoOpen = false" class="absolute top-4 right-4 text-white p-2">
            <x-phosphor-x class="w-6 h-6" />
        </button>
        <div class="w-full max-w-4xl p-4">
            <p x-text="videoName" class="text-white text-sm mb-2 truncate"></p>
            <video :src="videoSrc" controls class="w-full max-h-[80vh] rounded-lg bg-black"></video>
        </div>
    </div>

    {{-- Main layout: sidebar | content | details --}}
    <div class="flex gap-4" style="min-height:calc(100vh - 12rem);">

        {{-- Sidebar: folder tree --}}
        <div style="width:260px;flex-shrink:0;background:#18181b;border:1px solid #3f3f46;border-radius:12px;overflow-y:auto;max-height:calc(100vh - 10rem);padding:12px;">
            <p class="text-xs font-semibold mb-2" style="color:#a1a1aa;text-transform:uppercase;letter-spacing:0.05em;">Folders</p>
            <ul class="space-y-1">
                @foreach ($tree as $node)
                    @include('filament.pages.media-library-tree-node', ['node' => $node, 'level' => 0])
                @endforeach
            </ul>
        </div>

        {{-- Main content --}}
        <div class="flex-1 min-w-0 space-y-4">

            {{-- Toolbar --}}
            <div style="background:#18181b;border:1px solid #3f3f46;border-radius:12px;padding:12px 16px;">
                <div class="flex flex-wrap items-center gap-3">
                    {{-- Breadcrumbs --}}
                    <div class="flex items-center gap-1 text-sm" style="color:#a1a1aa;">
                        <button wire:click="$set('currentDirectory', 'media')" style="color:#d4d4d8;">Media</button>
                        @php
                            $crumbs = explode('/', trim($currentDirectory, '/'));
                            $crumbPath = '';
                        @endphp
                        @foreach ($crumbs as $crumb)
                            @php $crumbPath .= ($crumbPath ? '/' : '') . $crumb; @endphp
                            <span class="text-xs">/</span>
                            <button wire:click="$set('currentDirectory', '{{ $crumbPath }}')" style="color:#d4d4d8;" class="hover:text-primary-400">{{ ucfirst($crumb) }}</button>
                        @endforeach
                    </div>

                    <div class="flex-1"></div>

                    {{-- Search --}}
                    <x-filament::input.wrapper style="width:200px;">
                        <x-filament::input type="text" wire:model.live.debounce.300ms="search" placeholder="Search files..." />
                    </x-filament::input.wrapper>

                    {{-- Sort --}}
                    <select wire:model.live="sortBy" class="text-sm rounded-md border-none px-3 py-2" style="width:140px;background:#27272a;color:#d4d4d8;">
                        <option value="modified">Modified</option>
                        <option value="name">Name</option>
                        <option value="size">Size</option>
                        <option value="type">Type</option>
                    </select>

                    <button wire:click="toggleSortDirection" class="p-2 rounded-md" style="background:#27272a;color:#d4d4d8;">
                        @if ($sortDirection === 'asc')
                            <x-phosphor-sort-ascending class="w-4 h-4" />
                        @else
                            <x-phosphor-sort-descending class="w-4 h-4" />
                        @endif
                    </button>

                    {{-- View toggle --}}
                    <div class="flex rounded-md overflow-hidden" style="background:#27272a;">
                        <button wire:click="$set('viewMode', 'grid')" class="p-2" style="background:{{ $viewMode === 'grid' ? 'var(--color-primary-500)' : 'transparent' }};color:{{ $viewMode === 'grid' ? '#fff' : '#a1a1aa' }};">
                            <x-phosphor-squares-four class="w-4 h-4" />
                        </button>
                        <button wire:click="$set('viewMode', 'list')" class="p-2" style="background:{{ $viewMode === 'list' ? 'var(--color-primary-500)' : 'transparent' }};color:{{ $viewMode === 'list' ? '#fff' : '#a1a1aa' }};">
                            <x-phosphor-list class="w-4 h-4" />
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
                    :class="dragging ? 'border-primary-500 bg-primary-500/10' : 'border-zinc-700 hover:border-zinc-500'"
                    class="mt-3 relative border-2 border-dashed rounded-lg transition-colors cursor-pointer"
                >
                    <label class="flex flex-col items-center justify-center gap-1 py-4 px-4 cursor-pointer">
                        <input type="file" wire:model="uploadedFiles" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                        <x-phosphor-tray-arrow-up class="w-5 h-5" style="color:#a1a1aa;" />
                        <span class="text-sm font-medium" style="color:#d4d4d8;">Drop files here or click to upload</span>
                    </label>
                </div>

                @if ($uploadedFiles)
                    <div class="mt-2 flex items-center gap-3">
                        <span class="text-sm" style="color:#a1a1aa;">{{ count($uploadedFiles) }} file(s) ready</span>
                        <x-filament::button wire:click="uploadFiles" size="sm" icon="phosphor-tray-arrow-up">Upload Now</x-filament::button>
                    </div>
                @endif
            </div>

            {{-- Bulk actions bar --}}
            @if (!empty($selectedFiles))
                <div class="flex items-center justify-between px-3 py-2 rounded-lg" style="background:#27272a;border:1px solid #3f3f46;">
                    <span class="text-sm" style="color:#d4d4d8;">{{ count($selectedFiles) }} selected</span>
                    <div class="flex gap-2">
                        <x-filament::button wire:click="clearSelection" size="xs" color="gray">Clear</x-filament::button>
                        <x-filament::button wire:click="deleteSelectedFiles" size="xs" color="danger" icon="phosphor-trash">Delete</x-filament::button>
                    </div>
                </div>
            @endif

            {{-- File grid --}}
            @if ($viewMode === 'grid')
                <div class="grid gap-3" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));">
                    @forelse ($files as $file)
                        <div wire:key="grid-{{ $file['path'] }}"
                             wire:click="selectFile('{{ $file['path'] }}')"
                             class="group relative cursor-pointer rounded-xl overflow-hidden"
                             style="background:#18181b;border:2px solid {{ in_array($file['path'], $selectedFiles) ? 'var(--color-primary-500)' : '#3f3f46' }};transition:border-color 0.15s;"
                             onmouseenter="this.style.borderColor='var(--color-primary-500)'" onmouseleave="this.style.borderColor='{{ in_array($file['path'], $selectedFiles) ? 'var(--color-primary-500)' : '#3f3f46' }}'">
                            <div style="height:130px;background:#000;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                                @if ($file['type'] === 'image')
                                    <img src="{{ $file['thumbnail'] }}" alt="{{ $file['name'] }}" loading="lazy" class="w-full h-full object-cover">
                                @elseif ($file['type'] === 'video')
                                    <img src="{{ $file['thumbnail'] }}" alt="{{ $file['name'] }}" loading="lazy" class="w-full h-full object-cover">
                                    @if ($file['duration'])
                                        <span style="position:absolute;bottom:6px;right:6px;background:rgba(0,0,0,0.85);color:#fff;font-size:10px;font-weight:600;padding:2px 5px;border-radius:4px;">{{ $file['duration'] }}</span>
                                    @endif
                                @else
                                    <img src="{{ $file['thumbnail'] }}" alt="" class="w-12 h-12 opacity-60">
                                @endif
                            </div>
                            <div class="p-2">
                                <p class="text-xs font-medium truncate" style="color:#d4d4d8;" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
                                <div class="flex justify-between items-center mt-1">
                                    <span class="text-xs" style="color:#71717a;">{{ $file['size_formatted'] }}</span>
                                    @if (!empty($file['references']))
                                        <span class="text-[10px] px-1.5 py-0.5 rounded-full" style="background:#7f1d1d;color:#fca5a5;">In use</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full flex flex-col items-center justify-center py-16 text-center gap-2">
                            <x-phosphor-folder-open class="w-10 h-10" style="color:#52525b;" />
                            <p class="text-sm" style="color:#a1a1aa;">No files in this directory</p>
                        </div>
                    @endforelse
                </div>
            @else
                {{-- List view --}}
                <div style="background:#18181b;border:1px solid #3f3f46;border-radius:12px;overflow:hidden;">
                    <table class="w-full text-left text-sm">
                        <thead style="background:#27272a;">
                            <tr>
                                <th class="px-4 py-2 w-8"><input type="checkbox" wire:click="selectAllFiles" class="rounded" style="accent-color:var(--color-primary-500);" onclick="event.stopPropagation()"></th>
                                <th class="px-4 py-2 font-medium" style="color:#a1a1aa;">Name</th>
                                <th class="px-4 py-2 font-medium w-24" style="color:#a1a1aa;">Type</th>
                                <th class="px-4 py-2 font-medium w-24" style="color:#a1a1aa;">Size</th>
                                <th class="px-4 py-2 font-medium w-40" style="color:#a1a1aa;">Modified</th>
                                <th class="px-4 py-2 font-medium w-20" style="color:#a1a1aa;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($files as $file)
                                <tr wire:key="list-{{ $file['path'] }}" wire:click="selectFile('{{ $file['path'] }}')" class="cursor-pointer" style="border-top:1px solid #3f3f46;background:{{ in_array($file['path'], $selectedFiles) ? 'rgba(244,63,94,0.08)' : 'transparent' }};">
                                    <td class="px-4 py-2">
                                        <input type="checkbox" @if (in_array($file['path'], $selectedFiles)) checked @endif class="rounded" style="accent-color:var(--color-primary-500);" onclick="event.stopPropagation()">
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="flex items-center gap-2">
                                            @if ($file['type'] === 'image' || $file['type'] === 'video')
                                                <img src="{{ $file['thumbnail'] }}" class="w-8 h-8 rounded object-cover bg-black">
                                            @else
                                                <img src="{{ $file['thumbnail'] }}" class="w-8 h-8 opacity-60">
                                            @endif
                                            <span class="truncate" style="color:#d4d4d8;" title="{{ $file['name'] }}">{{ $file['name'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2" style="color:#a1a1aa;text-transform:uppercase;">{{ $file['extension'] }}</td>
                                    <td class="px-4 py-2" style="color:#a1a1aa;">{{ $file['size_formatted'] }}</td>
                                    <td class="px-4 py-2" style="color:#a1a1aa;">{{ $file['modified_formatted'] }}</td>
                                    <td class="px-4 py-2">
                                        @if (!empty($file['references']))
                                            <span class="text-[10px] px-1.5 py-0.5 rounded-full" style="background:#7f1d1d;color:#fca5a5;">In use</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center" style="color:#a1a1aa;">No files in this directory</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Pagination --}}
            @if ($files->hasPages())
                <div class="flex justify-center">
                    {{ $files->links() }}
                </div>
            @endif
        </div>

        {{-- Details panel --}}
        <div style="width:300px;flex-shrink:0;background:#18181b;border:1px solid #3f3f46;border-radius:12px;padding:16px;overflow-y:auto;max-height:calc(100vh - 10rem);">
            @if ($selectedFileData)
                <div class="space-y-4">
                    <div class="relative rounded-lg overflow-hidden bg-black" style="height:160px;">
                        @if ($selectedFileData['type'] === 'image')
                            <img src="{{ $selectedFileData['thumbnail'] }}" class="w-full h-full object-cover cursor-pointer" x-on:click="lightboxSrc = '{{ $selectedFileData['url'] }}'; lightboxType = 'image'; lightboxOpen = true">
                        @elseif ($selectedFileData['type'] === 'video')
                            <img src="{{ $selectedFileData['thumbnail'] }}" class="w-full h-full object-cover cursor-pointer" x-on:click="videoSrc = '{{ $selectedFileData['url'] }}'; videoName = '{{ $selectedFileData['name'] }}'; videoOpen = true">
                            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                <x-phosphor-play-circle class="w-10 h-10 text-white opacity-80" />
                            </div>
                        @else
                            <div class="w-full h-full flex items-center justify-center">
                                <img src="{{ $selectedFileData['thumbnail'] }}" class="w-16 h-16 opacity-60">
                            </div>
                        @endif
                    </div>

                    <div>
                        <p class="text-sm font-medium truncate" style="color:#fff;" title="{{ $selectedFileData['name'] }}">{{ $selectedFileData['name'] }}</p>
                        <p class="text-xs mt-1" style="color:#a1a1aa;">{{ $selectedFileData['size_formatted'] }} · {{ $selectedFileData['modified_formatted'] }}</p>
                    </div>

                    @if (!empty($selectedFileData['references']))
                        <div style="background:#27272a;border:1px solid #3f3f46;border-radius:8px;padding:12px;">
                            <p class="text-xs font-semibold mb-2" style="color:#fca5a5;">Referenced by</p>
                            <ul class="space-y-1">
                                @foreach ($selectedFileData['references'] as $ref)
                                    <li class="text-xs" style="color:#d4d4d8;">
                                        {{ $ref['model'] }} #{{ $ref['id'] }}
                                        <span style="color:#71717a;">({{ $ref['field'] }})</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="space-y-2">
                        <x-filament::button wire:click="startRename('{{ $selectedFileData['path'] }}')" size="sm" icon="phosphor-pencil-simple" class="w-full" :disabled="$selectedFileData['type'] === 'video' && str_starts_with($selectedFileData['path'], 'videos/')">
                            Rename
                        </x-filament::button>

                        <x-filament::button wire:click="confirmDelete('{{ $selectedFileData['path'] }}')" size="sm" color="danger" icon="phosphor-trash" class="w-full" :disabled="!empty($selectedFileData['references'])">
                            Delete
                        </x-filament::button>

                        <x-filament::button size="sm" color="gray" icon="phosphor-copy" class="w-full"
                            onclick="navigator.clipboard.writeText('{{ $selectedFileData['url'] }}')">
                            Copy URL
                        </x-filament::button>

                        <a href="{{ $selectedFileData['url'] }}" download target="_blank" class="inline-flex items-center justify-center gap-2 w-full px-3 py-2 rounded-lg text-sm font-medium" style="background:#27272a;color:#d4d4d8;">
                            <x-phosphor-download class="w-4 h-4" /> Download
                        </a>
                    </div>
                </div>
            @else
                <div class="flex flex-col items-center justify-center h-full text-center gap-2">
                    <x-phosphor-file class="w-8 h-8" style="color:#52525b;" />
                    <p class="text-sm" style="color:#a1a1aa;">Select a file to view details</p>
                </div>
            @endif
        </div>
    </div>
</div>
</x-filament-panels::page>