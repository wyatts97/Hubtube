<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

        return back()->with('success', 'Profile updated successfully.');
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

        return back()->with('success', 'Password updated successfully.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email_notifications' => ['boolean'],
            'push_notifications' => ['boolean'],
            'subscription_notifications' => ['boolean'],
        ]);

        $request->user()->update($validated);

        return back()->with('success', 'Notification preferences updated.');
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

        return back()->with('success', 'Privacy settings updated.');
    }
}
