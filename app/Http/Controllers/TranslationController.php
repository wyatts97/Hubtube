<?php

namespace App\Http\Controllers;

use App\Models\Translation;
use App\Models\Video;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class TranslationController extends Controller
{
    protected TranslationService $translationService;

    public function __construct(TranslationService $translationService)
    {
        $this->translationService = $translationService;
    }

    /**
     * Translate content on-demand via AJAX.
     * POST /api/translate
     */
    public function translate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:video,category,page',
            'id' => 'required|integer',
            'fields' => 'required|array|max:5',
            'fields.*' => 'string|max:50',
            'locale' => 'required|string|max:10',
        ]);

        $locale = $validated['locale'];
        if (!TranslationService::isValidLocale($locale)) {
            return response()->json(['error' => 'Invalid locale'], 422);
        }

        $modelMap = [
            'video' => \App\Models\Video::class,
            'category' => \App\Models\Category::class,
            'page' => \App\Models\Page::class,
        ];

        $modelClass = $modelMap[$validated['type']] ?? null;
        if (!$modelClass) {
            return response()->json(['error' => 'Invalid type'], 422);
        }

        $model = $modelClass::find($validated['id']);
        if (!$model) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $fields = [];
        foreach ($validated['fields'] as $field) {
            $value = $model->{$field} ?? null;
            if ($value) {
                $fields[$field] = $value;
            }
        }

        $translated = $this->translationService->translateModel(
            $modelClass,
            $model->id,
            $fields,
            $locale
        );

        return response()->json([
            'translations' => $translated,
            'locale' => $locale,
        ]);
    }

    /**
     * Batch translate video listings.
     * POST /api/translate/batch
     */
    public function translateBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:video',
            'ids' => 'required|array|max:50',
            'ids.*' => 'integer',
            'fields' => 'required|array|max:3',
            'fields.*' => 'string|max:50',
            'locale' => 'required|string|max:10',
        ]);

        $locale = $validated['locale'];
        if (!TranslationService::isValidLocale($locale)) {
            return response()->json(['error' => 'Invalid locale'], 422);
        }

        $modelClass = \App\Models\Video::class;
        $models = Video::whereIn('id', $validated['ids'])->get();

        $items = $models->map(function ($model) use ($validated) {
            $item = ['id' => $model->id];
            foreach ($validated['fields'] as $field) {
                $item[$field] = $model->{$field} ?? '';
            }
            return $item;
        })->toArray();

        $translated = $this->translationService->translateBatch(
            $modelClass,
            $items,
            $validated['fields'],
            $locale
        );

        return response()->json([
            'translations' => $translated,
            'locale' => $locale,
        ]);
    }

    /**
     * Get available languages.
     * GET /api/languages
     */
    public function languages(): JsonResponse
    {
        return response()->json([
            'default' => TranslationService::getDefaultLocale(),
            'current' => App::getLocale(),
            'languages' => TranslationService::getEnabledLanguages(),
        ]);
    }

    /**
     * Set user's preferred language.
     * POST /api/locale
     */
    public function setLocale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => 'required|string|max:10',
            'current_path' => 'nullable|string|max:2048',
        ]);

        $locale = $validated['locale'];
        if (!TranslationService::isValidLocale($locale)) {
            return response()->json(['error' => 'Invalid locale'], 422);
        }

        $defaultLocale = TranslationService::getDefaultLocale();
        $currentPath = $validated['current_path'] ?? '/';

        // Strip any existing locale prefix from the current path
        $enabledLocales = array_keys(TranslationService::getEnabledLanguages());
        foreach ($enabledLocales as $loc) {
            if ($currentPath === "/{$loc}" || str_starts_with($currentPath, "/{$loc}/")) {
                $currentPath = substr($currentPath, strlen("/{$loc}")) ?: '/';
                break;
            }
        }

        // Translate video slug if the current path is a video page
        $translatedPath = $currentPath;
        $knownRoutes = ['/', '/trending', '/shorts', '/search', '/videos', '/live', '/contact',
            '/categories', '/playlists', '/history', '/settings', '/dashboard', '/upload',
            '/login', '/register', '/feed', '/notifications'];
        $pathSegments = explode('/', ltrim($currentPath, '/'));

        if (count($pathSegments) === 1 && $pathSegments[0] !== '' && !in_array($currentPath, $knownRoutes)) {
            // Single-segment path that isn't a known route — likely a video slug
            $slug = $pathSegments[0];
            $video = Video::where('slug', $slug)->first();

            // If not found by original slug, try finding by translated slug (user might already be on a translated URL)
            if (!$video) {
                $videoId = $this->translationService->findByTranslatedSlug(Video::class, $slug, app()->getLocale());
                if ($videoId) {
                    $video = Video::find($videoId);
                }
            }

            if ($video) {
                if ($locale === $defaultLocale) {
                    // Switching back to default — use original slug
                    $translatedPath = "/{$video->slug}";
                } else {
                    // Get translated slug for target locale
                    $translatedSlug = $this->translationService->getTranslatedSlug(Video::class, $video->id, $locale);
                    $translatedPath = '/' . ($translatedSlug ?: $video->slug);
                }
            }
        }

        if ($locale === $defaultLocale) {
            // Switching back to default — clear session so unprefixed URLs work
            session()->forget('locale');
            $redirect = url($translatedPath);
        } else {
            session(['locale' => $locale]);
            $redirect = url("/{$locale}" . ($translatedPath === '/' ? '' : $translatedPath));
        }

        return response()->json([
            'locale' => $locale,
            'redirect' => $redirect,
        ]);
    }
}
