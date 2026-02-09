<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function show(Page $page): Response
    {
        if (!$page->is_published) {
            abort(404);
        }

        return Inertia::render('Legal/Show', [
            'page' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'content' => $page->content,
                'updated_at' => $page->updated_at->toDateString(),
            ],
        ]);
    }
}
