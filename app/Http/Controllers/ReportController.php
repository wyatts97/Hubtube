<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\Report;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reportable_type' => 'required|in:video,comment,user',
            'reportable_id' => 'required|integer',
            'reason' => 'required|in:spam,harassment,illegal,copyright,underage,other',
            'description' => 'nullable|string|max:2000',
        ]);

        $typeMap = [
            'video' => \App\Models\Video::class,
            'comment' => \App\Models\Comment::class,
            'user' => \App\Models\User::class,
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

        $report = Report::create([
            'user_id' => $request->user()->id,
            'reportable_type' => $morphType,
            'reportable_id' => $validated['reportable_id'],
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status' => Report::STATUS_PENDING,
        ]);

        // Try to get a meaningful label for the reported content
        $reportedContent = ucfirst($validated['reportable_type']) . ' #' . $validated['reportable_id'];
        $reportable = $morphType::find($validated['reportable_id']);
        if ($reportable) {
            $reportedContent = $reportable->title ?? $reportable->username ?? $reportable->name ?? $reportedContent;
        }

        $reasonLabels = [
            'spam' => 'Spam or misleading',
            'harassment' => 'Harassment or bullying',
            'illegal' => 'Illegal content',
            'copyright' => 'Copyright violation',
            'underage' => 'Underage content',
            'other' => 'Other',
        ];

        // Create an admin inbox entry so reports appear alongside contact messages
        ContactMessage::create([
            'type' => 'report',
            'name' => $request->user()->username,
            'email' => $request->user()->email,
            'user_id' => $request->user()->id,
            'report_id' => $report->id,
            'subject' => 'Report: ' . ($reasonLabels[$validated['reason']] ?? $validated['reason']) . ' — ' . $reportedContent,
            'message' => ($validated['description'] ?? '(No additional details)')
                . "\n\n---\nType: " . ucfirst($validated['reportable_type'])
                . "\nContent: " . $reportedContent
                . "\nReason: " . ($reasonLabels[$validated['reason']] ?? $validated['reason']),
            'is_read' => false,
        ]);

        EmailService::sendToAdmin('admin-new-report', [
            'reporter' => $request->user()->username,
            'report_type' => $validated['reportable_type'],
            'report_reason' => $validated['reason'],
            'reported_content' => $reportedContent,
            'description' => $validated['description'] ?? '(No additional details)',
        ]);

        return response()->json(['success' => true, 'message' => 'Report submitted successfully'], 201);
    }
}
