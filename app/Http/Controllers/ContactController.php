<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Contact');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'subject' => 'nullable|string|max:200',
            'message' => 'required|string|max:5000',
        ]);

        ContactMessage::create($validated);

        // Notify admin via email (if configured and enabled)
        EmailService::sendToAdmin('contact-form-admin', [
            'sender_name' => $validated['name'],
            'sender_email' => $validated['email'],
            'subject' => $validated['subject'] ?? '(No subject)',
            'message' => nl2br(e($validated['message'])),
        ], $validated['email'], $validated['name']);

        return back()->with('success', 'Your message has been sent. We\'ll get back to you soon!');
    }
}
