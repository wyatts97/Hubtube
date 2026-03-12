<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\TranslationService;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function show(Page $page): Response
    {
        if (!$page->is_published) {
            abort(404);
        }

        $title = $page->title;
        $content = $page->content;

        // Translate page content if a non-default locale is active
        $locale = App::getLocale();
        $defaultLocale = 'en';
        try {
            $defaultLocale = TranslationService::getDefaultLocale();
        } catch (\Exception $e) {
            // DB may not be ready
        }

        if ($locale !== $defaultLocale) {
            try {
                $translationService = app(TranslationService::class);
                // Translate title (short text — safe)
                $translatedTitle = $translationService->translateField(
                    Page::class, $page->id, 'title', $title, $locale
                );
                $title = $translatedTitle ?: $title;

                // Translate content separately (can be very long HTML — may fail)
                $translatedContent = $translationService->translateField(
                    Page::class, $page->id, 'content', $content, $locale
                );
                $content = $translatedContent ?: $content;
            } catch (\Throwable $e) {
                // Translation failed — fall back to original content
                \Illuminate\Support\Facades\Log::warning('Page translation failed: ' . $e->getMessage(), [
                    'page_id' => $page->id,
                    'locale' => $locale,
                ]);
            }
        }

        return Inertia::render('Legal/Show', [
            'page' => [
                'title' => $title,
                'slug' => $page->slug,
                'content' => $content,
                'updated_at' => $page->updated_at->toDateString(),
            ],
        ]);
    }

    /**
     * Locale-prefixed page show.
     * Uses plain {slug} param to avoid model binding conflict with {locale} prefix.
     */
    public function localeShow(string $locale, string $slug): Response
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        return $this->show($page);
    }
}
