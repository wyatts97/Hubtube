<?php

namespace App\Models;

use App\Services\ChannelService;
use App\Services\EmailService;
use Illuminate\Support\Facades\URL;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail, FilamentUser
{
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, Billable;

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
     * is_admin, is_pro, is_verified, wallet_balance,
     * points_balance, pro_expires_at, pro_source
     * must only be set via forceFill()/explicit assignment or admin panel.
     */

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'points_balance' => 'integer',
            'pro_expires_at' => 'datetime',
            'settings' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
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

    public function channelSubscriptions(): HasMany
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

    /**
     * The app's own in-app notification feed (bell in the front-end UI).
     *
     * Named distinctly from notifications() because that method name is
     * claimed by Illuminate\Notifications\Notifiable's HasDatabaseNotifications
     * trait, which Filament's admin database-notification bell relies on
     * (its Livewire component calls $user->notifications() directly). See
     * notifications() below and the filament_notifications migration.
     */
    public function appNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Overrides Notifiable's HasDatabaseNotifications::notifications() to
     * back Filament's admin database-notification bell with a dedicated
     * FilamentNotification model/table instead of the standard
     * Illuminate\Notifications\DatabaseNotification (which defaults to a
     * `notifications` table — already taken by App\Models\Notification's
     * incompatible schema, see appNotifications() above).
     *
     * @return MorphMany<FilamentNotification, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(FilamentNotification::class, 'notifiable')->latest();
    }

    /**
     * Local scope: admin users, per canAccessPanel()/is_admin.
     */
    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('is_admin', true);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function pointsTransactions(): HasMany
    {
        return $this->hasMany(PointsTransaction::class);
    }

    public function pointsRedemptions(): HasMany
    {
        return $this->hasMany(PointsRedemption::class);
    }

    public function ccbillSubscriptions(): HasMany
    {
        return $this->hasMany(CCBillSubscription::class);
    }

    /**
     * Whether the user has any active CCBill subscription granting Pro access.
     */
    public function hasActiveCCBillSubscription(): bool
    {
        return $this->ccbillSubscriptions()
            ->whereNotIn('status', [
                CCBillSubscription::STATUS_EXPIRED,
                CCBillSubscription::STATUS_REFUNDED,
                CCBillSubscription::STATUS_CHARGEBACK,
            ])
            ->where(function ($q) {
                $q->whereNull('current_period_end')
                    ->orWhere('current_period_end', '>', now());
            })
            ->exists();
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

    public function hasTwoFactorEnabled(): bool
    {
        return !is_null($this->two_factor_secret) && !is_null($this->two_factor_confirmed_at);
    }

    /**
     * Consume a recovery code if valid, removing it so it cannot be reused.
     */
    public function consumeRecoveryCode(string $code): bool
    {
        $codes = $this->two_factor_recovery_codes ?? [];
        $index = array_search($code, $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $this->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }

    public function isAgeVerified(): bool
    {
        return $this->age_verified_at !== null;
    }

    /**
     * The user's channel row, creating it if it is somehow missing.
     *
     * UserObserver covers normal creation and a backfill migration covered
     * existing rows, but the relation can still be null: saveQuietly() and
     * Event::fake() both bypass observers, and a deploy can serve traffic
     * before the backfill has run.
     *
     * Callers must use this rather than $user->channel?->... — that null-safe
     * chain is what silently discarded subscriber-count increments.
     */
    public function ensureChannel(): Channel
    {
        if ($this->relationLoaded('channel') && $this->channel) {
            return $this->channel;
        }

        $channel = $this->channel()->first() ?? ChannelService::createForUser($this);

        $this->setRelation('channel', $channel);

        return $channel;
    }

    /**
     * Number of videos of this user that are actually visible on the channel.
     *
     * ChannelController::show() used $videos->total() (which filters
     * ->processed()) while about() used ->public()->approved()->count(), so
     * the header and the About tab disagreed. This is the one definition.
     */
    public function publicVideoCount(): int
    {
        return $this->videos()->public()->approved()->processed()->count();
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
        return $this->channelSubscriptions()->where('channel_id', $user->id)->exists();
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

    /**
     * Only admins may impersonate other users (stechstudio/filament-impersonate
     * checks this method on the currently authenticated user).
     */
    public function canImpersonate(): bool
    {
        return $this->is_admin;
    }

    /**
     * Admins may never be impersonated, avoiding privilege ping-pong / confusion
     * between two admin sessions (stechstudio/filament-impersonate checks this
     * method on the target user).
     */
    public function canBeImpersonated(): bool
    {
        return !$this->is_admin;
    }

    /**
     * Override Laravel's default email verification notification
     * to use our custom template system.
     */
    public function sendEmailVerificationNotification(): void
    {
        $enabled = Setting::get('email_notify_verify-email', 'true');
        if ($enabled === 'false' || $enabled === '0') {
            return;
        }

        if (!EmailService::isMailConfigured()) {
            // Fall back to Laravel's default if mail isn't configured via admin panel
            parent::sendEmailVerificationNotification();
            return;
        }

        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())]
        );

        EmailService::sendToUser('verify-email', $this->email, [
            'username' => $this->username ?? 'there',
            'verify_url' => $verifyUrl,
        ]);
    }

    /**
     * Override Laravel's default password reset notification
     * to use our custom template system.
     */
    public function sendPasswordResetNotification($token): void
    {
        $enabled = Setting::get('email_notify_reset-password', 'true');
        if ($enabled === 'false' || $enabled === '0') {
            return;
        }

        if (!EmailService::isMailConfigured()) {
            parent::sendPasswordResetNotification($token);
            return;
        }

        $resetUrl = url(route('password.reset', ['token' => $token, 'email' => $this->email], false));

        EmailService::sendToUser('reset-password', $this->email, [
            'username' => $this->username ?? 'there',
            'reset_url' => $resetUrl,
            'expiry_minutes' => config('auth.passwords.users.expire', 60),
        ]);
    }
}
