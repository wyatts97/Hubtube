<?php

namespace App\Http\Controllers;

use Throwable;
use RuntimeException;
use App\Services\WordPressPasswordHasher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use App\Models\Setting;
use App\Http\Requests\UpdateSocialLinksRequest;
use App\Services\SocialLinkService;
use App\Services\UserDataExportService;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Settings', [
            'adminNotificationSettings' => [
                'email_notifications' => (bool) filter_var(Setting::get('email_notify_new-subscriber', true), FILTER_VALIDATE_BOOLEAN),
                'subscription_notifications' => (bool) filter_var(Setting::get('email_notify_new-subscriber', true), FILTER_VALIDATE_BOOLEAN),
            ],
            'twoFactorEnabled' => $request->user()->hasTwoFactorEnabled(),
            // Page-scoped rather than a global shared prop: only this form
            // needs the platform list, and shared props are built on every
            // request.
            'socialPlatforms' => collect(config('social_links'))
                ->map(fn (array $config, string $key) => [
                    'value' => $key,
                    'label' => $config['label'],
                    'freeform' => ($config['hosts'] ?? null) === null,
                ])
                ->values()
                ->all(),
            'socialLinksEnabled' => app(SocialLinkService::class)->outboundLinksEnabled(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', 'unique:users,username,' . $request->user()->id],
            'email' => ['required', 'email', 'unique:users,email,' . $request->user()->id],
            'bio' => ['nullable', 'string', 'max:500'],
        ]);

        $request->user()->update($validated);

        return redirect()->route('settings')->with('success', 'Profile updated successfully.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ]);

        $user = $request->user();

        // Delete old avatar if it exists
        if ($user->avatar) {
            $oldPath = str_replace('/storage/', '', $user->avatar);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        try {
            $manager = new ImageManager(new GdDriver());
            $image   = $manager->read($request->file('avatar')->getPathname());
            $image->cover(256, 256);
            $webp    = (string) $image->toWebp(85);

            $relativePath = "avatars/{$user->id}/avatar.webp";
            Storage::disk('public')->put($relativePath, $webp);
            $path = $relativePath;
        } catch (Throwable $e) {
            Log::warning('Avatar WebP conversion failed, using original', ['error' => $e->getMessage()]);
            $path = $request->file('avatar')->store("avatars/{$user->id}", 'public');
        }

        $user->update(['avatar' => '/storage/' . $path]);

        return redirect()->route('settings')->with('success', 'Avatar updated successfully.');
    }

    public function updateBanner(Request $request): RedirectResponse
    {
        $request->validate([
            // The banner is the channel page's LCP element, so bound the
            // dimensions as well as the byte size — a 4096px-wide PNG served
            // raw on every channel view is a real performance cost.
            'banner' => [
                'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120',
                'dimensions:min_width=1280,max_width=4096',
            ],
        ]);

        $user = $request->user();

        // Not $user->channel: that can be null (saveQuietly, or a deploy
        // serving traffic before the backfill migration runs). ensureChannel()
        // creates the row through ChannelService, which owns slug uniqueness —
        // the inline version here previously set slug = username, which
        // collides with the unique index.
        $channel = $user->ensureChannel();

        // Delete old banner if it exists
        if ($channel->banner_image) {
            $oldPath = str_replace('/storage/', '', $channel->banner_image);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        // Re-encode to WebP the same way updateAvatar does. The banner was
        // previously stored as the raw upload.
        try {
            $manager = new ImageManager(new GdDriver());
            $image = $manager->read($request->file('banner')->getPathname());
            $image->scaleDown(width: 2048);
            $webp = (string) $image->toWebp(82);

            $relativePath = "banners/{$user->id}/banner.webp";
            Storage::disk('public')->put($relativePath, $webp);
            $path = $relativePath;
        } catch (Throwable $e) {
            Log::warning('Banner WebP conversion failed, using original', ['error' => $e->getMessage()]);
            $path = $request->file('banner')->store("banners/{$user->id}", 'public');
        }

        $channel->update(['banner_image' => '/storage/' . $path]);

        return redirect()->route('settings')->with('success', 'Banner updated successfully.');
    }

    public function destroyBanner(Request $request): RedirectResponse
    {
        $channel = $request->user()->ensureChannel();

        if ($channel->banner_image) {
            $oldPath = str_replace('/storage/', '', $channel->banner_image);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $channel->update(['banner_image' => null]);

        return redirect()->route('settings')->with('success', 'Banner removed.');
    }

    public function updateSocialLinks(
        UpdateSocialLinksRequest $request,
        SocialLinkService $socialLinks,
    ): RedirectResponse {
        $request->user()->ensureChannel()->update([
            'social_links' => $socialLinks->normalize($request->validated('social_links', [])),
        ]);

        return redirect()->route('settings')->with('success', 'Links updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (!$this->isValidCurrentPassword($request->user(), $validated['current_password'])) {
            throw ValidationException::withMessages([
                'current_password' => __('The provided password does not match your current password.'),
            ]);
        }

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('settings')->with('success', 'Password updated successfully.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email_notifications' => ['boolean'],
            'push_notifications' => ['boolean'],
            'subscription_notifications' => ['boolean'],
        ]);

        $user = $request->user();
        $settings = $user->settings ?? [];

        $emailNotifications = array_key_exists('email_notifications', $validated)
            ? (bool) $validated['email_notifications']
            : (bool) ($settings['email_notifications'] ?? true);

        $pushNotifications = array_key_exists('push_notifications', $validated)
            ? (bool) $validated['push_notifications']
            : (bool) ($settings['push_notifications'] ?? true);

        $subscriptionNotifications = array_key_exists('subscription_notifications', $validated)
            ? (bool) $validated['subscription_notifications']
            : (bool) ($settings['subscription_notifications'] ?? true);

        $user->update([
            'settings' => array_merge($settings, [
                'email_notifications' => $emailNotifications,
                'push_notifications' => $pushNotifications,
                'subscription_notifications' => $subscriptionNotifications,
            ]),
        ]);

        return redirect()->route('settings')->with('success', 'Notification preferences updated.');
    }

    public function updatePrivacy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'private_profile' => ['boolean'],
            'show_watch_history' => ['boolean'],
            'show_liked_videos' => ['boolean'],
            'allow_comments' => ['boolean'],
        ]);

        $user = $request->user();
        $settings = $user->settings ?? [];
        
        $user->update([
            'settings' => array_merge($settings, $validated),
        ]);

        return redirect()->route('settings')->with('success', 'Privacy settings updated.');
    }

    public function exportData(Request $request, UserDataExportService $exporter)
    {
        $user = $request->user();
        $data = $exporter->build($user);
        $filename = $exporter->filename($user);

        Log::info('User requested data export', ['user_id' => $user->id]);

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function deleteAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (!$this->isValidCurrentPassword($request->user(), $validated['password'])) {
            throw ValidationException::withMessages([
                'password' => __('The provided password does not match your current password.'),
            ]);
        }

        $user = $request->user();

        DB::beginTransaction();
        try {
            // Delete user's videos (model hooks clean up files on the correct storage disk)
            foreach ($user->videos as $video) {
                $video->forceDelete();
            }

            // Delete channel
            if ($user->channel) {
                $user->channel->delete();
            }

            // Delete avatar and banner files
            if ($user->avatar) {
                $avatarPath = str_replace('/storage/', '', $user->avatar);
                Storage::disk('public')->delete($avatarPath);
            }

            // Clean up related records (comments, likes, subscriptions, etc.)
            $user->comments()->delete();
            $user->likes()->delete();
            $user->channelSubscriptions()->delete();
            $user->playlists()->delete();
            $user->appNotifications()->delete();
            $user->walletTransactions()->delete();

            // Log out before deleting
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Delete the user
            $user->delete();

            DB::commit();

            Log::info('User account deleted', ['user_id' => $user->id, 'username' => $user->username]);

            return redirect()->route('home')->with('success', 'Your account has been permanently deleted.');
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Account deletion failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return redirect()->route('settings')->with('error', 'Failed to delete account. Please try again or contact support.');
        }
    }

    private function isValidCurrentPassword($user, string $plainPassword): bool
    {
        try {
            if (Hash::check($plainPassword, $user->password)) {
                return true;
            }
        } catch (RuntimeException) {
            // Non-bcrypt hash, fall through to WP hasher check.
        }

        if (!WordPressPasswordHasher::isWordPressHash($user->password)) {
            return false;
        }

        return (new WordPressPasswordHasher())->check($plainPassword, $user->password);
    }
}
