<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresPermission;
use App\Jobs\ReindexMediaLibraryJob;
use App\Models\Image;
use App\Models\MediaFile;
use App\Models\MediaFolder;
use App\Models\Video;
use App\Services\FfmpegService;
use App\Services\FileManagerThumbnailService;
use App\Services\Media\MediaIndexService;
use App\Services\Media\MediaPathGuard;
use App\Services\Media\MediaReferenceResolver;
use App\Services\Media\MediaThumbnailDispatcher;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    protected string $view = 'filament.pages.media-library';

    // Navigation state
    public string $currentDirectory = 'media';

    // View state
    public string $viewMode = 'grid';

    public string $search = '';

    public string $sortBy = 'modified';

    public string $sortDirection = 'desc';

    // Upload state
    public $uploadedFiles = [];

    // Selection / actions
    public array $selectedFiles = [];

    public ?string $selectedFile = null;

    public ?string $deleteTarget = null;

    public ?string $renameTarget = null;

    public string $renameNewName = '';

    // The New Folder modal's visibility used to *be* $newFolderName: the modal
    // rendered under @if ($newFolderName), so clearing the field to type your
    // own name closed the modal out from under you.
    public bool $showNewFolderModal = false;

    public string $newFolderName = '';

    // Lazy-loaded tree expansion state
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

    public function updatingCurrentDirectory(): void
    {
        $this->resetPage();
        $this->selectedFiles = [];
        $this->selectedFile = null;
        $this->search = '';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSortBy(): void
    {
        $this->resetPage();
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
        $this->selectedFiles = [];
        $this->selectedFile = null;
        $this->search = '';
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
        $perPage = (int) config('hubtube.media_library.per_page', 50);

        $paginator = MediaFile::query()
            ->inDirectory($directory)
            ->matchingName($this->search !== '' ? $this->search : null)
            ->tap(fn ($query) => $this->applySort($query))
            ->paginate($perPage);

        // Queue thumbnails for this page's files only. Bounded by page size,
        // deduplicated by the job's uniqueness, and off the render path — the
        // page never generates a thumbnail itself any more.
        $this->thumbnailQueue->dispatchFor($paginator->getCollection());

        return $paginator->through(fn (MediaFile $file) => $this->presentFile($file));
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
            default => $query->orderBy('modified_at', $direction),
        };

        // A stable tiebreak, so paging cannot show the same row twice when
        // several files share a timestamp or a size.
        $query->orderBy('id', $direction);
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
            'duration' => $this->durationFor($file),
            // From the row, not from two queries per file.
            'references' => $file->references ?? [],
            'is_protected' => (bool) $file->is_protected,
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
    protected function sortKey(): string
    {
        return in_array($this->sortBy, ['name', 'size', 'type', 'modified'], true)
            ? $this->sortBy
            : 'modified';
    }

    /**
     * A video's duration, for the badge on its tile.
     *
     * Cached on path and mtime: this spawns an ffprobe subprocess, and it used
     * to do so for every video in the directory on every single render. Keyed
     * on mtime so replacing a file re-probes it.
     *
     * It also used to run a bare `ffprobe` from $PATH with a POSIX-only
     * `2>/dev/null` appended, ignoring the binary the admin configured in
     * Storage settings.
     */
    protected function getVideoDuration(string $path): ?string
    {
        $absolutePath = Storage::disk('public')->path($path);
        if (! file_exists($absolutePath)) {
            return null;
        }

        try {
            $modified = Storage::disk('public')->lastModified($path);
        } catch (Throwable) {
            return null;
        }

        $seconds = Cache::remember(
            'filemanager_duration:'.md5($path).':'.$modified,
            (int) config('hubtube.media_library.duration_cache_ttl', 86400),
            function () use ($absolutePath) {
                if (! FfmpegService::isAvailable()) {
                    return 0;
                }

                try {
                    $output = shell_exec(sprintf(
                        '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s',
                        FfmpegService::ffprobePath(),
                        escapeshellarg($absolutePath),
                    ));

                    return max(0, (int) round((float) trim($output ?? '')));
                } catch (Throwable) {
                    return 0;
                }
            }
        );

        return $this->formatDuration((int) $seconds);
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

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
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

    public function uploadFiles(): void
    {
        $directory = $this->sanitizePath($this->currentDirectory);

        if (! $this->isAllowedPath($directory)) {
            Notification::make()->title('Invalid upload directory')->danger()->send();
            $this->uploadedFiles = [];

            return;
        }

        $this->validate([
            'uploadedFiles.*' => 'file|max:204800',
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
        $directory = dirname($path);
        if ($directory === '.') {
            $directory = '';
        }

        // Block renaming anything under a video slug directory (including the slug dir itself)
        if ($this->isVideoSlugDirectory($path) || $this->isUnderVideoSlugDirectory($path)) {
            Notification::make()
                ->title('Cannot rename video slug directories')
                ->body('Rename videos from the video editor to keep URLs in sync.')
                ->warning()
                ->send();

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

    public function deleteSelectedFiles(): void
    {
        $blocked = [];
        $deleted = 0;

        foreach ($this->selectedFiles as $path) {
            $path = $this->sanitizePath($path);

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

        $this->selectedFiles = [];
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

        if ($this->isVideoSlugDirectory($path)) {
            Notification::make()
                ->title('Cannot delete video directory')
                ->body('Delete the video from the video editor instead.')
                ->warning()
                ->send();

            return;
        }

        // Prevent deleting folders that contain referenced files.
        try {
            $files = Storage::disk('public')->allFiles($path);
            foreach ($files as $file) {
                if ($this->hasReferences($file)) {
                    Notification::make()
                        ->title('Cannot delete folder')
                        ->body('The folder contains files referenced by Video or Image records.')
                        ->danger()
                        ->send();

                    return;
                }
            }
        } catch (Throwable) {
        }

        Storage::disk('public')->deleteDirectory($path);
        $this->mediaIndex->forgetDirectory($path);

        if ($this->currentDirectory === $path || str_starts_with($this->currentDirectory, $path.'/')) {
            $this->currentDirectory = 'media';
        }

        Notification::make()->title('Folder deleted')->success()->send();
    }

    /* ------------------------------------------------------------------ */
    /* Selection / details panel */
    /* ------------------------------------------------------------------ */

    public function selectFile(string $path): void
    {
        $this->selectedFile = $path;

        if (in_array($path, $this->selectedFiles)) {
            $this->selectedFiles = array_values(array_filter($this->selectedFiles, fn ($p) => $p !== $path));
        } else {
            $this->selectedFiles[] = $path;
        }
    }

    public function selectAllFiles(): void
    {
        $files = $this->getFilesProperty()->items();
        $paths = array_column($files, 'path');

        $this->selectedFiles = array_values(array_unique(array_merge($this->selectedFiles, $paths)));
    }

    public function clearSelection(): void
    {
        $this->selectedFiles = [];
        $this->selectedFile = null;
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
