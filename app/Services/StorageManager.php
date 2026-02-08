<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class StorageManager
{
    /**
     * Wasabi region-to-endpoint mapping per Wasabi docs.
     * @see https://docs.wasabi.com/docs/service-urls-for-wasabis-storage-regions
     */
    public const WASABI_ENDPOINTS = [
        'us-east-1'      => 'https://s3.wasabisys.com',
        'us-east-2'      => 'https://s3.us-east-2.wasabisys.com',
        'us-central-1'   => 'https://s3.us-central-1.wasabisys.com',
        'us-west-1'      => 'https://s3.us-west-1.wasabisys.com',
        'us-west-2'      => 'https://s3.us-west-2.wasabisys.com',
        'ca-central-1'   => 'https://s3.ca-central-1.wasabisys.com',
        'eu-central-1'   => 'https://s3.eu-central-1.wasabisys.com',
        'eu-central-2'   => 'https://s3.eu-central-2.wasabisys.com',
        'eu-west-1'      => 'https://s3.eu-west-1.wasabisys.com',
        'eu-west-2'      => 'https://s3.eu-west-2.wasabisys.com',
        'eu-west-3'      => 'https://s3.eu-west-3.wasabisys.com',
        'eu-south-1'     => 'https://s3.eu-south-1.wasabisys.com',
        'ap-northeast-1' => 'https://s3.ap-northeast-1.wasabisys.com',
        'ap-northeast-2' => 'https://s3.ap-northeast-2.wasabisys.com',
        'ap-southeast-1' => 'https://s3.ap-southeast-1.wasabisys.com',
        'ap-southeast-2' => 'https://s3.ap-southeast-2.wasabisys.com',
    ];

    /**
     * Get the name of the active storage disk based on admin settings.
     * Returns a cloud disk only if cloud offloading is enabled.
     */
    public static function getActiveDiskName(): string
    {
        if (!Setting::get('cloud_offloading_enabled', false)) {
            return 'public';
        }

        $driver = Setting::get('storage_driver', 'local');

        return match ($driver) {
            'wasabi' => 'wasabi',
            'b2'     => 'b2',
            's3'     => 's3',
            default  => 'public',
        };
    }

    /**
     * Get the active storage disk instance.
     * When Wasabi is selected, dynamically configures the disk from admin settings
     * so credentials saved in the DB override .env values.
     */
    public static function disk(?string $diskName = null): Filesystem
    {
        $diskName = $diskName ?? static::getActiveDiskName();

        if ($diskName === 'wasabi') {
            return static::buildWasabiDisk();
        }

        return Storage::disk($diskName);
    }

    /**
     * Build a Wasabi S3 disk at runtime using admin-saved credentials.
     * All values come from the Setting model (admin panel).
     */
    protected static function buildWasabiDisk(): Filesystem
    {
        $key      = Setting::get('wasabi_key', '');
        $secret   = Setting::get('wasabi_secret', '');
        $region   = Setting::get('wasabi_region', 'us-east-1');
        $bucket   = Setting::get('wasabi_bucket', '');
        $endpoint = Setting::get('wasabi_endpoint', '');

        // Auto-resolve endpoint from region if not explicitly set
        if (empty($endpoint)) {
            $endpoint = static::WASABI_ENDPOINTS[$region] ?? 'https://s3.wasabisys.com';
        }

        // Build the public URL for the bucket
        $url = static::getWasabiPublicUrl($bucket, $region, $endpoint);

        config([
            'filesystems.disks.wasabi' => [
                'driver'                  => 's3',
                'key'                     => $key,
                'secret'                  => $secret,
                'region'                  => $region,
                'bucket'                  => $bucket,
                'endpoint'                => $endpoint,
                'url'                     => $url,
                'use_path_style_endpoint' => false,
                'visibility'              => 'public',
                'throw'                   => true,
            ],
        ]);

        // Purge the cached disk so Laravel rebuilds it with new config
        Storage::forgetDisk('wasabi');

        return Storage::disk('wasabi');
    }

    /**
     * Get the public URL base for a Wasabi bucket.
     * Wasabi public URLs follow: https://<bucket>.s3.<region>.wasabisys.com
     */
    public static function getWasabiPublicUrl(string $bucket, string $region, ?string $endpoint = null): string
    {
        // CDN URL takes priority if configured
        $cdnUrl = Setting::get('cdn_url', '');
        if (!empty($cdnUrl) && Setting::get('cdn_enabled', false)) {
            return rtrim($cdnUrl, '/');
        }

        // Wasabi virtual-hosted style: https://<bucket>.s3.<region>.wasabisys.com
        if ($region === 'us-east-1') {
            return "https://{$bucket}.s3.wasabisys.com";
        }

        return "https://{$bucket}.s3.{$region}.wasabisys.com";
    }

    /**
     * Get the public URL for a file stored on the active disk.
     */
    public static function url(string $path, ?string $diskName = null): string
    {
        $diskName = $diskName ?? static::getActiveDiskName();

        if ($diskName === 'public') {
            // CDN override for local storage
            if (Setting::get('cdn_enabled', false)) {
                $cdnUrl = Setting::get('cdn_url', '');
                if (!empty($cdnUrl)) {
                    return rtrim($cdnUrl, '/') . '/' . ltrim($path, '/');
                }
            }
            return asset('storage/' . $path);
        }

        try {
            return static::disk($diskName)->url($path);
        } catch (\Throwable $e) {
            Log::warning('StorageManager: failed to generate URL', [
                'disk' => $diskName,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            // Fallback to local
            return asset('storage/' . $path);
        }
    }

    /**
     * Generate a temporary (pre-signed) URL for private files.
     * Useful for paid/private videos.
     */
    public static function temporaryUrl(string $path, int $minutes = 60, ?string $diskName = null): string
    {
        $diskName = $diskName ?? static::getActiveDiskName();

        if ($diskName === 'public') {
            return asset('storage/' . $path);
        }

        try {
            return static::disk($diskName)->temporaryUrl($path, now()->addMinutes($minutes));
        } catch (\Throwable $e) {
            Log::warning('StorageManager: temporaryUrl failed, falling back to url()', [
                'disk' => $diskName,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return static::url($path, $diskName);
        }
    }

    /**
     * Check if the active disk is a cloud/S3 disk.
     */
    public static function isCloudDisk(?string $diskName = null): bool
    {
        $diskName = $diskName ?? static::getActiveDiskName();
        return in_array($diskName, ['wasabi', 'b2', 's3']);
    }

    /**
     * Test connection to the active cloud storage.
     * Returns ['success' => bool, 'message' => string]
     */
    public static function testConnection(?string $diskName = null): array
    {
        $diskName = $diskName ?? static::getActiveDiskName();

        if ($diskName === 'public') {
            return ['success' => true, 'message' => 'Local storage is always available.'];
        }

        try {
            $disk = static::disk($diskName);
            $testFile = '.hubtube-connection-test-' . time();

            // Write test file
            $disk->put($testFile, 'HubTube connection test');

            // Verify it exists
            if (!$disk->exists($testFile)) {
                return ['success' => false, 'message' => 'File was written but could not be verified.'];
            }

            // Read it back
            $content = $disk->get($testFile);
            if ($content !== 'HubTube connection test') {
                $disk->delete($testFile);
                return ['success' => false, 'message' => 'File content mismatch after write.'];
            }

            // Clean up
            $disk->delete($testFile);

            return ['success' => true, 'message' => "Successfully connected to {$diskName} storage."];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => "Connection failed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Get the Wasabi endpoint URL for a given region.
     */
    public static function getWasabiEndpoint(string $region): string
    {
        return static::WASABI_ENDPOINTS[$region] ?? 'https://s3.wasabisys.com';
    }

    /**
     * Upload a file to the active storage disk.
     * Returns the stored path on success.
     */
    public static function putFile(string $directory, $file, ?string $diskName = null): string|false
    {
        $diskName = $diskName ?? static::getActiveDiskName();

        try {
            $disk = static::disk($diskName);
            return $disk->putFile($directory, $file, 'public');
        } catch (\Throwable $e) {
            Log::error('StorageManager: putFile failed', [
                'disk' => $diskName,
                'directory' => $directory,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Store file contents at a specific path.
     */
    public static function put(string $path, $contents, ?string $diskName = null): bool
    {
        $diskName = $diskName ?? static::getActiveDiskName();

        try {
            $disk = static::disk($diskName);
            return $disk->put($path, $contents, 'public');
        } catch (\Throwable $e) {
            Log::error('StorageManager: put failed', [
                'disk' => $diskName,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Delete a file from storage.
     */
    public static function delete(string $path, ?string $diskName = null): bool
    {
        $diskName = $diskName ?? static::getActiveDiskName();

        try {
            return static::disk($diskName)->delete($path);
        } catch (\Throwable $e) {
            Log::warning('StorageManager: delete failed', [
                'disk' => $diskName,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Delete a directory from storage.
     */
    public static function deleteDirectory(string $path, ?string $diskName = null): bool
    {
        $diskName = $diskName ?? static::getActiveDiskName();

        try {
            return static::disk($diskName)->deleteDirectory($path);
        } catch (\Throwable $e) {
            Log::warning('StorageManager: deleteDirectory failed', [
                'disk' => $diskName,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if a file exists on the given disk.
     */
    public static function exists(string $path, ?string $diskName = null): bool
    {
        $diskName = $diskName ?? static::getActiveDiskName();

        try {
            return static::disk($diskName)->exists($path);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get all files in a directory.
     */
    public static function allFiles(string $directory, ?string $diskName = null): array
    {
        $diskName = $diskName ?? static::getActiveDiskName();

        try {
            return static::disk($diskName)->allFiles($directory);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get the local filesystem path for a file.
     * Only works for local disks. For cloud disks, downloads to temp.
     */
    public static function localPath(string $storagePath, ?string $diskName = null): ?string
    {
        $diskName = $diskName ?? static::getActiveDiskName();

        if ($diskName === 'public') {
            return Storage::disk('public')->path($storagePath);
        }

        // For cloud disks, download to a temp file for FFmpeg processing
        try {
            $disk = static::disk($diskName);
            $tempPath = storage_path('app/temp/' . basename($storagePath));
            $tempDir = dirname($tempPath);

            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $stream = $disk->readStream($storagePath);
            if ($stream) {
                file_put_contents($tempPath, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                return $tempPath;
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('StorageManager: localPath download failed', [
                'disk' => $diskName,
                'path' => $storagePath,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Upload a local file to cloud storage.
     * Used after FFmpeg processing to push processed files to Wasabi.
     */
    public static function uploadLocalFile(string $localPath, string $storagePath, ?string $diskName = null): bool
    {
        $diskName = $diskName ?? static::getActiveDiskName();

        if ($diskName === 'public') {
            return true; // Already on local disk
        }

        try {
            $disk = static::disk($diskName);
            $stream = fopen($localPath, 'r');

            if (!$stream) {
                return false;
            }

            $result = $disk->put($storagePath, $stream, 'public');

            if (is_resource($stream)) {
                fclose($stream);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('StorageManager: uploadLocalFile failed', [
                'disk' => $diskName,
                'localPath' => $localPath,
                'storagePath' => $storagePath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Clean up temporary local files created during cloud processing.
     */
    public static function cleanupTemp(): void
    {
        $tempDir = storage_path('app/temp');
        if (is_dir($tempDir)) {
            $files = glob("{$tempDir}/*");
            foreach ($files as $file) {
                if (is_file($file) && (time() - filemtime($file)) > 3600) {
                    unlink($file);
                }
            }
        }
    }
}
