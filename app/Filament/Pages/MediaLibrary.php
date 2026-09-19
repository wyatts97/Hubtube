<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresPermission;
use App\Jobs\CompressMediaFileJob;
use App\Jobs\ReindexMediaLibraryJob;
use App\Models\Image;
use App\Models\MediaFile;
use App\Models\MediaFolder;
use App\Models\Video;
use App\Services\FileManagerThumbnailService;
use App\Services\Media\MediaCompressService;
use App\Services\Media\MediaGuard;
use App\Services\Media\MediaIndexService;
use App\Services\Media\MediaPathGuard;
use App\Services\Media\MediaReferenceResolver;
use App\Services\Media\MediaThumbnailDispatcher;
use App\Support\Bytes;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

/**
 * The admin media library, laid out like a file explorer.
 *
 * Folder tree on the left, the folder's contents in the middle, details on the
 * right. Everything is read from the media_files / media_folders index, so a
 * listing is one paginated, indexed query whatever the folder holds.
 *
 * "What is eating the disk" is answered with the explorer's own controls
 * rather than a separate view: pick All files, set a minimum size and sort by
 * the Size column. Any filter searches the current folder *and everything
 * below it*, the way Explorer's search box does.
 *
 * Every dialog is a Filament action. The browser only ever sends paths, and
 * each action re-validates them — client-side selection is a rendering
 * convenience, never a permission.
 */
class MediaLibrary extends Page
{
    use RequiresPermission;
    use WithFileUploads;
    use WithPagination;

    protected static string $requiredPermission = 'create_video';

    protected static string|\BackedEnum|null $navigationIcon = 'phosphor-image';

    protected static ?string $navigationLabel = 'Media Library';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.media-library';

    public const SORT_COLUMNS = ['name', 'type', 'size', 'modified'];

    /** The size filter's options, in bytes. */
    public const MIN_SIZES = [
        '100mb' => 100 * 1024 * 1024,
        '1gb' => 1024 * 1024 * 1024,
        '10gb' => 10 * 1024 * 1024 * 1024,
    ];

    public const PER_PAGE = 100;

    /*
     * Browsing state lives in the URL, so any view is a link you can send and
     * browser Back walks back up the folders.
     */

    /** The folder being shown. Empty means "All files" — every root at once. */
    #[Url(as: 'path', history: true)]
    public string $currentDirectory = 'media';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** '' | image | video | audio | document | other */
    #[Url(as: 'type', except: '')]
    public string $typeFilter = '';

    /** '' or a key of MIN_SIZES. */
    #[Url(as: 'min', except: '')]
    public string $minSize = '';

    /** List every file below this folder rather than just its own. */
    #[Url(as: 'deep', except: false)]
    public bool $includeSubfolders = false;

    #[Url(as: 'sort', except: 'name')]
    public string $sortBy = 'name';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDirection = 'asc';

    /**
     * details | icons. #[Session] remembers your layout; #[Url] lets a shared
     * link override it. In that order, or the session clobbers the link.
     */
    #[Session]
    #[Url(as: 'view', except: 'details')]
    public string $viewMode = 'details';

    /** The file whose details are open. Multi-selection is client-side. */
    public ?string $selectedFile = null;

    public $uploadedFiles = [];

    protected MediaPathGuard $pathGuard;

    protected MediaIndexService $mediaIndex;

    protected FileManagerThumbnailService $thumbnailService;

    /** Resolved in boot() because Livewire re-hydrates on every request. */
    public function boot(): void
    {
        $this->pathGuard = app(MediaPathGuard::class);
        $this->mediaIndex = app(MediaIndexService::class);
        $this->thumbnailService = new FileManagerThumbnailService;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'typeFilter', 'minSize', 'includeSubfolders', 'sortBy', 'sortDirection'], true)) {
            $this->resetPage();
        }
    }

    // ── Navigation ──────────────────────────────────────────────────────────

    /**
     * Open a folder, or All files when $path is empty.
     *
     * Through a method rather than $set so the path is validated first and a
     * folder name containing a quote cannot break the expression.
     */
    public function openDirectory(string $path): void
    {
        $path = $this->pathGuard->sanitize($path);

        if ($path !== '' && ! $this->pathGuard->isAllowed($path)) {
            Notification::make()->title('Invalid path')->danger()->send();

            return;
        }

        $this->currentDirectory = $path;
        $this->selectedFile = null;
        $this->resetPage();
    }

    /** The folder above this one, or null at All files. */
    public function getParentDirectoryProperty(): ?string
    {
        $current = $this->directory();

        if ($current === '') {
            return null;
        }

        return str_contains($current, '/') ? dirname($current) : '';
    }

    /**
     * Sort from a column header. Clicking the active column flips it; a new
     * column starts the useful way round — biggest and newest first, names
     * and types A–Z.
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

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, ['details', 'icons'], true)) {
            $this->viewMode = $mode;
        }
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->typeFilter = '';
        $this->minSize = '';
        $this->includeSubfolders = false;
        $this->resetPage();
    }

    public function sortKey(): string
    {
        return in_array($this->sortBy, self::SORT_COLUMNS, true) ? $this->sortBy : 'name';
    }

    /** The current folder, sanitised. '' is All files. */
    public function directory(): string
    {
        return $this->pathGuard->sanitize($this->currentDirectory);
    }

    /**
     * Whether the listing reaches below the current folder.
     *
     * Any filter does, like Explorer's search box, and All files always does.
     * Subfolder rows are hidden then, because the result is a list of files.
     */
    public function isSearching(): bool
    {
        return $this->hasFilters() || $this->directory() === '';
    }

    public function hasFilters(): bool
    {
        return $this->search !== ''
            || $this->typeFilter !== ''
            || $this->minSizeBytes() > 0
            || $this->includeSubfolders;
    }

    protected function minSizeBytes(): int
    {
        return self::MIN_SIZES[$this->minSize] ?? 0;
    }

    // ── Listing ─────────────────────────────────────────────────────────────

    /** One page of files, as a single indexed query. */
    public function getFilesProperty(): LengthAwarePaginator
    {
        $directory = $this->directory();
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $query = MediaFile::query()
            ->when(
                $directory === '',
                fn ($query) => $query->whereIn('root', $this->pathGuard->allowedRoots()),
                fn ($query) => $this->isSearching() ? $query->underDirectory($directory) : $query->inDirectory($directory),
            )
            ->matchingName($this->search !== '' ? $this->search : null)
            ->ofType($this->typeFilter !== '' ? $this->typeFilter : null)
            ->when($this->minSizeBytes() > 0, fn ($query) => $query->where('size', '>=', $this->minSizeBytes()));

        // name_lower, not name: MySQL's collation ignores case, SQLite's does not.
        match ($this->sortKey()) {
            'size' => $query->orderBy('size', $direction),
            'type' => $query->orderBy('type', $direction)->orderBy('name_lower', $direction),
            'modified' => $query->orderBy('modified_at', $direction),
            default => $query->orderBy('name_lower', $direction),
        };

        // A stable tiebreak, so paging never shows a row twice.
        $paginator = $query->orderBy('id', $direction)->paginate(self::PER_PAGE);

        // Thumbnails for this page only, off the render path.
        app(MediaThumbnailDispatcher::class)->dispatchFor($paginator->getCollection());

        return $paginator->through(fn (MediaFile $file) => $this->presentFile($file));
    }

    /**
     * The folders inside the current one — or the roots, at All files.
     *
     * Kept out of the paginator: mixing two row types makes page numbers lie.
     */
    public function getSubfoldersProperty(): array
    {
        if ($this->hasFilters()) {
            return [];
        }

        $folders = $this->directory() === ''
            ? MediaFolder::query()->whereNull('parent_path_hash')->whereIn('path', $this->pathGuard->allowedRoots())
            : MediaFolder::query()->childrenOf($this->directory());

        return $folders
            ->orderBy('name_lower')
            ->get(['path', 'name', 'total_file_count', 'total_size'])
            ->map(fn (MediaFolder $folder) => [
                'path' => $folder->path,
                'name' => $folder->name,
                'count' => $folder->total_file_count,
                'size' => (int) $folder->total_size,
                'size_formatted' => Bytes::format((int) $folder->total_size),
            ])
            ->all();
    }

    /** The sidebar tree, from one media_folders query. */
    public function getFolderTree(): array
    {
        $folders = MediaFolder::query()
            ->orderBy('depth')
            ->orderBy('name_lower')
            ->get(['path', 'path_hash', 'parent_path_hash', 'name', 'total_file_count', 'total_size']);

        $childrenOf = $folders->groupBy(fn (MediaFolder $folder) => $folder->parent_path_hash ?? '');

        $build = function (MediaFolder $folder, bool $isRoot) use (&$build, $childrenOf): array {
            return [
                'path' => $folder->path,
                'name' => $isRoot ? ucfirst($folder->name) : $folder->name,
                'count' => $folder->total_file_count,
                'size' => Bytes::format((int) $folder->total_size),
                'children' => $childrenOf->get($folder->path_hash, collect())
                    ->map(fn (MediaFolder $child) => $build($child, false))
                    ->all(),
            ];
        };

        // Roots in the order they are configured, not alphabetically.
        $roots = $folders->whereNull('parent_path_hash')->keyBy('path');

        return collect($this->pathGuard->allowedRoots())
            ->map(fn (string $root) => $roots->get($root))
            ->filter()
            ->map(fn (MediaFolder $folder) => $build($folder, true))
            ->values()
            ->all();
    }

    /** The shape the templates expect for one file. */
    protected function presentFile(MediaFile $file): array
    {
        return [
            'path' => $file->path,
            'name' => $file->name,
            'directory' => $file->directory,
            'url' => Storage::disk('public')->url($file->path),
            'thumbnail' => $this->thumbnailUrlFor($file),
            'thumbnail_state' => $file->thumbnail_state,
            'thumbnail_pending' => $file->thumbnailPending(),
            'type' => $file->type,
            'extension' => (string) $file->extension,
            'size' => (int) $file->size,
            'size_formatted' => Bytes::format((int) $file->size),
            'modified_formatted' => $file->modified_at?->format('M j, Y g:i A') ?? '—',
            'modified_relative' => $file->modified_at?->diffForHumans() ?? '—',
            'duration' => $this->formatDuration((int) $file->duration_seconds),
            'is_protected' => (bool) $file->is_protected,
            'is_referenced' => (bool) $file->is_referenced,
        ];
    }

    /** Reads the row and nothing else: no disk probe, no generation. */
    protected function thumbnailUrlFor(MediaFile $file): string
    {
        if ($file->hasThumbnail()) {
            return Storage::disk('public')->url($file->thumbnail_path);
        }

        // An SVG is its own thumbnail.
        if ($file->thumbnail_state === MediaFile::THUMB_READY && strtolower((string) $file->extension) === 'svg') {
            return Storage::disk('public')->url($file->path);
        }

        return $this->thumbnailService->fallbackIconUrl((string) $file->extension);
    }

    /** m:ss, or h:mm:ss past an hour. Null for an unknown length. */
    protected function formatDuration(int $seconds): ?string
    {
        if ($seconds <= 0) {
            return null;
        }

        return $seconds >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60)
            : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * Compress progress for the files on this page, in one cache read.
     *
     * @param  list<string>  $paths
     */
    public function compressStatuses(array $paths): array
    {
        return app(MediaCompressService::class)->statuses($paths);
    }

    /** Whether this folder has ever been indexed, for the empty state. */
    public function getDirectoryIndexedProperty(): bool
    {
        return $this->directory() === '' || $this->mediaIndex->isIndexed($this->directory());
    }

    // ── Details pane ────────────────────────────────────────────────────────

    public function selectFile(string $path): void
    {
        $this->selectedFile = $this->pathGuard->sanitize($path);
    }

    public function clearSelection(): void
    {
        $this->selectedFile = null;
    }

    /** Resolved by path, so the pane survives paging. */
    public function getSelectedFileDataProperty(): ?array
    {
        if (! $this->selectedFile) {
            return null;
        }

        $file = MediaFile::query()->where('path_hash', md5($this->selectedFile))->first();

        if (! $file) {
            return null;
        }

        $compress = app(MediaCompressService::class);
        $video = $file->type === 'video' ? $compress->videoOriginalFor($file->path) : null;

        return $this->presentFile($file) + [
            'width' => $file->width,
            'height' => $file->height,
            'reference_details' => app(MediaReferenceResolver::class)->describe($file),
            'compress_status' => $compress->status($file->path),
            // A video's original upload can be swapped for a compressed copy
            // sitting beside it; any other file you just delete yourself.
            'video' => $video ? ['id' => $video->id, 'title' => $video->title] : null,
            'compressed_copies' => $video ? $compress->compressedCopiesOf($file->path) : [],
        ];
    }

    public function regenerateThumbnail(string $path): void
    {
        $file = MediaFile::query()->where('path_hash', md5($this->pathGuard->sanitize($path)))->first();

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

        app(MediaThumbnailDispatcher::class)->dispatchFor([$file->refresh()]);

        Notification::make()->title('Thumbnail queued')->success()->send();
    }

    // ── Upload ──────────────────────────────────────────────────────────────

    public function allowedUploadExtensions(): array
    {
        return array_values(array_unique(array_map(
            'strtolower',
            (array) config('hubtube.media_library.allowed_upload_extensions', ['jpg', 'png'])
        )));
    }

    public function uploadFiles(): void
    {
        $directory = $this->directory();

        if (! $this->pathGuard->isAllowed($directory)) {
            Notification::make()->title('Open a folder to upload into')->warning()->send();
            $this->uploadedFiles = [];

            return;
        }

        // An allowlist as well as a size cap: storage/app/public is served
        // directly by nginx, so a .phtml must never land there.
        $this->validate([
            'uploadedFiles.*' => ['file', 'max:204800', 'mimes:'.implode(',', $this->allowedUploadExtensions())],
        ], [
            'uploadedFiles.*.mimes' => 'That file type is not allowed in the media library.',
        ]);

        Storage::disk('public')->makeDirectory($directory);

        $count = 0;

        foreach ($this->uploadedFiles as $file) {
            $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.Str::random(6);
            $file->storeAs($directory, $name.'.'.strtolower($file->getClientOriginalExtension()), 'public');
            $count++;
        }

        $this->uploadedFiles = [];
        $this->mediaIndex->indexDirectory($directory);

        Notification::make()->title('Uploaded '.$count.' '.Str::plural('file', $count))->success()->send();
    }

    // ── Rescan ──────────────────────────────────────────────────────────────

    /** Re-read the current folder from disk. Bounded, so synchronous. */
    public function rescanCurrentDirectory(): void
    {
        $directory = $this->directory();

        if (! $this->pathGuard->isAllowed($directory)) {
            $this->rescanLibrary();

            return;
        }

        $result = $this->mediaIndex->indexDirectory($directory);

        Notification::make()
            ->title('Folder refreshed')
            ->body(sprintf('%d added, %d updated, %d removed.', $result['added'], $result['updated'], $result['removed']))
            ->success()
            ->send();
    }

    /** Rebuild the whole index in the background; far too slow for a request. */
    public function rescanLibrary(): void
    {
        ReindexMediaLibraryJob::dispatch(auth()->id(), prune: true);

        Notification::make()
            ->title('Rescanning the library')
            ->body('This runs in the background. You will be notified when it finishes.')
            ->success()
            ->send();
    }

    // ── Actions ─────────────────────────────────────────────────────────────

    public function newFolderAction(): Action
    {
        return Action::make('newFolder')
            ->color('success')
            ->modalHeading('New folder')
            ->modalWidth('md')
            ->schema([TextInput::make('name')->label('Folder name')->required()->maxLength(120)->autofocus()])
            ->action(function (array $data, Action $action): void {
                $name = $this->sanitizeFilename($data['name']);
                $path = ltrim($this->directory().'/'.$name, '/');

                if ($name === '' || ! $this->pathGuard->isAllowed($path)) {
                    $this->warn('Invalid folder name', 'Open a folder first, and use letters, numbers, dots, dashes or underscores.');
                    $action->halt();
                }

                if (Storage::disk('public')->exists($path)) {
                    $this->warn('Something with that name already exists');
                    $action->halt();
                }

                Storage::disk('public')->makeDirectory($path);
                $this->mediaIndex->indexDirectory($path);

                Notification::make()->title('Folder created')->success()->send();
            });
    }

    /**
     * Rename a file or a folder.
     *
     * Every Video and Image row pointing at or inside it follows. Folders a
     * record locates by convention (videos/{slug}, images/{id}) are refused,
     * because no stored path would move with them.
     */
    public function renameAction(): Action
    {
        return Action::make('rename')
            ->color('info')
            ->modalHeading(fn (array $arguments) => 'Rename '.basename((string) ($arguments['path'] ?? '')))
            ->modalWidth('md')
            ->fillForm(fn (array $arguments) => ['name' => basename((string) ($arguments['path'] ?? ''))])
            ->schema([TextInput::make('name')->label('New name')->required()->maxLength(200)->autofocus()])
            ->beforeFormFilled(function (array $arguments, Action $action): void {
                if ($reason = $this->renameBlockedReason($this->pathGuard->sanitize((string) ($arguments['path'] ?? '')))) {
                    $this->warn('This cannot be renamed', $reason);
                    $action->cancel();
                }
            })
            ->action(function (array $data, array $arguments, Action $action): void {
                $old = $this->pathGuard->sanitize((string) ($arguments['path'] ?? ''));
                $name = $this->sanitizeFilename($data['name']);
                $new = ltrim((str_contains($old, '/') ? dirname($old) : '').'/'.$name, '/');

                if ($reason = $this->renameBlockedReason($old)) {
                    $this->warn('This cannot be renamed', $reason);
                    $action->halt();
                }

                if ($name === '' || ! $this->pathGuard->isAllowed($new)) {
                    $this->warn('Invalid name');
                    $action->halt();
                }

                // The same allowlist as uploads, or a rename could turn a
                // picture into a .php that nginx serves from storage.
                $extension = strtolower(pathinfo($new, PATHINFO_EXTENSION));

                if (! Storage::disk('public')->directoryExists($old)
                    && $extension !== strtolower(pathinfo($old, PATHINFO_EXTENSION))
                    && ! in_array($extension, $this->allowedUploadExtensions(), true)) {
                    $this->warn('That file type is not allowed in the media library');
                    $action->halt();
                }

                if ($new === $old) {
                    return;
                }

                if (Storage::disk('public')->exists($new)) {
                    $this->warn('Something with that name already exists');
                    $action->halt();
                }

                $this->movePath($old, $new);

                if ($this->selectedFile === $old) {
                    $this->selectedFile = $new;
                }

                Notification::make()->title('Renamed')->success()->send();
            });
    }

    /** Delete files and folders. Anything still in use by a record is skipped. */
    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->requiresConfirmation()
            ->color('danger')
            ->modalIcon('phosphor-trash')
            ->modalHeading(fn (array $arguments) => $this->describeTargets($arguments, 'Delete'))
            ->modalDescription('This cannot be undone — media files are not backed up. Anything still used by a video or image is skipped.')
            ->modalSubmitActionLabel('Delete')
            ->action(function (array $arguments): void {
                $deleted = 0;
                $blocked = [];
                $guard = app(MediaGuard::class);
                $disk = Storage::disk('public');

                foreach ($this->resolveBulkPaths($arguments['paths'] ?? []) as $path) {
                    if ($disk->directoryExists($path)) {
                        if ($reason = $guard->folderBlockedReason($path, $this->filesUnder($path))) {
                            $blocked[] = basename($path).' — '.$reason;

                            continue;
                        }

                        foreach ($this->filesUnder($path) as $file) {
                            $this->thumbnailService->deleteFor($file);
                        }

                        $disk->deleteDirectory($path);
                        $this->mediaIndex->forgetDirectory($path);

                        // Standing inside what was just deleted: step out of it.
                        if ($this->currentDirectory === $path || str_starts_with($this->currentDirectory, $path.'/')) {
                            $this->currentDirectory = str_contains($path, '/') ? dirname($path) : '';
                        }

                        $deleted++;

                        continue;
                    }

                    if ($reason = $guard->blockedReason($path)) {
                        $blocked[] = basename($path).' — '.$reason;

                        continue;
                    }

                    if ($this->stillOnDisk($path)) {
                        $disk->delete($path);
                        $this->thumbnailService->deleteFor($path);
                        $this->mediaIndex->forgetPath($path);
                        $deleted++;
                    }
                }

                $this->selectedFile = null;
                $this->report($deleted, 'Deleted', 'Not deleted', $blocked);
            });
    }

    /**
     * Move files into another folder.
     *
     * One at a time, not in a transaction: a filesystem move cannot be rolled
     * back, so each is finished or reported on its own. A name collision gets
     * a -1 suffix rather than overwriting.
     */
    public function moveAction(): Action
    {
        return Action::make('move')
            ->color('info')
            ->modalHeading(fn (array $arguments) => $this->describeTargets($arguments, 'Move'))
            ->modalWidth('md')
            ->schema([
                Select::make('destination')
                    ->label('Destination folder')
                    ->options(fn () => collect($this->getMoveDestinationsProperty())->mapWithKeys(fn ($path) => [$path => $path])->all())
                    ->searchable()
                    ->required(),
            ])
            ->modalSubmitActionLabel('Move')
            ->action(function (array $data, array $arguments, Action $action): void {
                $destination = $this->pathGuard->sanitize((string) $data['destination']);

                if (! $this->pathGuard->isAllowed($destination) || $this->pathGuard->isProtected($destination)
                    || ! Storage::disk('public')->directoryExists($destination)) {
                    $this->warn('Pick a destination folder');
                    $action->halt();
                }

                $moved = 0;
                $blocked = [];

                foreach ($this->resolveBulkPaths($arguments['paths'] ?? []) as $path) {
                    if (dirname($path) === $destination) {
                        continue;
                    }

                    if ($this->pathGuard->isProtected($path)) {
                        $blocked[] = basename($path).' — owned by a video or image record';

                        continue;
                    }

                    // Files only: a folder could otherwise be moved into itself.
                    if (Storage::disk('public')->directoryExists($path)) {
                        $blocked[] = basename($path).' — folders are moved by renaming them';

                        continue;
                    }

                    if (! $this->stillOnDisk($path)) {
                        continue;
                    }

                    $this->movePath($path, $this->availablePath($destination.'/'.basename($path)));
                    $moved++;
                }

                $this->selectedFile = null;
                $this->report($moved, 'Moved', 'Not moved', $blocked);
            });
    }

    /** Queue re-encodes of the selected videos. Each writes a new file. */
    public function compressAction(): Action
    {
        $compress = app(MediaCompressService::class);

        return Action::make('compress')
            ->color('info')
            ->modalHeading(fn (array $arguments) => $this->describeTargets($arguments, 'Compress'))
            ->modalIcon('phosphor-film-strip')
            ->modalWidth('lg')
            ->modalDescription(function (array $arguments) use ($compress): HtmlString {
                [, $refused] = $compress->splitTargets($this->resolveBulkPaths($arguments['paths'] ?? []));

                $text = 'Each video is encoded to a <strong>new file beside it</strong> (e.g. <code>clip.av1.mp4</code>). '
                    .'Nothing is overwritten or deleted, and you are notified as each one finishes.';

                if ($refused !== []) {
                    $text .= '<br><br>Skipped: '.e(collect($refused)->take(5)->map(fn ($r) => $r['name'].' ('.$r['reason'].')')->implode(', '));
                }

                return new HtmlString($text);
            })
            ->fillForm(fn () => ['codec' => $compress->defaultCodec(), 'quality' => 'balanced'])
            ->schema([
                Radio::make('codec')
                    ->options(collect(['av1', 'vp9', 'h265'])->mapWithKeys(fn ($codec) => [$codec => $compress->codecLabel($codec)])->all())
                    ->descriptions([
                        'av1' => 'Smallest files; plays in every modern browser. Recommended.',
                        'vp9' => 'Plays in every modern browser.',
                        'h265' => 'Fastest to encode. Plays on most devices, but not in every desktop browser.',
                    ])
                    ->disableOptionWhen(fn (string $value) => ! ($compress->available()[$value] ?? false))
                    ->required(),
                Radio::make('quality')
                    ->options(collect(MediaCompressService::QUALITIES)->mapWithKeys(fn ($quality) => [$quality => $compress->qualityLabel($quality)])->all())
                    ->inline()
                    ->required(),
            ])
            ->beforeFormFilled(function (array $arguments, Action $action) use ($compress): void {
                [$ok, $refused] = $compress->splitTargets($this->resolveBulkPaths($arguments['paths'] ?? []));

                if ($ok === []) {
                    $this->warn('Nothing here can be compressed', $refused[0]['reason'] ?? 'Select one or more video files.');
                    $action->cancel();
                }

                if (! in_array(true, $compress->available(), true)) {
                    $this->warn('No encoder available', "This server's ffmpeg has none of libx265, libvpx-vp9, libaom-av1 or libsvtav1.");
                    $action->cancel();
                }
            })
            ->modalSubmitActionLabel('Start')
            ->action(function (array $data, array $arguments, Action $action) use ($compress): void {
                $codec = (string) ($data['codec'] ?? '');

                if (! ($compress->available()[$codec] ?? false)) {
                    $this->warn('That codec is not available on this server');
                    $action->halt();
                }

                $quality = in_array($data['quality'] ?? '', MediaCompressService::QUALITIES, true) ? $data['quality'] : 'balanced';

                // Re-validated here: the paths came from the browser.
                [$ok] = $compress->splitTargets($this->resolveBulkPaths($arguments['paths'] ?? []));

                foreach ($ok as $path) {
                    $compress->setStatus($path, ['state' => 'queued', 'codec' => $codec]);
                    CompressMediaFileJob::dispatch($path, $codec, $quality, auth()->id());
                }

                Notification::make()
                    ->title('Queued '.count($ok).' '.Str::plural('video', count($ok)).' for '.$compress->codecLabel($codec))
                    ->body('They run one at a time in the background.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Swap a video's original upload for a compressed copy beside it.
     *
     * The only way a compress frees space on a file a record points at: the
     * column is repointed first and the old file deleted after, so a failure
     * can never leave the video without a file.
     */
    public function replaceOriginalAction(): Action
    {
        return Action::make('replaceOriginal')
            ->requiresConfirmation()
            ->color('danger')
            ->modalIcon('phosphor-swap')
            ->modalHeading(fn (array $arguments) => 'Replace the original with '.basename((string) ($arguments['replacement'] ?? '')).'?')
            ->modalDescription(new HtmlString(
                'The old upload is <strong>deleted permanently</strong> — media files are not backed up.<br><br>'
                .'The original is the player\'s fallback source, the "Original" quality option and the Pro download. '
                .'The HLS stream and renditions are not touched. <strong>H.265 does not play in some desktop browsers</strong> '
                .'(Firefox, and Chrome without hardware support), so prefer an AV1 copy. '
                .'Any later re-encode of this video starts from the compressed copy.'
            ))
            ->modalSubmitActionLabel('Replace and delete original')
            ->action(function (array $arguments): void {
                $compress = app(MediaCompressService::class);
                $source = $this->pathGuard->sanitize((string) ($arguments['path'] ?? ''));
                $replacement = $this->pathGuard->sanitize((string) ($arguments['replacement'] ?? ''));
                $video = $compress->videoOriginalFor($source);

                $reason = match (true) {
                    ! $video => 'That file is no longer a video\'s original upload.',
                    ! in_array($replacement, array_column($compress->compressedCopiesOf($source), 'path'), true) => 'That is not a compressed copy of this file.',
                    default => $compress->replaceOriginal($video, $replacement),
                };

                if ($reason !== null) {
                    $this->warn('The original was not replaced', $reason);

                    return;
                }

                $this->selectedFile = $replacement;
                Notification::make()->title('Original replaced')->success()->send();
            });
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** Folders a selection may be moved into. */
    public function getMoveDestinationsProperty(): array
    {
        return MediaFolder::query()
            ->orderBy('path')
            ->pluck('path')
            ->reject(fn (string $path) => $this->pathGuard->isProtected($path))
            ->values()
            ->all();
    }

    /**
     * Narrow client-supplied paths to ones this page may act on. Capped, so a
     * crafted request cannot ask for an unbounded loop of filesystem work.
     *
     * @return list<string>
     */
    protected function resolveBulkPaths(mixed $paths): array
    {
        return collect(is_array($paths) ? $paths : [$paths])
            ->filter(fn ($path) => is_string($path))
            ->map(fn (string $path) => $this->pathGuard->sanitize($path))
            ->filter(fn (string $path) => $this->pathGuard->isAllowed($path))
            ->unique()
            ->take(200)
            ->values()
            ->all();
    }

    protected function renameBlockedReason(string $path): ?string
    {
        if (! $this->pathGuard->isAllowed($path)) {
            return 'That path is outside the media library.';
        }

        if (in_array($path, $this->pathGuard->allowedRoots(), true)) {
            return 'Top-level media folders cannot be renamed.';
        }

        if (Storage::disk('public')->directoryExists($path)) {
            return $this->pathGuard->isProtected($path) || in_array($this->pathGuard->rootOf($path), ['videos', 'images'], true)
                ? 'Videos and images own their folders — rename them from their own editor.'
                : null;
        }

        return app(MediaGuard::class)->renameBlockedReason($path);
    }

    /**
     * Move a file or folder, taking every record that points into it along.
     * Records are rewritten first, so a failed move leaves them pointing at
     * files that exist.
     */
    protected function movePath(string $old, string $new): void
    {
        $disk = Storage::disk('public');
        $isDirectory = $disk->directoryExists($old);

        foreach ($isDirectory ? $this->filesUnder($old) : [$old] as $file) {
            $moved = $new.substr($file, strlen($old));
            Video::updateFilePath($file, $moved);
            Image::updateFilePath($file, $moved);
            $this->thumbnailService->deleteFor($file);
        }

        $disk->move($old, $new);

        if ($isDirectory) {
            $this->mediaIndex->forgetDirectory($old);
            $this->mediaIndex->indexDirectory($new, recursive: true);

            if ($this->currentDirectory === $old || str_starts_with($this->currentDirectory, $old.'/')) {
                $this->currentDirectory = $new.substr($this->currentDirectory, strlen($old));
            }
        } else {
            $this->mediaIndex->movePath($old, $new);
        }
    }

    /** A free path, suffixing -1, -2… on collision. */
    protected function availablePath(string $path): string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return $path;
        }

        $stem = dirname($path).'/'.pathinfo($path, PATHINFO_FILENAME);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $extension = $extension !== '' ? '.'.$extension : '';

        for ($suffix = 1; $suffix < 1000; $suffix++) {
            if (! $disk->exists($stem.'-'.$suffix.$extension)) {
                return $stem.'-'.$suffix.$extension;
            }
        }

        return $stem.'-'.Str::random(6).$extension;
    }

    /** @return list<string> every file under a directory, from the index or the disk */
    protected function filesUnder(string $directory): array
    {
        $indexed = MediaFile::query()->underDirectory($directory)->pluck('path')->all();

        if ($indexed !== []) {
            return $indexed;
        }

        try {
            return Storage::disk('public')->allFiles($directory);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Whether a file is still on disk, dropping its index row if not — the
     * index can hold a row for something deleted behind the app's back.
     */
    protected function stillOnDisk(string $path): bool
    {
        if (Storage::disk('public')->exists($path)) {
            return true;
        }

        $this->mediaIndex->forgetPath($path);
        $this->warn('That file no longer exists', 'It has been removed from the library index.');

        return false;
    }

    protected function sanitizeFilename(string $name): string
    {
        return trim((string) preg_replace('/[^a-zA-Z0-9._-]/', '-', basename($name)), '-.');
    }

    /** "Delete clip.mp4" or "Delete 3 items", for a modal heading. */
    protected function describeTargets(array $arguments, string $verb): string
    {
        $paths = $this->resolveBulkPaths($arguments['paths'] ?? []);

        return count($paths) === 1 ? $verb.' '.basename($paths[0]) : $verb.' '.count($paths).' items';
    }

    protected function warn(string $title, ?string $body = null): void
    {
        Notification::make()->title($title)->body($body)->warning()->send();
    }

    /** @param  list<string>  $blocked */
    protected function report(int $count, string $verb, string $blockedTitle, array $blocked): void
    {
        if ($blocked !== []) {
            $this->warn($blockedTitle, implode("\n", array_slice($blocked, 0, 5)).(count($blocked) > 5 ? "\n…" : ''));
        }

        if ($count > 0) {
            Notification::make()->title($verb.' '.$count.' '.Str::plural('item', $count))->success()->send();
        }
    }
}
