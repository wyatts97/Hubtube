<?php

namespace App\Http\Controllers;

use App\Http\Requests\Video\StoreVideoRequest;
use App\Http\Requests\Video\UpdateVideoRequest;
use App\Models\Video;
use App\Models\Category;
use App\Models\Setting;
use App\Models\SponsoredCard;
use App\Services\SeoService;
use App\Services\StorageManager;
use App\Services\TranslationService;
use App\Services\VideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VideoController extends Controller
{
    public function __construct(
        protected VideoService $videoService,
        protected SeoService $seoService,
    ) {}

    public function index(Request $request): Response
    {
        $escapedSearch = $request->search
            ? str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $request->search)
            : null;

        $videos = Video::query()
            ->with(['user', 'category'])
            ->public()
            ->approved()
            ->processed()
            ->when($request->category, fn($q, $cat) => $q->where('category_id', $cat))
            ->when($escapedSearch, fn($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->when(
                $request->sort === 'popular',
                fn($q) => $q->orderByDesc('views_count'),
                fn($q) => $q->when(
                    $request->sort === 'oldest',
                    fn($q) => $q->oldest('published_at'),
                    fn($q) => $q->latest('published_at')
                )
            )
            ->paginate(24);

        return Inertia::render('Videos/Index', [
            'videos' => $videos,
            'categories' => Category::active()->get(),
            'filters' => $request->only(['category', 'sort']),
            'bannerAd' => [
                'enabled' => Setting::get('browse_banner_ad_enabled', false),
                'type' => Setting::get('browse_banner_ad_type', 'html'),
                'code' => Setting::get('browse_banner_ad_code', ''),
                'image' => Setting::get('browse_banner_ad_image', ''),
                'link' => Setting::get('browse_banner_ad_link', ''),
                'mobileType' => Setting::get('browse_banner_ad_mobile_type', 'html'),
                'mobileCode' => Setting::get('browse_banner_ad_mobile_code', ''),
                'mobileImage' => Setting::get('browse_banner_ad_mobile_image', ''),
                'mobileLink' => Setting::get('browse_banner_ad_mobile_link', ''),
            ],
            'adSettings' => [
                'videoGridEnabled' => (bool) Setting::get('video_grid_ad_enabled', false),
                'videoGridCode' => (string) Setting::get('video_grid_ad_code', ''),
                'videoGridMobileCode' => (string) Setting::get('video_grid_ad_mobile_code', ''),
                'videoGridFrequency' => (int) Setting::get('video_grid_ad_frequency', 8),
            ],
            'sponsoredCards' => SponsoredCard::getForPage(
                'browse',
                auth()->user()?->role ?? 'guest',
                $request->category ? (int) $request->category : null,
            ),
        ]);
    }

    /**
     * Locale-prefixed video show — manually resolves by slug to avoid
     * model binding conflict with the {locale} route prefix parameter.
     * Also checks for translated slugs in the translations table.
     */
    public function localeShow(string $locale, string $slug): Response
    {
        // Try original slug first
        $video = Video::where('slug', $slug)->first();

        // If not found, try translated slug
        if (!$video) {
            $currentLocale = app()->getLocale();
            $translationService = app(\App\Services\TranslationService::class);
            $videoId = $translationService->findByTranslatedSlug(Video::class, $slug, $currentLocale);
            if ($videoId) {
                $video = Video::find($videoId);
            }
        }

        if (!$video) {
            abort(404);
        }

        return $this->show($video);
    }

    public function show(Video $video): Response
    {
        if (!$video->isAccessibleBy(auth()->user())) {
            abort(403);
        }

        // Non-owners can only see approved+processed videos
        $isOwner = auth()->check() && (auth()->id() === $video->user_id || auth()->user()->is_admin);
        if (!$isOwner && (!$video->is_approved || $video->status !== 'processed')) {
            abort(404);
        }

        $video->load(['user.channel', 'category']);
        $video->incrementViews();

        $relatedVideos = Video::query()
            ->with('user')
            ->where('id', '!=', $video->id)
            ->where('category_id', $video->category_id)
            ->public()
            ->approved()
            ->processed()
            ->limit(12)
            ->get();

        // Batch-load all settings in a single query instead of ~18 individual calls
        $all = Setting::getAll();
        $s = fn (string $key, mixed $default = null) => $all[$key] ?? $default;

        $sidebarAd = [
            'enabled' => $s('video_sidebar_ad_enabled', false),
            'code' => $s('video_sidebar_ad_code', ''),
            'mobileCode' => $s('video_sidebar_ad_mobile_code', ''),
        ];

        $bannerAbovePlayer = [
            'enabled' => (bool) $s('banner_above_player_enabled', false),
            'type' => $s('banner_above_player_type', 'html'),
            'html' => $s('banner_above_player_html', ''),
            'image' => $s('banner_above_player_image', ''),
            'link' => $s('banner_above_player_link', ''),
            'mobile_type' => $s('banner_above_player_mobile_type', 'html'),
            'mobile_html' => $s('banner_above_player_mobile_html', ''),
            'mobile_image' => $s('banner_above_player_mobile_image', ''),
            'mobile_link' => $s('banner_above_player_mobile_link', ''),
        ];

        $bannerBelowPlayer = [
            'enabled' => (bool) $s('banner_below_player_enabled', false),
            'type' => $s('banner_below_player_type', 'html'),
            'html' => $s('banner_below_player_html', ''),
            'image' => $s('banner_below_player_image', ''),
            'link' => $s('banner_below_player_link', ''),
            'mobile_type' => $s('banner_below_player_mobile_type', 'html'),
            'mobile_html' => $s('banner_below_player_mobile_html', ''),
            'mobile_image' => $s('banner_below_player_mobile_image', ''),
            'mobile_link' => $s('banner_below_player_mobile_link', ''),
        ];

        // Get user's playlists with flag indicating if this video is already in each
        // Uses a single subquery instead of N+1 exists() calls per playlist
        $userPlaylists = [];
        if (auth()->check()) {
            $videoId = $video->id;
            $userPlaylists = auth()->user()->playlists()
                ->select('id', 'title', 'slug')
                ->withCount('videos')
                ->withCount(['videos as has_video' => function ($q) use ($videoId) {
                    $q->where('video_id', $videoId);
                }])
                ->get()
                ->each(fn ($p) => $p->has_video = (bool) $p->has_video);
        }

        // Translate tags for non-default locales
        $translatedTags = null;
        $locale = App::getLocale();
        $defaultLocale = TranslationService::getDefaultLocale();

        if ($locale !== $defaultLocale && !empty($video->tags)) {
            $translationService = app(TranslationService::class);
            $translatedTags = array_map(
                fn (string $tag) => $translationService->translateText($tag, $locale, $defaultLocale),
                $video->tags
            );
        }

        return Inertia::render('Videos/Show', [
            'video' => $video,
            'translatedTags' => $translatedTags,
            'relatedVideos' => $relatedVideos,
            'userLike' => auth()->check() 
                ? $video->likes()->where('user_id', auth()->id())->first()?->type 
                : null,
            'isSubscribed' => auth()->check() 
                ? auth()->user()->isSubscribedTo($video->user) 
                : false,
            'sidebarAd' => $sidebarAd,
            'bannerAbovePlayer' => $bannerAbovePlayer,
            'bannerBelowPlayer' => $bannerBelowPlayer,
            'userPlaylists' => $userPlaylists,
            'seo' => $this->seoService->forVideo($video),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('upload-video');

        return Inertia::render('Videos/Create', [
            'categories' => Category::active()->get(),
        ]);
    }

    public function uploadSuccess(Request $request): Response
    {
        return Inertia::render('Videos/UploadSuccess', [
            'videoTitle' => $request->query('title', ''),
        ]);
    }

    public function store(StoreVideoRequest $request): RedirectResponse
    {
        Gate::authorize('upload-video');

        $video = $this->videoService->create($request->validated(), $request->user());

        // Admin/Pro users go to the full edit page; default users go to the status page
        if ($request->user()->canEditVideo()) {
            return redirect()
                ->route('videos.edit', $video)
                ->with('success', 'Video uploaded! Processing will begin shortly.');
        }

        return redirect()
            ->route('videos.upload-success', ['title' => $video->title]);
    }

    /**
     * Accept a file chunk for resumable uploads.
     * Frontend sends: chunk (file), chunkIndex, totalChunks, uploadId, filename, fileSize
     * On final chunk: assembles all chunks into a single file and returns the temp path.
     */
    public function uploadChunk(Request $request): JsonResponse
    {
        Gate::authorize('upload-video');

        $request->validate([
            'chunk' => 'required|file',
            'chunkIndex' => 'required|integer|min:0|max:10000',
            'totalChunks' => 'required|integer|min:1|max:10000',
            'uploadId' => 'required|string|max:64|regex:/^[a-zA-Z0-9_-]+$/',
            'filename' => 'required|string|max:255',
            'fileSize' => 'required|integer|min:1',
        ]);

        $fileSize = (int) $request->input('fileSize');
        $maxSize = (int) ($request->user()->max_video_size ?? 0);
        if ($maxSize > 0 && $fileSize > $maxSize) {
            return response()->json(['error' => 'Video file exceeds your maximum upload size.'], 422);
        }

        $uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', $request->input('uploadId'));
        $chunkIndex = (int) $request->input('chunkIndex');
        $totalChunks = (int) $request->input('totalChunks');
        if ($chunkIndex >= $totalChunks) {
            return response()->json(['error' => 'Invalid chunk index.'], 422);
        }

        $filename = $request->input('filename');
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: '');
        $allowedExtensions = config('hubtube.video.allowed_extensions', []);
        if (empty($extension) || !in_array($extension, $allowedExtensions, true)) {
            return response()->json(['error' => 'Invalid video file type.'], 422);
        }
        $chunkDir = storage_path("app/chunks/{$uploadId}");

        if (!is_dir($chunkDir)) {
            mkdir($chunkDir, 0755, true);
        }

        // Store the chunk
        $request->file('chunk')->move($chunkDir, "chunk_{$chunkIndex}");

        // Check if all chunks have been received
        $receivedChunks = count(glob("{$chunkDir}/chunk_*"));

        if ($receivedChunks < $totalChunks) {
            return response()->json([
                'status' => 'partial',
                'received' => $receivedChunks,
                'total' => $totalChunks,
            ]);
        }

        // All chunks received — assemble the file
        $filename = $request->input('filename');
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'mp4';
        $assembledPath = storage_path("app/chunks/{$uploadId}.{$extension}");

        $output = fopen($assembledPath, 'wb');
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = "{$chunkDir}/chunk_{$i}";
            if (!file_exists($chunkPath)) {
                fclose($output);
                return response()->json(['error' => "Missing chunk {$i}"], 422);
            }
            $chunk = fopen($chunkPath, 'rb');
            stream_copy_to_stream($chunk, $output);
            fclose($chunk);
        }
        fclose($output);

        // Clean up chunk directory
        array_map('unlink', glob("{$chunkDir}/chunk_*"));
        rmdir($chunkDir);

        return response()->json([
            'status' => 'complete',
            'uploadId' => $uploadId,
            'extension' => $extension,
        ]);
    }

    public function edit(Video $video): Response
    {
        $this->authorize('update', $video);

        return Inertia::render('Videos/Edit', [
            'video' => $video,
            'categories' => Category::active()->get(),
        ]);
    }

    public function status(Video $video): Response
    {
        $this->authorize('viewStatus', $video);

        return Inertia::render('Videos/Status', [
            'video' => $video,
            'canEdit' => auth()->user()->canEditVideo(),
        ]);
    }

    public function update(UpdateVideoRequest $request, Video $video): RedirectResponse
    {
        $this->authorize('update', $video);

        $this->videoService->update($video, $request->validated());

        return back()->with('success', 'Video updated successfully.');
    }

    public function destroy(Video $video): RedirectResponse
    {
        $this->authorize('delete', $video);

        $this->videoService->delete($video);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Video deleted successfully.');
    }

    public function processingStatus(Video $video): JsonResponse
    {
        $this->authorize('viewStatus', $video);

        $thumbnails = [];
        $slugTitle = Str::slug($video->title, '_') ?: 'video';
        $videoDir = "videos/{$video->slug}";
        $count = (int) Setting::get('thumbnail_count', 4);

        // During processing, files are always on local disk
        // After cloud offload, storage_disk changes — but thumbnails are checked on both
        for ($i = 0; $i < $count; $i++) {
            $thumbRelative = "{$videoDir}/{$slugTitle}_thumb_{$i}.jpg";

            // Check local disk first (processing happens locally)
            $localPath = Storage::disk('public')->path($thumbRelative);
            if (file_exists($localPath)) {
                $thumbnails[] = asset('storage/' . $thumbRelative);
            } elseif ($video->storage_disk && $video->storage_disk !== 'public') {
                // After cloud offload, check cloud disk
                if (StorageManager::exists($thumbRelative, $video->storage_disk)) {
                    $thumbnails[] = StorageManager::url($thumbRelative, $video->storage_disk);
                }
            }
        }

        return response()->json([
            'status' => $video->status,
            'thumbnail_url' => $video->thumbnail_url,
            'thumbnails' => $thumbnails,
            'qualities_available' => $video->qualities_available,
        ]);
    }

    public function selectThumbnail(Request $request, Video $video): JsonResponse
    {
        $this->authorize('update', $video);

        $count = (int) Setting::get('thumbnail_count', 4);
        $request->validate(['index' => "required|integer|min:0|max:" . ($count - 1)]);

        $index = $request->input('index');
        $slugTitle = Str::slug($video->title, '_') ?: 'video';
        $videoDir = "videos/{$video->slug}";
        $thumbRelative = "{$videoDir}/{$slugTitle}_thumb_{$index}.jpg";

        // Check local first, then cloud
        $localPath = Storage::disk('public')->path($thumbRelative);
        $disk = $video->storage_disk ?? 'public';

        if (!file_exists($localPath) && !StorageManager::exists($thumbRelative, $disk)) {
            return response()->json(['error' => 'Thumbnail not found'], 404);
        }

        $video->update(['thumbnail' => $thumbRelative]);

        // Return URL from whichever disk has the file
        if ($disk !== 'public' && StorageManager::exists($thumbRelative, $disk)) {
            $url = StorageManager::url($thumbRelative, $disk);
        } else {
            $url = asset('storage/' . $thumbRelative);
        }

        return response()->json([
            'thumbnail_url' => $url,
        ]);
    }
}
