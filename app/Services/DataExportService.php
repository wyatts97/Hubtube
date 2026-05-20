<?php

namespace App\Services;

use InvalidArgumentException;
use ZipArchive;
use RuntimeException;
use Throwable;
use App\Models\Image;
use App\Models\User;
use App\Models\Video;
use App\Services\StorageManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Zip;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportService
{
    private string $tempDisk = 'local';

    /**
     * Export users data in the specified format.
     */
    public function exportUsers(string $format): string
    {
        $users = User::all();
        $filename = "users_export_{$format}_" . now()->format('Y-m-d_H-i-s');

        switch ($format) {
            case 'csv':
                return $this->exportUsersToCsv($users, $filename);
            case 'json':
                return $this->exportUsersToJson($users, $filename);
            case 'sql':
                return $this->exportUsersToSql($users, $filename);
            default:
                throw new InvalidArgumentException("Unsupported format: {$format}");
        }
    }

    /**
     * Export videos and their media files as a ZIP.
     */
    public function exportVideos(): string
    {
        $videos = Video::with('user', 'category')->get();
        $filename = "videos_export_" . now()->format('Y-m-d_H-i-s') . '.zip';
        $tempPath = Storage::disk($this->tempDisk)->path('exports/' . $filename);

        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Failed to create ZIP file");
        }

        // Add metadata JSON
        $zip->addFromString('metadata.json', json_encode($videos->toArray(), JSON_PRETTY_PRINT));

        // Add video files
        foreach ($videos as $video) {
            $videoDir = "videos/{$video->id}/";
            $zip->addEmptyDir($videoDir);
            $disk = $video->storage_disk ?? 'public';

            // Video file
            $this->addFileToZip($zip, $video->video_path, $disk, $videoDir . basename($video->video_path));

            // Thumbnail
            $this->addFileToZip($zip, $video->thumbnail, $disk, $videoDir . basename($video->thumbnail));
        }

        $zip->close();

        return "exports/{$filename}";
    }

    /**
     * Export images and their media files as a ZIP.
     */
    public function exportImages(): string
    {
        $images = Image::with('user', 'category')->get();
        $filename = "images_export_" . now()->format('Y-m-d_H-i-s') . '.zip';
        $tempPath = Storage::disk($this->tempDisk)->path('exports/' . $filename);

        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Failed to create ZIP file");
        }

        // Add metadata JSON
        $zip->addFromString('metadata.json', json_encode($images->toArray(), JSON_PRETTY_PRINT));

        // Add image files
        foreach ($images as $image) {
            $imageDir = "images/{$image->uuid}/";
            $zip->addEmptyDir($imageDir);
            $disk = $image->storage_disk ?? 'public';

            // Original file
            $this->addFileToZip($zip, $image->file_path, $disk, $imageDir . basename($image->file_path));

            // Thumbnail
            $this->addFileToZip($zip, $image->thumbnail_path, $disk, $imageDir . basename($image->thumbnail_path));
        }

        $zip->close();

        return "exports/{$filename}";
    }

    /**
     * Add a file to the ZIP archive using the correct disk.
     * Uses addFile() for local disks (memory efficient) and streams for cloud disks.
     */
    private function addFileToZip(ZipArchive $zip, ?string $path, string $disk, string $zipPath): void
    {
        if (!$path) {
            return;
        }

        try {
            if (!StorageManager::exists($path, $disk)) {
                return;
            }
        } catch (Throwable $e) {
            return;
        }

        $localPath = null;

        if (in_array($disk, ['public', 'local'])) {
            try {
                $localPath = Storage::disk($disk)->path($path);
            } catch (Throwable $e) {
                $localPath = null;
            }
        }

        if ($localPath && file_exists($localPath)) {
            $zip->addFile($localPath, $zipPath);
            return;
        }

        // Cloud disk: stream to a temporary file
        try {
            $stream = Storage::disk($disk)->readStream($path);
            if (!$stream) {
                return;
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'ht_export_');
            $dest = fopen($tempFile, 'w');
            stream_copy_to_stream($stream, $dest);
            fclose($stream);
            fclose($dest);

            $zip->addFile($tempFile, $zipPath);
            unlink($tempFile);
        } catch (Throwable $e) {
            // Silently skip files that can't be read from cloud storage
        }
    }

    /**
     * Generate download response for a file.
     */
    public function downloadFile(string $path, string $downloadFilename): StreamedResponse
    {
        $fullPath = Storage::disk($this->tempDisk)->path($path);

        return response()->streamDownload(function () use ($fullPath) {
            readfile($fullPath);
        }, $downloadFilename);
    }

    /**
     * Clean up temporary export files older than 1 hour.
     */
    public function cleanupOldExports(): void
    {
        $exports = Storage::disk($this->tempDisk)->files('exports');
        $cutoff = now()->subHour();

        foreach ($exports as $export) {
            $lastModified = Storage::disk($this->tempDisk)->lastModified($export);
            if ($lastModified < $cutoff->timestamp) {
                Storage::disk($this->tempDisk)->delete($export);
            }
        }
    }

    /**
     * Export users to CSV format.
     */
    private function exportUsersToCsv($users, string $filename): string
    {
        $filepath = "exports/{$filename}.csv";
        $handle = fopen('php://temp', 'r+');

        // CSV header
        fputcsv($handle, ['id', 'username', 'email', 'first_name', 'last_name', 'role', 'is_verified', 'created_at', 'password']);

        // CSV rows
        foreach ($users as $user) {
            fputcsv($handle, [
                $user->id,
                $user->username,
                $user->email,
                $user->first_name ?? '',
                $user->last_name ?? '',
                $user->role ?? 'user',
                $user->is_verified ? 'true' : 'false',
                $user->created_at?->toDateTimeString(),
                $user->password,
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        Storage::disk($this->tempDisk)->put($filepath, $content);

        return $filepath;
    }

    /**
     * Export users to JSON format.
     */
    private function exportUsersToJson($users, string $filename): string
    {
        $filepath = "exports/{$filename}.json";
        $data = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'role' => $user->role,
                'is_verified' => $user->is_verified,
                'created_at' => $user->created_at?->toDateTimeString(),
                'password' => $user->password,
            ];
        });

        Storage::disk($this->tempDisk)->put($filepath, json_encode($data, JSON_PRETTY_PRINT));

        return $filepath;
    }

    /**
     * Export users to SQL INSERT statements.
     */
    private function exportUsersToSql($users, string $filename): string
    {
        $filepath = "exports/{$filename}.sql";
        $statements = ["-- Users Export - " . now()->toDateTimeString() . "\n"];

        foreach ($users as $user) {
            $escaped = [
                DB::getPdo()->quote($user->username),
                DB::getPdo()->quote($user->email),
                DB::getPdo()->quote($user->first_name ?? ''),
                DB::getPdo()->quote($user->last_name ?? ''),
                DB::getPdo()->quote($user->role ?? 'user'),
                $user->is_verified ? 1 : 0,
                DB::getPdo()->quote($user->created_at?->toDateTimeString() ?? now()),
                DB::getPdo()->quote($user->password),
            ];

            $statements[] = "INSERT INTO users (username, email, first_name, last_name, role, is_verified, created_at, password) VALUES ({$escaped[0]}, {$escaped[1]}, {$escaped[2]}, {$escaped[3]}, {$escaped[4]}, {$escaped[5]}, {$escaped[6]}, {$escaped[7]});";
        }

        Storage::disk($this->tempDisk)->put($filepath, implode("\n", $statements));

        return $filepath;
    }
}
