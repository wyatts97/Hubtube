<?php

namespace App\Http\Middleware;

use App\Services\RegistrationGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the "Allow Registration" switch and the IP blocklist.
 *
 * The setting existed in the admin panel but nothing honoured it, so the
 * register form stayed open however it was set.
 */
class EnsureRegistrationOpen
{
    public function __construct(
        protected RegistrationGuard $guard,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->guard->registrationOpen()) {
            return $this->refuse($request, 'New account registration is currently closed.');
        }

        if (! $this->guard->requestAllowed($request)) {
            return $this->refuse($request, 'Registration is not available from your network.');
        }

        return $next($request);
    }

    protected function refuse(Request $request, string $message): Response
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['error' => $message, 'message' => $message], 403);
        }

        return redirect()->route('login')->with('error', $message);
    }
}
