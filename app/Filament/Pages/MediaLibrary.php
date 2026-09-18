<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresPermission;
use App\Jobs\ReindexMediaLibraryJob;
use App\Models\Image;
use App\Models\MediaFile;
use App\Models\MediaFolder;
use App\Models\Video;
use App\Services\FileManagerThumbnailService;
use App\Services\Media\MediaGuard;
use App\Services\Media\MediaIndexService;
use App\Services\Media\MediaPathGuard;
use App\Services\Media\MediaReferenceResolver;
use App\Services\Media\MediaStorageReport;
use App\Services\Media\MediaThumbnailDispatcher;
use App\Support\Bytes;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

class MediaLibrary extends Page
{
    use RequiresPermission;

    protected static string $requiredPermission = 'create_video';

    use WithFileUploads;

    // Pagination was hand-rolled: a plain `public int $page` and
    // $paginator->links(), which renders ordinary <a href="?page=2"> anchors.
    // Those caused a full browser navigation, after which the component
    // mounted fresh with page = 1 — so clicking "2" put you back on page 1.
    // WithPagination gives the gotoPage/nextPage/previousPage methods that
    // Filament's pagination component drives over Livewire.
    use WithPagination;

    protected static string|\BackedEnum|null $navigationIcon = 'phosphor-image';

    protected static ?string $navigationLabel = 'Media Library';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 99;

    /** Columns the listing can be ordered by. Each has an index behind it. */
    public const SORT_COLUMNS = ['name', 'size', 'type', 'modified', 'root'];

    protected string $view = 'filament.pages.media-library';

    /*
     * Browsing state lives in the URL.
     *
     * None of this used to be shareable or survive a revisit: opening the page
     * always dropped you in `media` in grid view sorted by date, whatever you
     * were looking at last. #[Url] makes a filtered view a link you can send
     * someone, and `history: true` on the directory means browser Back walks
     * back up the folders.
     */
    #[Url(as: 'path', history: true)]
    public string $currentDirectory = 'media';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** folder | subtree | library — how wide a search reaches. */
    #[Url(as: 'in', except: 'folder')]
    public string $searchScope = 'folder';

    /** '' | image | video | audio | document | other */
    #[Url(as: 'type', except: '')]
    public string $typeFilter = '';

    /** '' | used | unused */
    #[Url(as: 'usage', except: '')]
    public string $usageFilter = '';

    #[Url(except: 'modified')]
    public string $sortBy = 'modified';

    #[Url(except: 'desc')]
    public string $sortDirection = 'desc';

    /**
     * grid | list | flat.
     *
     * Both attributes, in this order. #[Session] supplies the remembered
     * default and #[Url] overrides it when a link carries one — BaseUrl skips
     * writing when the parameter is absent, so a bare visit keeps your own
     * layout while a shared link wins. Reversing the order would make the
     * session clobber the link.
     *
     * It needs to be shareable because the point of the flat view is "look at
     * the 40 biggest files on this box": sort, scope and filters are already
     * in the URL, so without this a shared link renders in the recipient's
     * remembered mode with somebody else's sort applied.
     */
    #[Session]
    #[Url(as: 'view', except: 'grid')]
    public string $viewMode = 'grid';

    /**
     * Rows per page.
     *
     * Capped at 200 to match resolveBulkPaths()'s cap, so "Select page →
     * Delete" can never quietly act on fewer files than are shown.
     */
    #[Session]
    #[Url(as: 'per', except: 50)]
    public int $perPage = 50;

    /** Whether the storage summary strip is expanded. */
    #[Session]
    public bool $showStorageReport = true;

    // Upload state
    public $uploadedFiles = [];

    /**
     * The file whose details are open.
     *
     * Multi-selection is client-side now (see the mediaSelection Alpine
     * component): every card used to carry wire:click="selectFile(...)", so
     * moving a 2px border cost a full server round-trip that rebuilt the whole
     * listing. Only the details panel genuinely needs the server, because it
     * resolves reference records and their edit links.
     */
    public ?string $selectedFile = null;

    public ?string $deleteTarget = null;

    public ?string $renameTarget = null;

    public string $renameNewName = '';

    // The New Folder modal's visibility used to *be* $newFolderName: the modal
    // rendered under @if ($newFolderName), so clearing the field to type your
    // own name closed the modal out from under you.
    public bool $showNewFolderModal = false;

    public string $newFolderName = '';

    // Folder actions
    public ?string $folderRenameTarget = null;

    public string $folderRenameNewName = '';

    public ?string $folderDeleteTarget = null;

    /** Move-to-folder modal: the paths being moved and the chosen destination. */
    public array $moveTargets = [];

    public string $moveDestination = '';

    public bool $showMoveModal = false;

    // Tree expansion state
    public array $expandedNodes = [];

    protected FileManagerThumbnailService $thumbnailService;

    protected MediaIndexService $mediaIndex;

    protected MediaPathGuard $pathGuard;

    protected MediaThumbnailDispatcher $thumbnailQueue;

    /**
     * Livewire re-hydrates the component on every request, so these are
     * resolved in boot() rather than injected into a constructor.
     */
    public function boot(): void
    {
        $this->thumbnailService = new FileManagerThumbnailService;
        $this->mediaIndex = app(MediaIndexService::class);
        $this->pathGuard = app(MediaPathGuard::class);
        $this->thumbnailQueue = app(MediaThumbnailDispatcher::class);
    }

    /**
     * Changing folder keeps the search term.
     *
     * It used to be wiped silently, so typing a query and then clicking a
     * folder to look for it there threw the query away. The toolbar shows what
     * is being searched and where, with a way to clear it.
     */
    public function updatingCurrentDirectory(): void
    {
        $this->resetPage();
        $this->selectedFile = null;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSortBy(): void
    {
        $this->resetPage();
    }

    public function updatingSearchScope(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingUsageFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Switch layout.
     *
     * Flat mode is a whole-library view, so it forces the library scope on the
     * way in and restores folder scope on the way out. `currentDirectory` is
     * left alone — the library scope ignores it — so leaving flat mode drops
     * you back where you were browsing.
     */
    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, ['grid', 'list', 'flat'], true)) {
            return;
        }

        $wasFlat = $this->viewMode === 'flat';
        $this->viewMode = $mode;

        if ($mode === 'flat') {
            $this->searchScope = 'library';
        } elseif ($wasFlat) {
            $this->searchScope = 'folder';
        }

        $this->selectedFile = null;
        $this->resetPage();
    }

    /**
     * Sort by a column, from a clickable header.
     *
     * Clicking the active column flips the direction; a new column starts in
     * whichever direction is actually useful — biggest and newest first,
     * names and types alphabetically.
     */
    public function sortByColumn(string $column): void
    {
        if (! in_array($column, self::SORT_COLUMNS, true)) {
            return;
        }

        if ($this->sortKey() === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = in_array($column, ['size', 'modified'], true) ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    /** Row counts offered in the page-size picker. */
    public function perPageOptions(): array
    {
        return [25, 50, 100, 200];
    }

    /**
     * The page size actually used.
     *
     * Clamped rather than trusted: $perPage comes from the query string, and
     * an arbitrary value would let a link ask for the whole table in one
     * render.
     */
    protected function resolvedPerPage(): int
    {
        return in_array($this->perPage, $this->perPageOptions(), true)
            ? $this->perPage
            : (int) config('hubtube.media_library.per_page', 50);
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function toggleStorageReport(): void
    {
        $this->showStorageReport = ! $this->showStorageReport;
    }

    /** Clear every filter without leaving the folder. */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->typeFilter = '';
        $this->usageFilter = '';
        $this->searchScope = 'folder';
        $this->resetPage();
    }

    /** Where the disk is going, for the summary strip. */
    public function getStorageReportProperty(): array
    {
        return app(MediaStorageReport::class)->all();
    }

    /** The biggest files in the library, for the summary strip. */
    public function getBiggestFilesProperty(): array
    {
        return app(MediaStorageReport::class)->biggestFiles(10);
    }

    /**
     * Jump into the flat view sorted by size, from the summary strip.
     *
     * The overview is a doorway into the grid rather than a parallel UI, so
     * "show me these" lands you in the real browser with the real actions.
     */
    public function showBiggest(?string $type = null): void
    {
        $this->viewMode = 'flat';
        $this->searchScope = 'library';
        $this->typeFilter = in_array($type, MediaFile::TYPES, true) ? $type : '';
        $this->sortBy = 'size';
        $this->sortDirection = 'desc';
        $this->search = '';
        $this->usageFilter = '';
        $this->resetPage();
    }

    /** Whether anything is narrowing the listing right now. */
    public function getHasFiltersProperty(): bool
    {
        return $this->search !== ''
            || $this->typeFilter !== ''
            || $this->usageFilter !== ''
            || $this->scopeKey() !== 'folder';
    }

    /**
     * Navigate to a directory.
     *
     * The tree and the breadcrumbs used to call $set('currentDirectory', '…')
     * with the path interpolated raw into the expression, which breaks on a
     * folder name containing a quote. Going through a method also means the
     * path is validated before it is used, and the reset below is explicit —
     * Livewire's updating hooks fire for client-side property updates, not for
     * assignments made inside a method.
     */
    public function openDirectory(string $path): void
    {
        $path = $this->sanitizePath($path);

        if (! $this->isAllowedPath($path)) {
            Notification::make()->title('Invalid path')->danger()->send();

            return;
        }

        $this->currentDirectory = $path;
        $this->resetPage();
        $this->selectedFile = null;
    }

    public function openNewFolderModal(): void
    {
        $this->newFolderName = '';
        $this->showNewFolderModal = true;
    }

    public function closeNewFolderModal(): void
    {
        $this->showNewFolderModal = false;
        $this->newFolderName = '';
    }

    public function toggleSortDirection(): void
    {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    }

    /* ------------------------------------------------------------------ */
    /* Path validation */
    /* ------------------------------------------------------------------ */

    /*
     * These all delegate to MediaPathGuard, which is the single definition of
     * what the library may touch — the indexer, the jobs and the console
     * commands have to agree with the page, and previously the page was the
     * only place the rules existed.
     */

    protected function allowedPaths(): array
    {
        return $this->pathGuard->allowedRoots();
    }

    protected function isAllowedPath(string $path): bool
    {
        return $this->pathGuard->isAllowed($path);
    }

    protected function sanitizePath(string $path): string
    {
        return $this->pathGuard->sanitize($path);
    }

    protected function isVideoSlugDirectory(string $path): bool
    {
        return $this->pathGuard->isVideoSlugDirectory($path);
    }

    protected function isUnderVideoSlugDirectory(string $path): bool
    {
        return $this->pathGuard->isUnderVideoSlugDirectory($path);
    }

    /* ------------------------------------------------------------------ */
    /* Folder tree */
    /* ------------------------------------------------------------------ */

    /**
     * The sidebar folder tree, as one query.
     *
     * This used to call Storage::allFiles() *recursively at every node*, plus
     * a size() stat per file, walking the entire disk on every render — and it
     * built the whole tree whether or not a branch was expanded, despite the
     * expansion state suggesting otherwise. The counts and sizes now come from
     * media_folders, where MediaFolderRollup keeps them.
     */
    public function getFolderTree(): array
    {
        $folders = MediaFolder::query()
            ->orderBy('depth')
            ->orderBy('name_lower')
            ->get(['path', 'path_hash', 'parent_path_hash', 'name', 'total_file_count', 'total_size']);

        // Group by parent so the tree can be assembled without re-querying.
        $childrenOf = $folders->groupBy(fn (MediaFolder $folder) => $folder->parent_path_hash ?? '');

        $build = function (MediaFolder $folder, bool $isRoot) use (&$build, $childrenOf): array {
            return [
                'path' => $folder->path,
                'name' => $isRoot ? ucfirst($folder->name) : $folder->name,
                'count' => $folder->total_file_count,
                'size' => $this->formatBytes($folder->total_size),
                'children' => $childrenOf->get($folder->path_hash, collect())
                    ->map(fn (MediaFolder $child) => $build($child, false))
                    ->all(),
            ];
        };

        // Roots in the order they are configured, not alphabetically.
        $roots = $folders->whereNull('parent_path_hash')->keyBy('path');

        return collect($this->allowedPaths())
            ->map(fn (string $root) => $roots->get(trim($root, '/')))
            ->filter()
            ->map(fn (MediaFolder $folder) => $build($folder, true))
            ->values()
            ->all();
    }

    public function toggleNode(string $path): void
    {
        if (in_array($path, $this->expandedNodes)) {
            $this->expandedNodes = array_values(array_filter($this->expandedNodes, fn ($p) => $p !== $path));
        } else {
            $this->expandedNodes[] = $path;
        }
    }

    /* ------------------------------------------------------------------ */
    /* File listing */
    /* ------------------------------------------------------------------ */

    /**
     * One page of files in the current directory, read from the index.
     *
     * This used to list the directory from disk and build full metadata for
     * every file in it — two stats, a thumbnail existence check that also
     * generated the thumbnail, an ffprobe subprocess per video and two SQL
     * queries per file — and only then slice down to one page. A folder of
     * 1,000 files paid for all 1,000 on every render to show fifty.
     *
     * It is now an indexed query with a LIMIT, so the work is the same whether
     * the folder holds fifty files or fifty thousand. Reference badges come
     * from the row's denormalised `references`, so they cost nothing at all.
     */
    public function getFilesProperty(): LengthAwarePaginator
    {
        $directory = $this->sanitizePath($this->currentDirectory);
        $perPage = $this->resolvedPerPage();

        $paginator = MediaFile::query()
            ->tap(fn ($query) => $this->applyScope($query, $directory))
            ->matchingName($this->search !== '' ? $this->search : null)
            ->ofType($this->typeFilter !== '' ? $this->typeFilter : null)
            ->when($this->usageFilter === 'used', fn ($query) => $query->where('is_referenced', true))
            ->when($this->usageFilter === 'unused', fn ($query) => $query->where('is_referenced', false))
            ->tap(fn ($query) => $this->applySort($query))
            ->paginate($perPage);

        // Queue thumbnails for this page's files only. Bounded by page size,
        // deduplicated by the job's uniqueness, and off the render path — the
        // page never generates a thumbnail itself any more.
        $this->thumbnailQueue->dispatchFor($paginator->getCollection());

        return $paginator->through(fn (MediaFile $file) => $this->presentFile($file));
    }

    /**
     * How wide the listing reaches.
     *
     * Search used to die at the folder boundary: a substring filter over one
     * directory's listing, with no way to find a file unless you already knew
     * which folder it was in. The index makes the wider scopes a `root`
     * predicate or a path prefix, both of which have an index behind them.
     */
    protected function applyScope($query, string $directory): void
    {
        match ($this->scopeKey()) {
            'library' => $query->whereIn('root', $this->allowedPaths()),
            'subtree' => $query->underDirectory($directory),
            default => $query->inDirectory($directory),
        };
    }

    /** The search scope, narrowed to one this page supports. */
    protected function scopeKey(): string
    {
        return in_array($this->searchScope, ['folder', 'subtree', 'library'], true)
            ? $this->searchScope
            : 'folder';
    }

    /**
     * Apply the chosen sort, on an indexed column.
     *
     * name_lower rather than name: MySQL's collation is case-insensitive but
     * SQLite's ORDER BY is not, so sorting on the raw column would order
     * differently in the test suite than in production.
     */
    protected function applySort($query): void
    {
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        match ($this->sortKey()) {
            'name' => $query->orderBy('name_lower', $direction),
            'size' => $query->orderBy('size', $direction),
            'type' => $query->orderBy('type', $direction)->orderBy('name_lower', $direction),
            // Which root is eating the disk, answered without leaving the grid.
            'root' => $query->orderBy('root', $direction)->orderBy('size', 'desc'),
            default => $query->orderBy('modified_at', $direction),
        };

        // A stable tiebreak, so paging cannot show the same row twice when
        // several files share a timestamp or a size.
        $query->orderBy('id', $direction);
    }

    /**
     * The subfolders of the current directory, for the main pane.
     *
     * The grid was files-only (Storage::files() never returned directories), so
     * the only way into a subfolder was the sidebar tree. These render as a row
     * above the file grid rather than being merged into the paginator: folder
     * counts are small, and mixing two row types into one paginated set makes
     * the page numbers lie.
     */
    public function getSubfoldersProperty(): array
    {
        // Only in folder scope — a subtree or library search is about files.
        if ($this->scopeKey() !== 'folder' || $this->search !== '') {
            return [];
        }

        return MediaFolder::query()
            ->childrenOf($this->sanitizePath($this->currentDirectory))
            ->orderBy('name_lower')
            ->get(['path', 'name', 'total_file_count', 'total_size'])
            ->map(fn (MediaFolder $folder) => [
                'path' => $folder->path,
                'name' => $folder->name,
                'count' => $folder->total_file_count,
                'size' => $this->formatBytes($folder->total_size),
            ])
            ->all();
    }

    /**
     * Counts per file type for the filter chips, in one grouped query.
     *
     * @return array<string, int>
     */
    public function getTypeCountsProperty(): array
    {
        $directory = $this->sanitizePath($this->currentDirectory);

        return MediaFile::query()
            ->tap(fn ($query) => $this->applyScope($query, $directory))
            ->matchingName($this->search !== '' ? $this->search : null)
            ->groupBy('type')
            ->selectRaw('type, COUNT(*) as total')
            ->pluck('total', 'type')
            ->all();
    }

    /**
     * Shape an index row the way the grid and list templates expect.
     *
     * The keys are unchanged from when this was built straight off the
     * filesystem, so the templates did not have to change with the data source.
     */
    protected function presentFile(MediaFile $file): array
    {
        return [
            'path' => $file->path,
            'name' => $file->name,
            'url' => Storage::disk('public')->url($file->path),
            'thumbnail' => $this->thumbnailUrlFor($file),
            'thumbnail_state' => $file->thumbnail_state,
            'thumbnail_pending' => $file->thumbnailPending(),
            'type' => $file->type,
            'extension' => (string) $file->extension,
            'size' => (int) $file->size,
            'size_formatted' => $this->formatBytes((int) $file->size),
            'modified' => $file->modified_at?->getTimestamp() ?? 0,
            'modified_formatted' => $file->modified_at?->format('M j, Y g:i A') ?? '—',
            // "3 months ago" — "sort by age" is what was asked for, and an
            // absolute timestamp does not read as age.
            'modified_relative' => $file->modified_at?->diffForHumans() ?? '—',
            'root' => $file->root,
            'duration' => $this->durationFor($file),
            // From the row, not from two queries per file.
            'references' => $file->references ?? [],
            'is_protected' => (bool) $file->is_protected,
            'is_referenced' => (bool) $file->is_referenced,
            // Shown under the name when a search reached past this folder.
            'directory' => $file->directory,
        ];
    }

    /**
     * What to show in a tile.
     *
     * Reads the row and nothing else: no disk probe, no generation. A file
     * whose thumbnail has not been made yet gets the type icon and a pending
     * marker rather than an <img> pointing at a file that does not exist.
     */
    protected function thumbnailUrlFor(MediaFile $file): string
    {
        if ($file->hasThumbnail()) {
            return Storage::disk('public')->url($file->thumbnail_path);
        }

        // An SVG is its own thumbnail — see FileManagerThumbnailService.
        if ($file->thumbnail_state === MediaFile::THUMB_READY && strtolower((string) $file->extension) === 'svg') {
            return Storage::disk('public')->url($file->path);
        }

        return $this->thumbnailService->fallbackIconUrl((string) $file->extension);
    }

    /**
     * A video's duration, from the index.
     *
     * Filled by the thumbnail job while the file is already open. The page used
     * to spawn an ffprobe per video per render to get this.
     */
    protected function durationFor(MediaFile $file): ?string
    {
        return $file->duration_seconds !== null
            ? $this->formatDuration((int) $file->duration_seconds)
            : null;
    }

    /**
     * Whether anything on this page is still waiting for a thumbnail.
     *
     * Drives a poll that starts and stops itself, so the page is only ever
     * refreshing while there is something to wait for.
     */
    public function getHasPendingThumbnailsProperty(): bool
    {
        return MediaFile::query()
            ->inDirectory($this->sanitizePath($this->currentDirectory))
            ->whereIn('thumbnail_state', [MediaFile::THUMB_PENDING, MediaFile::THUMB_QUEUED])
            ->exists();
    }

    /** Re-queue one file's thumbnail, from the details panel. */
    public function regenerateThumbnail(string $path): void
    {
        $file = MediaFile::query()->where('path_hash', md5($this->sanitizePath($path)))->first();

        if (! $file) {
            return;
        }

        $this->thumbnailService->deleteFor($file->path);

        $file->update([
            'thumbnail_state' => MediaFile::THUMB_PENDING,
            'thumbnail_path' => null,
            'thumbnail_error' => null,
            'thumbnail_attempts' => 0,
        ]);

        $this->thumbnailQueue->dispatchFor([$file->refresh()]);

        Notification::make()->title('Thumbnail queued')->success()->send();
    }

    /**
     * The details panel's data for the selected file.
     *
     * Resolved by path, not by scanning the current page — the panel used to go
     * blank as soon as you turned the page.
     */
    public function getSelectedFileDataProperty(): ?array
    {
        if (! $this->selectedFile) {
            return null;
        }

        $file = MediaFile::query()->where('path_hash', md5($this->selectedFile))->first();

        if (! $file) {
            return null;
        }

        return $this->presentFile($file) + [
            'width' => $file->width,
            'height' => $file->height,
            'reference_details' => app(MediaReferenceResolver::class)->describe($file),
        ];
    }

    /** The sort column, narrowed to one this page actually supports. */
    public function sortKey(): string
    {
        return in_array($this->sortBy, self::SORT_COLUMNS, true)
            ? $this->sortBy
            : 'modified';
    }

    /** Seconds as m:ss, or h:mm:ss past an hour. Null for an unknown length. */
    protected function formatDuration(int $seconds): ?string
    {
        if ($seconds <= 0) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $secs)
            : sprintf('%d:%02d', $minutes, $secs);
    }

    /**
     * This had no terabyte branch, so a large video root rendered as
     * "3,481.22 GB" — in the very panel that now reports storage totals.
     */
    protected function formatBytes(int $bytes): string
    {
        return Bytes::format($bytes);
    }

    /* ------------------------------------------------------------------ */
    /* Reference tracking */
    /* ------------------------------------------------------------------ */

    public function getReferences(string $path): array
    {
        return array_merge(
            Video::findByFilePath($path),
            Image::findByFilePath($path)
        );
    }

    public function hasReferences(string $path): bool
    {
        return count($this->getReferences($path)) > 0;
    }

    /* ------------------------------------------------------------------ */
    /* Upload */
    /* ------------------------------------------------------------------ */

    /** Extensions the library accepts, for both the rule and the file picker. */
    public function allowedUploadExtensions(): array
    {
        return array_values(array_unique(array_map(
            'strtolower',
            (array) config('hubtube.media_library.allowed_upload_extensions', ['jpg', 'png'])
        )));
    }

    public function uploadFiles(): void
    {
        $directory = $this->sanitizePath($this->currentDirectory);

        if (! $this->isAllowedPath($directory)) {
            Notification::make()->title('Invalid upload directory')->danger()->send();
            $this->uploadedFiles = [];

            return;
        }

        // Extension allowlist as well as a size cap. Admin-only, but a .phtml
        // landing in storage/app/public — which nginx serves directly — is not
        // a risk worth carrying for the sake of a shorter rule.
        $this->validate([
            'uploadedFiles.*' => [
                'file',
                'max:204800',
                'mimes:'.implode(',', $this->allowedUploadExtensions()),
            ],
        ], [
            'uploadedFiles.*.mimes' => 'That file type is not allowed in the media library.',
        ]);

        Storage::disk('public')->makeDirectory($directory);

        $count = 0;
        foreach ($this->uploadedFiles as $file) {
            $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $ext = strtolower($file->getClientOriginalExtension());
            $filename = Str::slug($original).'-'.Str::random(6).'.'.$ext;
            $file->storeAs($directory, $filename, 'public');
            $count++;
        }

        $this->uploadedFiles = [];
        $this->syncIndexFor($directory);

        Notification::make()
            ->title("Uploaded {$count} file".($count !== 1 ? 's' : ''))
            ->success()
            ->send();
    }

    /* ------------------------------------------------------------------ */
    /* Rename */
    /* ------------------------------------------------------------------ */

    public function startRename(string $path): void
    {
        $path = $this->sanitizePath($path);

        // MediaGuard owns this rule, so the button's disabled state and the
        // action itself cannot disagree — they used to use different
        // predicates.
        $blocked = app(MediaGuard::class)->renameBlockedReason($path);

        if ($blocked !== null) {
            Notification::make()->title('Cannot rename this file')->body($blocked)->warning()->send();

            return;
        }

        $this->renameTarget = $path;
        $this->renameNewName = basename($path);
    }

    public function cancelRename(): void
    {
        $this->renameTarget = null;
        $this->renameNewName = '';
    }

    public function confirmRename(): void
    {
        if (! $this->renameTarget || ! $this->renameNewName) {
            return;
        }

        $oldPath = $this->sanitizePath($this->renameTarget);
        $directory = dirname($oldPath);
        if ($directory === '.') {
            $directory = '';
        }

        $newName = $this->sanitizeFilename($this->renameNewName);
        if (! $newName) {
            Notification::make()->title('Invalid filename')->danger()->send();

            return;
        }

        $newPath = $directory ? $directory.'/'.$newName : $newName;

        if ($oldPath === $newPath) {
            $this->cancelRename();

            return;
        }

        if (! $this->isAllowedPath($oldPath) || ! $this->isAllowedPath($newPath)) {
            Notification::make()->title('Invalid path')->danger()->send();

            return;
        }

        if (Storage::disk('public')->exists($newPath)) {
            Notification::make()->title('A file with that name already exists')->danger()->send();

            return;
        }

        if ($this->isVideoSlugDirectory($oldPath) || $this->isUnderVideoSlugDirectory($oldPath)) {
            Notification::make()
                ->title('Cannot rename video slug directories')
                ->body('Rename videos from the video editor to keep URLs in sync.')
                ->warning()
                ->send();
            $this->cancelRename();

            return;
        }

        // Update database references first, then move the file.
        Video::updateFilePath($oldPath, $newPath);
        Image::updateFilePath($oldPath, $newPath);

        Storage::disk('public')->move($oldPath, $newPath);

        // The thumbnail is keyed on the source path, so the old one is now
        // orphaned and the new path has none. Nothing used to clear either.
        $this->thumbnailService->deleteFor($oldPath);

        $this->mediaIndex->movePath($oldPath, $newPath);
        $this->cancelRename();

        Notification::make()->title('File renamed')->success()->send();
    }

    /**
     * Remove a deleted file's generated thumbnail.
     *
     * Nothing did this before, so the thumbnail cache only ever grew: every
     * file ever deleted left its WebP behind for good.
     */
    protected function deleteThumbnail(string $path): void
    {
        $this->thumbnailService->deleteFor($path);
    }

    protected function sanitizeFilename(string $name): string
    {
        $name = pathinfo($name, PATHINFO_BASENAME);
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', $name);
        $name = trim($name, '-.');

        return $name;
    }

    /* ------------------------------------------------------------------ */
    /* Delete */
    /* ------------------------------------------------------------------ */

    public function confirmDelete(string $path): void
    {
        $this->deleteTarget = $path;
    }

    public function cancelDelete(): void
    {
        $this->deleteTarget = null;
    }

    public function deleteFile(): void
    {
        if (! $this->deleteTarget) {
            return;
        }

        $path = $this->sanitizePath($this->deleteTarget);

        if (! $this->isAllowedPath($path)) {
            Notification::make()->title('Invalid path')->danger()->send();
            $this->deleteTarget = null;

            return;
        }

        if ($this->hasReferences($path)) {
            Notification::make()
                ->title('Cannot delete referenced file')
                ->body('This file is used by a Video or Image record. Remove the reference first.')
                ->danger()
                ->send();
            $this->deleteTarget = null;

            return;
        }

        if ($this->stillOnDisk($path)) {
            Storage::disk('public')->delete($path);
            $this->deleteThumbnail($path);
            $this->mediaIndex->forgetPath($path);
            Notification::make()->title('File deleted')->success()->send();
        }

        $this->deleteTarget = null;
        $this->selectedFile = null;
    }

    /**
     * Delete the files the client selected.
     *
     * The paths arrive from the browser, so every one of them goes back
     * through sanitizePath + isAllowedPath + the reference check. Client-side
     * selection is a rendering convenience; it is never a permission.
     *
     * @param  list<string>  $paths
     */
    public function deleteSelectedFiles(array $paths = []): void
    {
        $blocked = [];
        $deleted = 0;

        foreach ($this->resolveBulkPaths($paths) as $path) {

            if (! $this->isAllowedPath($path)) {
                continue;
            }

            if ($this->hasReferences($path)) {
                $blocked[] = basename($path);

                continue;
            }

            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                $this->deleteThumbnail($path);
                $deleted++;
            }
        }

        $this->selectedFile = null;
        $this->syncIndexFor($this->sanitizePath($this->currentDirectory));

        if (! empty($blocked)) {
            Notification::make()
                ->title('Some files could not be deleted')
                ->body('Blocked: '.implode(', ', array_slice($blocked, 0, 5)).(count($blocked) > 5 ? '...' : ''))
                ->warning()
                ->send();
        } elseif ($deleted > 0) {
            Notification::make()
                ->title("Deleted {$deleted} file".($deleted !== 1 ? 's' : ''))
                ->success()
                ->send();
        }
    }

    /* ------------------------------------------------------------------ */
    /* Folders */
    /* ------------------------------------------------------------------ */

    public function createFolder(): void
    {
        $name = $this->sanitizeFilename($this->newFolderName);
        if (! $name) {
            Notification::make()->title('Invalid folder name')->danger()->send();

            return;
        }

        $directory = $this->sanitizePath($this->currentDirectory);
        $newPath = $directory ? $directory.'/'.$name : $name;

        if (! $this->isAllowedPath($newPath)) {
            Notification::make()->title('Invalid path')->danger()->send();

            return;
        }

        if (Storage::disk('public')->exists($newPath)) {
            Notification::make()->title('Folder already exists')->warning()->send();

            return;
        }

        Storage::disk('public')->makeDirectory($newPath);
        $this->closeNewFolderModal();
        $this->syncIndexFor($newPath);

        Notification::make()->title('Folder created')->success()->send();
    }

    public function deleteFolder(string $path): void
    {
        $path = $this->sanitizePath($path);

        if (! $this->isAllowedPath($path)) {
            Notification::make()->title('Invalid path')->danger()->send();

            return;
        }

        // One batched reference lookup for the whole subtree, checked live
        // against the database rather than against the index's flags.
        $blocked = app(MediaGuard::class)->folderBlockedReason($path, $this->filesUnder($path));

        if ($blocked !== null) {
            Notification::make()->title('Cannot delete folder')->body($blocked)->danger()->send();
            $this->folderDeleteTarget = null;

            return;
        }

        foreach ($this->filesUnder($path) as $file) {
            $this->thumbnailService->deleteFor($file);
        }

        Storage::disk('public')->deleteDirectory($path);
        $this->mediaIndex->forgetDirectory($path);

        if ($this->currentDirectory === $path || str_starts_with($this->currentDirectory, $path.'/')) {
            $this->currentDirectory = str_contains($path, '/') ? dirname($path) : 'media';
        }

        $this->folderDeleteTarget = null;
        $this->selectedFile = null;

        Notification::make()->title('Folder deleted')->success()->send();
    }

    /** Delete the folder the confirmation dialog is open for. */
    public function confirmFolderDeletion(): void
    {
        if ($this->folderDeleteTarget) {
            $this->deleteFolder($this->folderDeleteTarget);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Folder actions */
    /* ------------------------------------------------------------------ */

    /**
     * Folder rename.
     *
     * The riskiest operation on this page: every Video and Image row pointing
     * inside the folder has to follow it. `videos/` and `images/` are refused
     * outright — a record locates its own directory by slug or ulid, so
     * renaming one orphans it no matter how carefully the paths are rewritten.
     */
    public function startFolderRename(string $path): void
    {
        $path = $this->sanitizePath($path);

        if (! $this->isAllowedPath($path) || in_array($path, $this->allowedPaths(), true)) {
            Notification::make()->title('That folder cannot be renamed')->warning()->send();

            return;
        }

        if ($this->pathGuard->isProtected($path) || $this->pathGuard->rootOf($path) === 'videos' || $this->pathGuard->rootOf($path) === 'images') {
            Notification::make()
                ->title('That folder belongs to a record')
                ->body('Videos and images own their folders — rename them from their own editor.')
                ->warning()
                ->send();

            return;
        }

        $this->folderRenameTarget = $path;
        $this->folderRenameNewName = basename($path);
    }

    public function cancelFolderRename(): void
    {
        $this->folderRenameTarget = null;
        $this->folderRenameNewName = '';
    }

    public function confirmFolderRename(): void
    {
        if (! $this->folderRenameTarget) {
            return;
        }

        $oldPath = $this->sanitizePath($this->folderRenameTarget);
        $name = $this->sanitizeFilename($this->folderRenameNewName);
        $parent = str_contains($oldPath, '/') ? dirname($oldPath) : '';
        $newPath = $parent !== '' ? $parent.'/'.$name : $name;

        if (! $name || ! $this->isAllowedPath($newPath) || ! $this->isAllowedPath($oldPath)) {
            Notification::make()->title('Invalid folder name')->danger()->send();

            return;
        }

        if (Storage::disk('public')->exists($newPath)) {
            Notification::make()->title('A folder with that name already exists')->danger()->send();

            return;
        }

        // Rewrite the stored paths of everything inside before the directory
        // moves, so a failure leaves the records pointing at files that exist.
        foreach ($this->filesUnder($oldPath) as $filePath) {
            $movedPath = $newPath.substr($filePath, strlen($oldPath));

            Video::updateFilePath($filePath, $movedPath);
            Image::updateFilePath($filePath, $movedPath);
            $this->thumbnailService->deleteFor($filePath);
        }

        Storage::disk('public')->move($oldPath, $newPath);

        $this->mediaIndex->forgetDirectory($oldPath);
        $this->mediaIndex->indexDirectory($newPath, recursive: true);

        if ($this->currentDirectory === $oldPath || str_starts_with($this->currentDirectory, $oldPath.'/')) {
            $this->currentDirectory = $newPath.substr($this->currentDirectory, strlen($oldPath));
        }

        $this->cancelFolderRename();

        Notification::make()->title('Folder renamed')->success()->send();
    }

    public function confirmFolderDelete(string $path): void
    {
        $this->folderDeleteTarget = $this->sanitizePath($path);
    }

    public function cancelFolderDelete(): void
    {
        $this->folderDeleteTarget = null;
    }

    /* ------------------------------------------------------------------ */
    /* Move */
    /* ------------------------------------------------------------------ */

    /**
     * Open the move dialog.
     *
     * A folder picker rather than drag-and-drop: it works from the keyboard,
     * it works for a bulk selection, and it does not need a drag library
     * fighting Livewire's DOM morphing. If drag-and-drop is ever added it can
     * call moveFiles() directly.
     *
     * @param  list<string>  $paths
     */
    public function startMove(array $paths = []): void
    {
        $this->moveTargets = $this->resolveBulkPaths($paths);

        if ($this->moveTargets === []) {
            Notification::make()->title('Nothing selected to move')->warning()->send();

            return;
        }

        $this->moveDestination = '';
        $this->showMoveModal = true;
    }

    public function cancelMove(): void
    {
        $this->showMoveModal = false;
        $this->moveTargets = [];
        $this->moveDestination = '';
    }

    /**
     * Move the chosen files into another folder.
     *
     * File by file, not in a transaction: a filesystem move cannot be rolled
     * back, so each one is completed or reported individually rather than
     * leaving the database describing a state the disk does not share.
     */
    public function confirmMove(): void
    {
        $destination = $this->sanitizePath($this->moveDestination);

        if (! $this->isAllowedPath($destination) || ! Storage::disk('public')->exists($destination)) {
            Notification::make()->title('Pick a destination folder')->warning()->send();

            return;
        }

        $moved = 0;
        $blocked = [];
        $sources = [];

        foreach ($this->moveTargets as $path) {
            if (dirname($path) === $destination) {
                continue;
            }

            if ($this->pathGuard->isProtected($path)) {
                $blocked[] = basename($path);

                continue;
            }

            if (! $this->stillOnDisk($path)) {
                continue;
            }

            $target = $this->availablePath($destination.'/'.basename($path));

            Video::updateFilePath($path, $target);
            Image::updateFilePath($path, $target);

            Storage::disk('public')->move($path, $target);
            $this->thumbnailService->deleteFor($path);
            $this->mediaIndex->movePath($path, $target);

            $sources[] = dirname($path);
            $moved++;
        }

        foreach (array_unique($sources) as $source) {
            $this->syncIndexFor($source);
        }

        $this->cancelMove();
        $this->selectedFile = null;

        if ($blocked !== []) {
            Notification::make()
                ->title('Some files could not be moved')
                ->body('Files owned by a video or image record stay where they are: '.implode(', ', array_slice($blocked, 0, 5)))
                ->warning()
                ->send();
        }

        if ($moved > 0) {
            Notification::make()->title("Moved {$moved} file".($moved !== 1 ? 's' : ''))->success()->send();
        }
    }

    /**
     * A free filename at $path, suffixing -1, -2… on collision rather than
     * overwriting whatever is already there.
     */
    protected function availablePath(string $path): string
    {
        if (! Storage::disk('public')->exists($path)) {
            return $path;
        }

        $directory = dirname($path);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $base = pathinfo($path, PATHINFO_FILENAME);

        for ($suffix = 1; $suffix < 1000; $suffix++) {
            $candidate = $directory.'/'.$base.'-'.$suffix.($extension !== '' ? '.'.$extension : '');

            if (! Storage::disk('public')->exists($candidate)) {
                return $candidate;
            }
        }

        return $directory.'/'.$base.'-'.Str::random(6).($extension !== '' ? '.'.$extension : '');
    }

    /**
     * Every file under a directory, from the index where possible.
     *
     * @return list<string>
     */
    protected function filesUnder(string $directory): array
    {
        $indexed = MediaFile::query()
            ->underDirectory($directory)
            ->pluck('path')
            ->all();

        if ($indexed !== []) {
            return $indexed;
        }

        // Nothing indexed here yet — fall back to the disk so a folder action
        // is still correct before the first scan.
        try {
            return Storage::disk('public')->allFiles($directory);
        } catch (Throwable) {
            return [];
        }
    }

    /** The folders a selection may be moved into. */
    public function getMoveDestinationsProperty(): array
    {
        return MediaFolder::query()
            ->orderBy('path')
            ->pluck('path')
            ->reject(fn (string $path) => $this->pathGuard->isProtected($path))
            ->values()
            ->all();
    }

    /** Open the details panel for one file. */
    public function selectFile(string $path): void
    {
        $this->selectedFile = $this->sanitizePath($path);
    }

    public function clearSelection(): void
    {
        $this->selectedFile = null;
    }

    /**
     * Narrow a list of client-supplied paths to ones this page may act on.
     *
     * Capped as well as validated: a crafted request should not be able to ask
     * for an unbounded loop of filesystem work.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    protected function resolveBulkPaths(array $paths): array
    {
        return collect($paths)
            ->filter(fn ($path) => is_string($path))
            ->map(fn (string $path) => $this->sanitizePath($path))
            ->filter(fn (string $path) => $this->isAllowedPath($path))
            ->unique()
            ->take(200)
            ->values()
            ->all();
    }

    /* ------------------------------------------------------------------ */
    /* Index bookkeeping */
    /* ------------------------------------------------------------------ */

    /**
     * Bring one directory's index rows back in line with the disk.
     *
     * Called after every action this page takes, so the page is never wrong
     * about its own work. This replaces a cache-clearing helper that only ever
     * forgot the *root* node keys, leaving every subfolder's count stale for
     * up to five minutes after an upload or a delete.
     */
    protected function syncIndexFor(string $directory): void
    {
        $this->mediaIndex->indexDirectory($directory);
    }

    /**
     * Re-read the current folder on request.
     *
     * The escape hatch for anything that wrote to the disk without telling the
     * index. Bounded to one directory, so it stays a synchronous action.
     */
    public function rescanCurrentDirectory(): void
    {
        $directory = $this->sanitizePath($this->currentDirectory);

        if (! $this->isAllowedPath($directory)) {
            return;
        }

        $result = $this->mediaIndex->indexDirectory($directory);
        MediaStorageReport::forget();

        Notification::make()
            ->title('Folder rescanned')
            ->body(sprintf(
                '%d added, %d updated, %d removed.',
                $result['added'],
                $result['updated'],
                $result['removed'],
            ))
            ->success()
            ->send();
    }

    /**
     * Rebuild the whole index in the background.
     *
     * Queued rather than synchronous: a full pass over a large library is far
     * too slow for a request. The admin who asked gets a notification when it
     * lands.
     */
    public function rescanLibrary(): void
    {
        ReindexMediaLibraryJob::dispatch(auth()->id(), prune: true);
        MediaStorageReport::forget();

        Notification::make()
            ->title('Rescanning the library')
            ->body('This runs in the background. You will be notified when it finishes.')
            ->success()
            ->send();
    }

    /**
     * Confirm a path is still on disk before acting on it, dropping its index
     * row if it is not.
     *
     * The index can hold a row for a file something else deleted. Rather than
     * showing a "missing file" state nobody would know what to do with, the
     * row is removed the moment anyone touches it and the action reports why
     * nothing happened.
     */
    protected function stillOnDisk(string $path): bool
    {
        if (Storage::disk('public')->exists($path)) {
            return true;
        }

        $this->mediaIndex->forgetPath($path);

        Notification::make()
            ->title('That file no longer exists')
            ->body('It has been removed from the library index.')
            ->warning()
            ->send();

        return false;
    }

    /** Whether the folder on disk has moved on since it was last indexed. */
    public function getDirectoryStaleProperty(): bool
    {
        return $this->mediaIndex->isStale($this->sanitizePath($this->currentDirectory));
    }

    /** Whether this folder has ever been indexed, for the empty state. */
    public function getDirectoryIndexedProperty(): bool
    {
        return $this->mediaIndex->isIndexed($this->sanitizePath($this->currentDirectory));
    }
}
