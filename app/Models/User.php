<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail, FilamentUser
{
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['is_admin', 'is_pro', 'is_verified', 'wallet_balance', 'email', 'username'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('admin');
    }

    protected $fillable = [
        'username',
        'email',
        'password',
        'first_name',
        'last_name',
        'avatar',
        'cover_image',
        'bio',
        'gender',
        'country',
        'website',
        'age_verified_at',
        'email_verified_at',
        'last_active_at',
        'settings',
    ];

    /**
     * Fields that must NEVER be mass-assignable.
     * is_admin, is_pro, is_verified, wallet_balance
     * must only be set via explicit $user->is_admin = true or admin panel.
     */

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'avatar_url',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'age_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
            'wallet_balance' => 'decimal:2',
            'is_verified' => 'boolean',
            'is_pro' => 'boolean',
            'is_admin' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function channel(): HasOne
    {
        return $this->hasOne(Channel::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'subscriber_id');
    }

    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscription::class, 'channel_id');
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }

    public function favoritePlaylists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class, 'playlist_favorites')
            ->withTimestamps();
    }

    public function watchHistory(): HasMany
    {
        return $this->hasMany(WatchHistory::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function liveStreams(): HasMany
    {
        return $this->hasMany(LiveStream::class);
    }

    public function giftsSent(): HasMany
    {
        return $this->hasMany(GiftTransaction::class, 'sender_id');
    }

    public function giftsReceived(): HasMany
    {
        return $this->hasMany(GiftTransaction::class, 'receiver_id');
    }

    public function complianceRecords(): HasMany
    {
        return $this->hasMany(ComplianceRecord::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    public function isAgeVerified(): bool
    {
        return $this->age_verified_at !== null;
    }

    public function getSubscriberCountAttribute(): int
    {
        // Use cached channel subscriber_count if available, otherwise fall back to live count
        if ($this->relationLoaded('channel') && $this->channel) {
            return $this->channel->subscriber_count ?? 0;
        }

        return $this->channel?->subscriber_count ?? $this->subscribers()->count();
    }

    public function isSubscribedTo(User $user): bool
    {
        return $this->subscriptions()->where('channel_id', $user->id)->exists();
    }

    public function canEditVideo(): bool
    {
        return $this->is_admin || $this->is_pro;
    }

    public function canUpload(): bool
    {
        $limit = $this->is_pro 
            ? (int) Setting::get('max_daily_uploads_pro', 50)
            : (int) Setting::get('max_daily_uploads_free', 5);
            
        $todayUploads = $this->videos()
            ->whereDate('created_at', today())
            ->count();
            
        return $todayUploads < $limit;
    }

    public function canGoLive(): bool
    {
        if ($this->is_pro) {
            return true;
        }
        return (bool) Setting::get('free_users_can_go_live', false);
    }

    public function getMaxVideoSizeAttribute(): int
    {
        $sizeMb = $this->is_pro 
            ? (int) Setting::get('max_upload_size_pro', 5000)
            : (int) Setting::get('max_upload_size_free', 500);
        return $sizeMb * 1048576; // Convert MB to bytes
    }

    public function getFilamentName(): string
    {
        return $this->username ?? $this->email;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url;
    }

    public function getAvatarUrlAttribute(): string
    {
        $raw = $this->attributes['avatar'] ?? null;
        if ($raw) {
            return $raw;
        }
        return '/images/default_avatar.webp';
    }

    public function getNameAttribute(): string
    {
        if ($this->first_name || $this->last_name) {
            return trim($this->first_name . ' ' . $this->last_name);
        }
        return $this->username ?? '';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }
}
