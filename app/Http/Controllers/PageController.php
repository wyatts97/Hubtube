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
                $translated = $translationService->translateModel(
                    Page::class,
                    $page->id,
                    ['title' => $title, 'content' => $content],
                    $locale
                );
                $title = $translated['title'] ?? $title;
                $content = $translated['content'] ?? $content;
            } catch (\Exception $e) {
                // Translation failed — fall back to original content
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
}
