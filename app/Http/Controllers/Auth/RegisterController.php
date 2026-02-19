<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\Channel;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $baseSlug = Str::slug($user->username) ?: 'channel';
        $slug = $baseSlug . '-' . $user->id;
        $suffix = 2;
        while (Channel::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $user->id . '-' . $suffix;
            $suffix++;
        }

        Channel::create([
            'user_id' => $user->id,
            'name' => $user->username,
            'slug' => $slug,
        ]);

        event(new Registered($user));

        Auth::login($user);

        $requireVerification = Setting::get('require_email_verification', 'false');
        if ($requireVerification === 'true' || $requireVerification === '1') {
            return redirect()->route('verification.notice');
        }

        return redirect()->route('home')->with('success', 'Welcome to HubTube!');
    }
}
