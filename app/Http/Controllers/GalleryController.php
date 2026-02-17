<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\Image;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    public function index(Request $request): Response
    {
        $galleries = Gallery::query()
            ->with(['user', 'coverImage'])
            ->public()
            ->withCount('images')
            ->when(
                $request->sort === 'popular',
                fn($q) => $q->orderByDesc('views_count'),
                fn($q) => $q->latest()
            )
            ->paginate(24);

        return Inertia::render('Galleries/Index', [
            'galleries' => $galleries,
            'filters' => $request->only(['sort']),
        ]);
    }

    public function show(Gallery $gallery): Response
    {
        if (!$gallery->isAccessibleBy(auth()->user())) {
            abort(403);
        }

        $gallery->load(['user', 'coverImage']);
        $gallery->incrementViews();

        $images = $gallery->images()
            ->with('user')
            ->approved()
            ->paginate(48);

        return Inertia::render('Galleries/Show', [
            'gallery' => $gallery,
            'images' => $images,
            'canEdit' => auth()->check() && (auth()->id() === $gallery->user_id || auth()->user()->is_admin),
        ]);
    }

    public function create(): Response
    {
        $userImages = Image::where('user_id', auth()->id())
            ->approved()
            ->latest()
            ->get();

        return Inertia::render('Galleries/Create', [
            'userImages' => $userImages,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'privacy' => ['required', 'in:public,private,unlisted'],
            'image_ids' => ['required', 'array', 'min:1'],
            'image_ids.*' => ['exists:images,id'],
            'sort_order' => ['nullable', 'in:manual,newest,oldest'],
        ]);

        $slug = $this->generateUniqueSlug($request->title);

        $gallery = Gallery::create([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'slug' => $slug,
            'description' => $request->description,
            'privacy' => $request->privacy,
            'sort_order' => $request->sort_order ?? 'newest',
            'images_count' => count($request->image_ids),
        ]);

        // Attach images with sort order
        $syncData = [];
        foreach ($request->image_ids as $index => $imageId) {
            $syncData[$imageId] = ['sort_order' => $index];
        }
        $gallery->images()->sync($syncData);

        // Set first image as cover
        if (!empty($request->image_ids)) {
            $gallery->update(['cover_image_id' => $request->image_ids[0]]);
        }

        return redirect()->route('galleries.show', $gallery->slug)
            ->with('success', 'Gallery created successfully!');
    }

    public function update(Request $request, Gallery $gallery): RedirectResponse
    {
        Gate::authorize('update', $gallery);

        $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'privacy' => ['required', 'in:public,private,unlisted'],
            'image_ids' => ['nullable', 'array'],
            'image_ids.*' => ['exists:images,id'],
        ]);

        $gallery->update([
            'title' => $request->title,
            'description' => $request->description,
            'privacy' => $request->privacy,
        ]);

        if ($request->has('image_ids')) {
            $syncData = [];
            foreach ($request->image_ids as $index => $imageId) {
                $syncData[$imageId] = ['sort_order' => $index];
            }
            $gallery->images()->sync($syncData);
            $gallery->update(['images_count' => count($request->image_ids)]);

            if (!empty($request->image_ids) && !$gallery->cover_image_id) {
                $gallery->update(['cover_image_id' => $request->image_ids[0]]);
            }
        }

        return redirect()->route('galleries.show', $gallery->slug)
            ->with('success', 'Gallery updated successfully.');
    }

    public function destroy(Gallery $gallery): RedirectResponse
    {
        Gate::authorize('delete', $gallery);

        $gallery->images()->detach();
        $gallery->delete();

        return redirect()->route('galleries.index')
            ->with('success', 'Gallery deleted successfully.');
    }

    protected function generateUniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title) ?: 'gallery';
        $slug = $baseSlug;
        $suffix = 2;
        while (Gallery::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }
        return $slug;
    }
}
