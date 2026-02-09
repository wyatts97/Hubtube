<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class CheckInstalled
{
    /**
     * Redirect to /install if not installed, or block /install if already installed.
     */
    public function handle(Request $request, Closure $next, string $mode = 'require'): Response
    {
        $installed = File::exists(storage_path('installed'));

        if ($mode === 'block' && $installed) {
            // Already installed — block access to /install
            return redirect('/');
        }

        if ($mode === 'block' && !$installed) {
            // During installation, force file-based sessions so CSRF works
            // even when Redis or database drivers aren't configured yet.
            config(['session.driver' => 'file']);
        }

        if ($mode === 'require' && !$installed) {
            // Not installed — redirect to installer (unless already on /install)
            if (!$request->is('install*')) {
                return redirect()->route('install.requirements');
            }
        }

        return $next($request);
    }
}
