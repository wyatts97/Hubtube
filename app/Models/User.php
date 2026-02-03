<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail, FilamentUser
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

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
        'wallet_balance',
        'is_verified',
        'is_pro',
        'is_admin',
        'two_factor_enabled',
        'two_factor_secret',
        'age_verified_at',
        'email_verified_at',
        'last_active_at',
        'settings',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
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
            'two_factor_enabled' => 'boolean',
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
        return $this->subscribers()->count();
    }

    public function isSubscribedTo(User $user): bool
    {
        return $this->subscriptions()->where('channel_id', $user->id)->exists();
    }

    public function canUpload(): bool
    {
        $limit = $this->is_pro 
            ? config('hubtube.limits.pro.daily_uploads')
            : config('hubtube.limits.free.daily_uploads');
            
        $todayUploads = $this->videos()
            ->whereDate('created_at', today())
            ->count();
            
        return $todayUploads < $limit;
    }

    public function canGoLive(): bool
    {
        if ($this->is_pro) {
            return config('hubtube.limits.pro.can_go_live');
        }
        return config('hubtube.limits.free.can_go_live');
    }

    public function getMaxVideoSizeAttribute(): int
    {
        return $this->is_pro 
            ? config('hubtube.limits.pro.max_video_size')
            : config('hubtube.limits.free.max_video_size');
    }

    public function getFilamentName(): string
    {
        return $this->username ?? $this->email;
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
