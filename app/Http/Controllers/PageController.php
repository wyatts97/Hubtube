<?php

namespace App\Http\Controllers;

use Exception;
use Throwable;
use Illuminate\Support\Facades\Log;
use App\Jobs\TranslateModelJob;
use App\Models\Page;
use App\Services\SeoService;
use App\Services\TranslationService;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function __construct(
        protected SeoService $seoService,
    ) {}

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
        } catch (Exception $e) {
            // DB may not be ready
        }

        if ($locale !== $defaultLocale) {
            try {
                // Cache-only read. Page content is often long HTML, and the
                // provider is throttled, so translating inline could hold the
                // request for a long time; missing pieces are queued instead.
                $cached = app(TranslationService::class)->modelFromCache(
                    Page::class,
                    $page->id,
                    ['title' => $title, 'content' => $content],
                    $locale
                );

                $title = $cached['fields']['title'] ?: $title;
                $content = $cached['fields']['content'] ?: $content;

                if (!$cached['complete'] && TranslationService::autoTranslateEnabled()) {
                    TranslateModelJob::dispatch(Page::class, $page->id, ['title', 'content'], $locale);
                }
            } catch (Throwable $e) {
                // Lookup failed — fall back to original content
                Log::warning('Page translation lookup failed: ' . $e->getMessage(), [
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
            'seo' => $this->seoService->forPage($page, $title, $content),
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
