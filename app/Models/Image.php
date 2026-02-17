<?php

namespace App\Models;

use App\Services\StorageManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Image extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['is_approved', 'privacy'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('admin');
    }

    protected $fillable = [
        'user_id',
        'uuid',
        'title',
        'description',
        'file_path',
        'thumbnail_path',
        'storage_disk',
        'mime_type',
        'width',
        'height',
        'file_size',
        'is_animated',
        'blurhash',
        'privacy',
        'is_approved',
        'views_count',
        'likes_count',
        'category_id',
        'tags',
        'published_at',
    ];

    protected $appends = [
        'image_url',
        'thumbnail_url',
        'formatted_size',
    ];

    protected function casts(): array
    {
        return [
            'is_animated' => 'boolean',
            'is_approved' => 'boolean',
            'tags' => 'array',
            'published_at' => 'datetime',
            'width' => 'integer',
            'height' => 'integer',
            'file_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Image $image) {
            $disk = $image->storage_disk ?? 'public';
            $dir = dirname($image->file_path);

            if ($image->file_path && StorageManager::exists($image->file_path, $disk)) {
                // Delete the entire image directory (original + variants)
                if ($dir && $dir !== '.' && StorageManager::exists($dir, $disk)) {
                    StorageManager::deleteDirectory($dir, $disk);
                } else {
                    StorageManager::delete($image->file_path, $disk);
                }
            }

            if ($image->thumbnail_path && StorageManager::exists($image->thumbnail_path, $disk)) {
                StorageManager::delete($image->thumbnail_path, $disk);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function galleries(): BelongsToMany
    {
        return $this->belongsToMany(Gallery::class, 'gallery_image')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function scopePublic($query)
    {
        return $query->where('privacy', 'public');
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function isAccessibleBy(?User $user): bool
    {
        if ($this->privacy === 'public') {
            return true;
        }

        if (!$user) {
            return false;
        }

        if ($this->user_id === $user->id) {
            return true;
        }

        if ($this->privacy === 'private') {
            return false;
        }

        return true;
    }

    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return StorageManager::url($this->file_path, $this->storage_disk ?? 'public');
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path) {
            return $this->image_url;
        }

        return StorageManager::url($this->thumbnail_path, $this->storage_disk ?? 'public');
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        return number_format($bytes / 1024, 2) . ' KB';
    }
}
