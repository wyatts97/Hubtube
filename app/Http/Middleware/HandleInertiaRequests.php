<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'username' => $request->user()->username,
                    'email' => $request->user()->email,
                    'avatar' => $request->user()->avatar,
                    'is_verified' => $request->user()->is_verified,
                    'is_pro' => $request->user()->is_pro,
                    'is_admin' => $request->user()->is_admin,
                    'wallet_balance' => $request->user()->wallet_balance,
                    'age_verified' => $request->user()->isAgeVerified(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'app' => [
                'name' => config('app.name'),
                'age_verification_required' => config('hubtube.age_verification_required'),
            ],
        ];
    }
}
