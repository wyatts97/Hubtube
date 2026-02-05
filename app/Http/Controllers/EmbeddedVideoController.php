<?php

namespace App\Http\Controllers;

use App\Models\EmbeddedVideo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmbeddedVideoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = EmbeddedVideo::published();

        // Filter by source site
        if ($request->has('site')) {
            $query->fromSite($request->site);
        }

        // Filter by tag
        if ($request->has('tag')) {
            $query->whereJsonContains('tags', $request->tag);
        }

        // Search
        if ($request->has('q')) {
            $query->where('title', 'like', '%' . $request->q . '%');
        }

        // Sort
        $sortBy = $request->get('sort', 'imported_at');
        $sortDir = $request->get('dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $videos = $query->paginate(24);

        return Inertia::render('EmbeddedVideos/Index', [
            'videos' => $videos,
            'filters' => [
                'site' => $request->site,
                'tag' => $request->tag,
                'q' => $request->q,
                'sort' => $sortBy,
            ],
        ]);
    }

    public function show(EmbeddedVideo $embeddedVideo): Response
    {
        if (!$embeddedVideo->is_published) {
            abort(404);
        }

        // Get related videos from same site or with similar tags
        $related = EmbeddedVideo::published()
            ->where('id', '!=', $embeddedVideo->id)
            ->where(function ($query) use ($embeddedVideo) {
                $query->where('source_site', $embeddedVideo->source_site);
                if (!empty($embeddedVideo->tags)) {
                    foreach (array_slice($embeddedVideo->tags, 0, 3) as $tag) {
                        $query->orWhereJsonContains('tags', $tag);
                    }
                }
            })
            ->inRandomOrder()
            ->limit(12)
            ->get();

        // Append proxied thumbnail URLs
        $videoData = $embeddedVideo->toArray();
        $videoData['proxied_thumbnail_url'] = $embeddedVideo->proxied_thumbnail_url;

        $relatedData = $related->map(function ($v) {
            $arr = $v->toArray();
            $arr['proxied_thumbnail_url'] = $v->proxied_thumbnail_url;
            return $arr;
        });

        return Inertia::render('EmbeddedVideos/Show', [
            'video' => $videoData,
            'related' => $relatedData,
        ]);
    }

    public function featured(): Response
    {
        $videos = EmbeddedVideo::published()
            ->featured()
            ->orderBy('imported_at', 'desc')
            ->limit(20)
            ->get();

        return Inertia::render('EmbeddedVideos/Featured', [
            'videos' => $videos,
        ]);
    }
}
