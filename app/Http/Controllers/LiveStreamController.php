<?php

namespace App\Http\Controllers;

use App\Events\LiveStreamStarted;
use App\Models\LiveStream;
use App\Services\AgoraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LiveStreamController extends Controller
{
    public function __construct(
        protected AgoraService $agoraService
    ) {}

    public function index(): Response
    {
        $liveStreams = LiveStream::query()
            ->with('user')
            ->live()
            ->orderByDesc('viewer_count')
            ->paginate(24);

        return Inertia::render('Live/Index', [
            'streams' => $liveStreams,
        ]);
    }

    public function show(LiveStream $liveStream): Response
    {
        if (!$liveStream->isLive()) {
            abort(404, 'Stream has ended');
        }

        $liveStream->load('user.channel');

        $token = null;
        if (auth()->check()) {
            $token = $this->agoraService->generateToken(
                $liveStream->channel_name,
                auth()->id(),
                'audience'
            );
        }

        return Inertia::render('Live/Show', [
            'stream' => $liveStream,
            'agoraAppId' => config('hubtube.agora.app_id'),
            'agoraToken' => $token,
            'isSubscribed' => auth()->check() 
                ? auth()->user()->isSubscribedTo($liveStream->user) 
                : false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('go-live');

        return Inertia::render('Live/Create');
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('go-live');

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:5000',
        ]);

        $channelName = 'live_' . $request->user()->id . '_' . time();

        $token = $this->agoraService->generateToken(
            $channelName,
            $request->user()->id,
            'host'
        );

        $liveStream = LiveStream::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'channel_name' => $channelName,
            'agora_token' => $token,
            'status' => 'pending',
        ]);

        return response()->json([
            'stream' => $liveStream,
            'agoraAppId' => config('hubtube.agora.app_id'),
            'agoraToken' => $token,
            'channelName' => $channelName,
        ]);
    }

    public function start(LiveStream $liveStream): JsonResponse
    {
        $this->authorize('update', $liveStream);

        $liveStream->start();

        event(new LiveStreamStarted($liveStream));

        return response()->json(['success' => true]);
    }

    public function end(LiveStream $liveStream): JsonResponse
    {
        $this->authorize('update', $liveStream);

        $liveStream->end();

        return response()->json(['success' => true]);
    }

    public function updateViewerCount(Request $request, LiveStream $liveStream): JsonResponse
    {
        $validated = $request->validate([
            'count' => 'required|integer|min:0',
        ]);

        $liveStream->updateViewerCount($validated['count']);

        return response()->json(['success' => true]);
    }
}
