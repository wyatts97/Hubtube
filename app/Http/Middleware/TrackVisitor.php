<?php

namespace App\Http\Middleware;

use App\Models\VisitorDaily;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TrackVisitor
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            // Only track GET requests (real page views, not API/AJAX)
            if (!$request->isMethod('GET')) {
                return $response;
            }

            // Skip API routes, Livewire, and obvious asset/XHR requests
            $path = $request->path();
            if (
                str_starts_with($path, 'api/') ||
                str_starts_with($path, 'livewire/') ||
                str_starts_with($path, 'admin/') ||
                $request->hasHeader('X-Livewire')
            ) {
                return $response;
            }

            // Skip AJAX requests that are NOT Inertia (Inertia page loads should be tracked)
            if ($request->ajax() && !$request->hasHeader('X-Inertia')) {
                return $response;
            }

            // Skip static asset extensions
            $ext = pathinfo($request->path(), PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'woff', 'woff2', 'ttf', 'map', 'mp4', 'm3u8', 'ts'])) {
                return $response;
            }

            $ip = $request->ip() ?? '0.0.0.0';
            $ua = $request->userAgent() ?? '';
            $hash = hash('sha256', $ip . $ua);
            $today = now()->toDateString();

            // Upsert: increment if exists, otherwise insert
            DB::table('visitor_daily')->upsert(
                [
                    'visitor_hash' => $hash,
                    'date'         => $today,
                    'visit_count'  => 1,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ],
                ['visitor_hash', 'date'],
                [
                    'visit_count' => DB::raw('visit_count + 1'),
                    'updated_at'  => now(),
                ]
            );
        } catch (\Throwable) {
            // Never let tracking break the request
        }

        return $response;
    }
}
