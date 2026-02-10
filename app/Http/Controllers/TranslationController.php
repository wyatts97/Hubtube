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
        ]);

        $locale = $validated['locale'];
        if (!TranslationService::isValidLocale($locale)) {
            return response()->json(['error' => 'Invalid locale'], 422);
        }

        session(['locale' => $locale]);

        return response()->json([
            'locale' => $locale,
            'redirect' => $locale === TranslationService::getDefaultLocale()
                ? url('/')
                : url("/{$locale}"),
        ]);
    }
}
