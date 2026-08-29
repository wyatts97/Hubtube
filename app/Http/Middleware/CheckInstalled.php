<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CheckInstalled
{
    /**
     * Per-request memo for the database probe, so a missing marker file costs at
     * most one query per request rather than one per middleware invocation.
     */
    protected static ?bool $databaseProbe = null;

    /**
     * Redirect to /install if not installed, or block /install if already installed.
     */
    public function handle(Request $request, Closure $next, string $mode = 'require'): Response
    {
        $installed = $this->isInstalled();

        if ($mode === 'block' && $installed) {
            // Already installed — block access to /install
            return redirect('/');
        }

        if ($mode === 'require' && !$installed) {
            // Not installed — redirect to installer (unless already on /install)
            if (!$request->is('install*')) {
                return redirect()->route('install.requirements');
            }
        }

        return $next($request);
    }

    /**
     * Determine whether HubTube has been installed.
     *
     * The marker file is the fast path, but it must not be the only signal. It
     * lives under storage/, so it can go missing on a restored backup, a wiped
     * storage/ directory, or a deploy that does not preserve storage/. Treating
     * that as "not installed" would take a live site down and hand an anonymous
     * visitor an unauthenticated setup wizard that can rewrite .env and create an
     * administrator, so a populated database is accepted as proof of install.
     */
    protected function isInstalled(): bool
    {
        // Escape hatch. Because a populated database now counts as proof of
        // install, a run that failed *after* creating the admin would otherwise
        // lock the wizard shut for good. An operator with shell access can reopen
        // it by creating storage/install-unlock; a visitor arriving over HTTP
        // cannot create that file, so this costs nothing in security terms.
        if (File::exists(storage_path('install-unlock'))) {
            return false;
        }

        if (File::exists(storage_path('installed'))) {
            return true;
        }

        if (!$this->databaseLooksInstalled()) {
            return false;
        }

        // Self-heal the marker so subsequent requests take the cheap path again.
        // A read-only storage/ is survivable here — we simply probe each request.
        try {
            File::put(storage_path('installed'), now()->toDateTimeString());
        } catch (Throwable $e) {
            // Intentionally ignored; the database probe above is authoritative.
        }

        return true;
    }

    /**
     * Does the database contain a usable HubTube installation?
     */
    protected function databaseLooksInstalled(): bool
    {
        if (static::$databaseProbe !== null) {
            return static::$databaseProbe;
        }

        try {
            static::$databaseProbe = Schema::hasTable('users')
                && DB::table('users')->where('is_admin', true)->exists();
        } catch (Throwable $e) {
            // No connection, no schema, or a partially migrated database.
            static::$databaseProbe = false;
        }

        return static::$databaseProbe;
    }

    /**
     * Reset the memo. Used by the installer after finalising, and by tests.
     */
    public static function flushProbeCache(): void
    {
        static::$databaseProbe = null;
    }
}
