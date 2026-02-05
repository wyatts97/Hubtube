<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reportable_type' => 'required|in:video,comment,user,live_stream',
            'reportable_id' => 'required|integer',
            'reason' => 'required|in:spam,harassment,illegal,copyright,underage,other',
            'description' => 'nullable|string|max:2000',
        ]);

        $typeMap = [
            'video' => \App\Models\Video::class,
            'comment' => \App\Models\Comment::class,
            'user' => \App\Models\User::class,
            'live_stream' => \App\Models\LiveStream::class,
        ];

        $morphType = $typeMap[$validated['reportable_type']] ?? null;

        if (!$morphType || !$morphType::find($validated['reportable_id'])) {
            return response()->json(['error' => 'Content not found'], 404);
        }

        // Prevent duplicate reports
        $existing = Report::where([
            'user_id' => $request->user()->id,
            'reportable_type' => $morphType,
            'reportable_id' => $validated['reportable_id'],
        ])->where('status', Report::STATUS_PENDING)->exists();

        if ($existing) {
            return response()->json(['error' => 'You have already reported this content'], 422);
        }

        Report::create([
            'user_id' => $request->user()->id,
            'reportable_type' => $morphType,
            'reportable_id' => $validated['reportable_id'],
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status' => Report::STATUS_PENDING,
        ]);

        return response()->json(['success' => true, 'message' => 'Report submitted successfully'], 201);
    }
}
