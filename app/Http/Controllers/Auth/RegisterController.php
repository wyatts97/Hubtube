<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\ChannelService;
use App\Services\EmailService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        ChannelService::createForUser($user);

        event(new Registered($user));

        EmailService::sendToAdmin('admin-new-user', [
            'username' => $user->username,
            'email' => $user->email,
            'registered_at' => now()->toDateTimeString(),
        ]);

        Auth::login($user);

        $requireVerification = Setting::get('email_verification_required', false);
        if ($requireVerification === true || $requireVerification === 'true' || $requireVerification === '1') {
            return redirect()->route('verification.notice');
        }

        return redirect()->route('home')->with('success', 'Welcome to HubTube!');
    }
}
