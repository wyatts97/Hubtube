<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Image;
use App\Models\Setting;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ImageController extends Controller
{
    public function __construct(
        protected ImageService $imageService,
    ) {}

    public function index(Request $request): Response
    {
        $images = Image::query()
            ->with('user')
            ->public()
            ->approved()
            ->when($request->category, fn($q, $cat) => $q->where('category_id', $cat))
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

        return Inertia::render('Images/Index', [
            'images' => $images,
            'categories' => Category::active()->get(),
            'filters' => $request->only(['category', 'sort']),
        ]);
    }

    public function show(Image $image): Response
    {
        if (!$image->isAccessibleBy(auth()->user())) {
            abort(403);
        }

        $isOwner = auth()->check() && (auth()->id() === $image->user_id || auth()->user()->is_admin);
        if (!$isOwner && !$image->is_approved) {
            abort(404);
        }

        $image->load(['user', 'category']);
        $image->incrementViews();

        $relatedImages = Image::query()
            ->with('user')
            ->where('id', '!=', $image->id)
            ->when($image->category_id, fn($q) => $q->where('category_id', $image->category_id))
            ->public()
            ->approved()
            ->latest('published_at')
            ->limit(12)
            ->get();

        return Inertia::render('Images/Show', [
            'image' => $image,
            'relatedImages' => $relatedImages,
            'canEdit' => $isOwner,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Images/Upload', [
            'categories' => Category::active()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'image_file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,bmp', 'max:51200'], // 50MB
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'privacy' => ['required', 'in:public,private,unlisted'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
        ]);

        $image = $this->imageService->process(
            $request->file('image_file'),
            auth()->id(),
            [
                'title' => $request->title,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'privacy' => $request->privacy,
                'tags' => $request->tags,
            ]
        );

        return redirect()->route('images.show', $image->uuid)
            ->with('success', 'Image uploaded successfully!');
    }

    public function destroy(Image $image): RedirectResponse
    {
        Gate::authorize('delete', $image);

        $image->delete();

        return redirect()->route('images.index')
            ->with('success', 'Image deleted successfully.');
    }
}
