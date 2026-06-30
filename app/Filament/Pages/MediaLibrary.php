<?php

namespace App\Filament\Pages;

use Throwable;
use App\Models\Image;
use App\Models\Video;
use App\Services\FileManagerThumbnailService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;

class MediaLibrary extends Page
{
    use WithFileUploads;

    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-image';
    protected static ?string $navigationLabel = 'Media Library';
    protected static string | \UnitEnum | null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 99;
    protected string $view = 'filament.pages.media-library';

    // Navigation state
    public string $currentDirectory = 'media';

    // View state
    public string $viewMode = 'grid';
    public string $search = '';
    public string $sortBy = 'modified';
    public string $sortDirection = 'desc';
    public int $page = 1;

    // Upload state
    public $uploadedFiles = [];

    // Selection / actions
    public array $selectedFiles = [];
    public ?string $selectedFile = null;
    public ?string $deleteTarget = null;
    public ?string $renameTarget = null;
    public string $renameNewName = '';
    public string $newFolderName = '';

    // Lazy-loaded tree expansion state
    public array $expandedNodes = [];

    protected FileManagerThumbnailService $thumbnailService;

    public function boot(): void
    {
        $this->thumbnailService = new FileManagerThumbnailService();
    }

    public function updatingCurrentDirectory(): void
    {
        $this->page = 1;
        $this->selectedFiles = [];
        $this->selectedFile = null;
        $this->search = '';
    }

    public function updatingSearch(): void
    {
        $this->page = 1;
    }

    public function toggleSortDirection(): void
    {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    }

    /* ------------------------------------------------------------------ */
    /* Path validation                                                      */
    /* ------------------------------------------------------------------ */

    protected function allowedPaths(): array
    {
        return (array) config('hubtube.media_library.allowed_paths', ['media']);
    }

    protected function isAllowedPath(string $path): bool
    {
        $path = trim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            return false;
        }

        foreach ($this->allowedPaths() as $allowed) {
            $allowed = trim($allowed, '/');
            if ($path === $allowed || str_starts_with($path, $allowed . '/')) {
                return true;
            }
        }

        return false;
    }

    protected function sanitizePath(string $path): string
    {
        $path = trim($path, '/');
        $path = str_replace('\\', '/', $path);
        $parts = explode('/', $path);
        $parts = array_filter($parts, fn ($p) => $p !== '' && $p !== '.');

        return implode('/', $parts);
    }

    protected function isVideoSlugDirectory(string $path): bool
    {
        return (bool) preg_match('#^videos/[^/]+$#', trim($path, '/'));
    }

    protected function isUnderVideoSlugDirectory(string $path): bool
    {
        return (bool) preg_match('#^videos/[^/]+/.+$#', trim($path, '/'));
    }

    /* ------------------------------------------------------------------ */
    /* Folder tree                                                          */
    /* ------------------------------------------------------------------ */

    public function getFolderTree(): array
    {
        $cacheKey = 'filemanager:tree:' . md5(implode(',', $this->allowedPaths()));
        $cacheTtl = (int) config('hubtube.media_library.cache_ttl', 300);

        return Cache::remember($cacheKey, $cacheTtl, function () {
            $tree = [];

            foreach ($this->allowedPaths() as $root) {
                $root = trim($root, '/');
                if (!Storage::disk('public')->exists($root)) {
                    continue;
                }

                $tree[] = $this->buildTreeNode($root, true);
            }

            return $tree;
        });
    }

    protected function buildTreeNode(string $path, bool $isRoot = false): array
    {
        $name = $isRoot ? ucfirst(basename($path)) : basename($path);
        $cacheKey = 'filemanager:node:' . md5($path);
        $cacheTtl = (int) config('hubtube.media_library.cache_ttl', 300);

        $meta = Cache::remember($cacheKey, $cacheTtl, function () use ($path) {
            $count = 0;
            $size = 0;

            try {
                $files = Storage::disk('public')->allFiles($path);
                $count = count($files);
                foreach ($files as $file) {
                    $size += Storage::disk('public')->size($file);
                }
            } catch (Throwable) {
            }

            return ['count' => $count, 'size' => $size];
        });

        $children = [];
        try {
            $directories = Storage::disk('public')->directories($path);
            foreach ($directories as $dir) {
                $children[] = $this->buildTreeNode($dir);
            }
        } catch (Throwable) {
        }

        return [
            'path' => $path,
            'name' => $name,
            'count' => $meta['count'],
            'size' => $this->formatBytes($meta['size']),
            'children' => $children,
        ];
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
    /* File listing                                                         */
    /* ------------------------------------------------------------------ */

    public function getFilesProperty(): LengthAwarePaginator
    {
        $directory = $this->sanitizePath($this->currentDirectory);
        $perPage = (int) config('hubtube.media_library.per_page', 50);

        $allFiles = [];
        try {
            $allFiles = Storage::disk('public')->files($directory);
        } catch (Throwable) {
        }

        $files = [];
        foreach ($allFiles as $path) {
            $name = basename($path);

            if ($this->search && !str_contains(strtolower($name), strtolower($this->search))) {
                continue;
            }

            try {
                $size = Storage::disk('public')->size($path);
                $modified = Storage::disk('public')->lastModified($path);
            } catch (Throwable) {
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $files[] = [
                'path' => $path,
                'name' => $name,
                'url' => Storage::disk('public')->url($path),
                'thumbnail' => $this->thumbnailService->thumbnailUrl($path),
                'type' => $this->fileType($extension),
                'extension' => $extension,
                'size' => $size,
                'size_formatted' => $this->formatBytes($size),
                'modified' => $modified,
                'modified_formatted' => now()->setTimestamp($modified)->format('M j, Y g:i A'),
                'duration' => $this->isVideo($extension) ? $this->getVideoDuration($path) : null,
                'references' => $this->getReferences($path),
            ];
        }

        $files = $this->sortFiles($files);

        $currentPage = max(1, $this->page);
        $total = count($files);
        $items = array_slice($files, ($currentPage - 1) * $perPage, $perPage);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            ['path' => request()->url()]
        );
    }

    protected function fileType(string $extension): string
    {
        return match (true) {
            $this->isImage($extension) => 'image',
            $this->isVideo($extension) => 'video',
            in_array($extension, ['mp3', 'wav', 'ogg', 'flac', 'aac']) => 'audio',
            in_array($extension, ['pdf']) => 'document',
            default => 'other',
        };
    }

    protected function isImage(string $extension): bool
    {
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico']);
    }

    protected function isVideo(string $extension): bool
    {
        return in_array($extension, ['mp4', 'mov', 'webm', 'mkv', 'avi', 'flv', 'wmv']);
    }

    protected function sortFiles(array $files): array
    {
        usort($files, function ($a, $b) {
            $dir = $this->sortDirection === 'asc' ? 1 : -1;

            return match ($this->sortBy) {
                'name' => strcmp(strtolower($a['name']), strtolower($b['name'])) * $dir,
                'size' => ($a['size'] <=> $b['size']) * $dir,
                'type' => strcmp($a['type'], $b['type']) * $dir,
                default => ($a['modified'] <=> $b['modified']) * $dir,
            };
        });

        return $files;
    }

    protected function getVideoDuration(string $path): ?string
    {
        $absolutePath = Storage::disk('public')->path($path);
        if (!file_exists($absolutePath)) {
            return null;
        }

        try {
            $output = shell_exec('ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($absolutePath) . ' 2>/dev/null');
            $seconds = (int) round((float) trim($output ?? ''));
            if ($seconds <= 0) {
                return null;
            }

            $hours = intdiv($seconds, 3600);
            $minutes = intdiv($seconds % 3600, 60);
            $secs = $seconds % 60;

            if ($hours > 0) {
                return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
            }

            return sprintf('%d:%02d', $minutes, $secs);
        } catch (Throwable) {
            return null;
        }
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    /* ------------------------------------------------------------------ */
    /* Reference tracking                                                   */
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
    /* Upload                                                               */
    /* ------------------------------------------------------------------ */

    public function uploadFiles(): void
    {
        $directory = $this->sanitizePath($this->currentDirectory);

        if (!$this->isAllowedPath($directory)) {
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
            $filename = Str::slug($original) . '-' . Str::random(6) . '.' . $ext;
            $file->storeAs($directory, $filename, 'public');
            $count++;
        }

        $this->uploadedFiles = [];
        $this->clearTreeCache();

        Notification::make()
            ->title("Uploaded {$count} file" . ($count !== 1 ? 's' : ''))
            ->success()
            ->send();
    }

    /* ------------------------------------------------------------------ */
    /* Rename                                                               */
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
        if (!$this->renameTarget || !$this->renameNewName) {
            return;
        }

        $oldPath = $this->sanitizePath($this->renameTarget);
        $directory = dirname($oldPath);
        if ($directory === '.') {
            $directory = '';
        }

        $newName = $this->sanitizeFilename($this->renameNewName);
        if (!$newName) {
            Notification::make()->title('Invalid filename')->danger()->send();
            return;
        }

        $newPath = $directory ? $directory . '/' . $newName : $newName;

        if ($oldPath === $newPath) {
            $this->cancelRename();
            return;
        }

        if (!$this->isAllowedPath($oldPath) || !$this->isAllowedPath($newPath)) {
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

        $this->clearTreeCache();
        $this->cancelRename();

        Notification::make()->title('File renamed')->success()->send();
    }

    protected function sanitizeFilename(string $name): string
    {
        $name = pathinfo($name, PATHINFO_BASENAME);
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', $name);
        $name = trim($name, '-.');

        return $name;
    }

    /* ------------------------------------------------------------------ */
    /* Delete                                                               */
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
        if (!$this->deleteTarget) {
            return;
        }

        $path = $this->sanitizePath($this->deleteTarget);

        if (!$this->isAllowedPath($path)) {
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

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            $this->clearTreeCache();
            Notification::make()->title('File deleted')->success()->send();
        } else {
            Notification::make()->title('File not found')->warning()->send();
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

            if (!$this->isAllowedPath($path)) {
                continue;
            }

            if ($this->hasReferences($path)) {
                $blocked[] = basename($path);
                continue;
            }

            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                $deleted++;
            }
        }

        $this->selectedFiles = [];
        $this->selectedFile = null;
        $this->clearTreeCache();

        if (!empty($blocked)) {
            Notification::make()
                ->title('Some files could not be deleted')
                ->body('Blocked: ' . implode(', ', array_slice($blocked, 0, 5)) . (count($blocked) > 5 ? '...' : ''))
                ->warning()
                ->send();
        } elseif ($deleted > 0) {
            Notification::make()
                ->title("Deleted {$deleted} file" . ($deleted !== 1 ? 's' : ''))
                ->success()
                ->send();
        }
    }

    /* ------------------------------------------------------------------ */
    /* Folders                                                              */
    /* ------------------------------------------------------------------ */

    public function createFolder(): void
    {
        $name = $this->sanitizeFilename($this->newFolderName);
        if (!$name) {
            Notification::make()->title('Invalid folder name')->danger()->send();
            return;
        }

        $directory = $this->sanitizePath($this->currentDirectory);
        $newPath = $directory ? $directory . '/' . $name : $name;

        if (!$this->isAllowedPath($newPath)) {
            Notification::make()->title('Invalid path')->danger()->send();
            return;
        }

        if (Storage::disk('public')->exists($newPath)) {
            Notification::make()->title('Folder already exists')->warning()->send();
            return;
        }

        Storage::disk('public')->makeDirectory($newPath);
        $this->newFolderName = '';
        $this->clearTreeCache();

        Notification::make()->title('Folder created')->success()->send();
    }

    public function deleteFolder(string $path): void
    {
        $path = $this->sanitizePath($path);

        if (!$this->isAllowedPath($path)) {
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
        $this->clearTreeCache();

        if ($this->currentDirectory === $path || str_starts_with($this->currentDirectory, $path . '/')) {
            $this->currentDirectory = 'media';
        }

        Notification::make()->title('Folder deleted')->success()->send();
    }

    /* ------------------------------------------------------------------ */
    /* Selection / details panel                                            */
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
    /* Cache helpers                                                        */
    /* ------------------------------------------------------------------ */

    protected function clearTreeCache(): void
    {
        try {
            Cache::forget('filemanager:tree:' . md5(implode(',', $this->allowedPaths())));

            foreach ($this->allowedPaths() as $root) {
                $root = trim($root, '/');
                Cache::forget('filemanager:node:' . md5($root));
            }
        } catch (Throwable) {
        }
    }
}
