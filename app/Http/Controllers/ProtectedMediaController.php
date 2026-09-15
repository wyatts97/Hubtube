<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\ProtectedMediaService;
use App\Support\VisitorCountry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves files belonging to private videos.
 *
 * Nginx only sends a /storage/videos/{slug}/… request here when that video's
 * directory carries the privacy marker; everything else is served statically.
 * It arrives by one of two routes, depending on the server layout:
 *
 *  - PHP-FPM behind the same server block: the rewrite leaves REQUEST_URI as
 *    the original /storage/videos/{slug}/{path}, so that route matches.
 *  - A front server block proxying to a PHP one (CloudPanel): the rewritten
 *    /protected-media?path=… is what gets proxied, so that route matches.
 */
class ProtectedMediaController extends Controller
{
    public function __invoke(Request $request, ProtectedMediaService $media, ?string $slug = null, ?string $path = null): Response
    {
        if ($slug === null) {
            // /protected-media?path=/storage/videos/{slug}/{path}
            if (! preg_match('#^/?storage/videos/([^/]+)/(.+)$#', (string) $request->query('path', ''), $m)) {
                abort(404);
            }

            [, $slug, $path] = $m;
        }

        $video = Video::where('slug', $slug)->first();

        // 404 rather than 403 throughout, so probing a slug does not reveal
        // that a private video exists.
        if (! $video || $video->is_embedded || ! $video->isAccessibleBy($request->user())) {
            abort(404);
        }

        $user = $request->user();
        $isOwner = $user && ($user->id === $video->user_id || $user->is_admin);

        if (! $isOwner && $video->isGeoBlockedFor(VisitorCountry::fromRequest($request))) {
            abort(404);
        }

        $resolved = $media->resolveFile($video, $path);

        if ($resolved === null) {
            abort(404);
        }

        [$relative, $absolute] = $resolved;

        return $media->respond($relative, $absolute);
    }
}
