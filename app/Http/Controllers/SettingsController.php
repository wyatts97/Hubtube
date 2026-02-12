<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings');
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

        $path = $request->file('avatar')->store("avatars/{$user->id}", 'public');
        $user->update(['avatar' => '/storage/' . $path]);

        return redirect()->route('settings')->with('success', 'Avatar updated successfully.');
    }

    public function updateBanner(Request $request): RedirectResponse
    {
        $request->validate([
            'banner' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();

        // Ensure user has a channel record
        $channel = $user->channel;
        if (!$channel) {
            $user->channel()->create([
                'name' => $user->username,
                'slug' => $user->username,
            ]);
            $channel = $user->channel()->first();
        }

        // Delete old banner if it exists
        if ($channel->banner_image) {
            $oldPath = str_replace('/storage/', '', $channel->banner_image);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $path = $request->file('banner')->store("banners/{$user->id}", 'public');
        $channel->update(['banner_image' => '/storage/' . $path]);

        return redirect()->route('settings')->with('success', 'Banner updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

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

        $user->update([
            'settings' => array_merge($settings, [
                'email_notifications' => $validated['email_notifications'] ?? true,
                'push_notifications' => $validated['push_notifications'] ?? true,
                'subscription_notifications' => $validated['subscription_notifications'] ?? true,
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

    public function deleteAccount(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        DB::beginTransaction();
        try {
            // Delete user's videos and their storage files
            foreach ($user->videos as $video) {
                $videoDir = "videos/{$video->slug}";
                if (Storage::disk('public')->exists($videoDir)) {
                    Storage::disk('public')->deleteDirectory($videoDir);
                }
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
            $user->subscriptions()->delete();
            $user->playlists()->delete();
            $user->notifications()->delete();
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
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Account deletion failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return redirect()->route('settings')->with('error', 'Failed to delete account. Please try again or contact support.');
        }
    }
}
