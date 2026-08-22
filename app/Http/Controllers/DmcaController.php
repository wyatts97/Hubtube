<?php

namespace App\Http\Controllers;

use App\Models\DmcaRequest;
use App\Models\Video;
use App\Services\AdminLogger;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DmcaController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Dmca');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'complainant_name' => 'required|string|max:150',
            'complainant_email' => 'required|email|max:255',
            'complainant_company' => 'nullable|string|max:150',
            'copyrighted_work_description' => 'required|string|max:5000',
            'infringing_urls' => 'required|string|max:5000',
            'good_faith_statement' => 'accepted',
            'accuracy_statement' => 'accepted',
            'signature' => 'required|string|max:150',
        ]);

        $validated['video_id'] = $this->resolveVideoId($validated['infringing_urls']);

        $dmcaRequest = DmcaRequest::create($validated);

        AdminLogger::log(
            "New DMCA takedown request from {$validated['complainant_name']} ({$validated['complainant_email']})",
            'admin',
            ['dmca_request_id' => $dmcaRequest->id, 'video_id' => $dmcaRequest->video_id],
            $dmcaRequest,
        );

        return back()->with('success', 'Your takedown request has been submitted. We will review it and respond to the email address provided.');
    }

    /**
     * Best-effort match of the first submitted URL to a video by slug, so admins
     * can jump straight to the reported content. Not required for the request
     * to be valid — a failed match just leaves video_id null for manual review.
     */
    protected function resolveVideoId(string $infringingUrls): ?int
    {
        $firstUrl = preg_split('/[\s,]+/', trim($infringingUrls), -1, PREG_SPLIT_NO_EMPTY)[0] ?? null;
        if (!$firstUrl) {
            return null;
        }

        $path = trim((string) parse_url($firstUrl, PHP_URL_PATH), '/');
        $slug = explode('/', $path)[0] ?? null;
        if (!$slug) {
            return null;
        }

        return Video::where('slug', $slug)->value('id');
    }
}
