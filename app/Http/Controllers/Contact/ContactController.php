<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Store a contact message.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|max:255',
            'phone'      => 'nullable|string|max:20',
            'subject'    => 'required|string|max:255',
            'message'    => 'required|string|max:5000',
        ]);

        ContactMessage::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Your message has been sent. We will get back to you soon!',
        ], 201);
    }

    /**
     * Admin: list all contact messages.
     */
    public function index(Request $request)
    {
        $messages = ContactMessage::latest()
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->paginate(20);

        return response()->json(['status' => 'success', 'data' => $messages]);
    }

    /**
     * Admin: mark a message as read.
     */
    public function markRead($id)
    {
        $msg = ContactMessage::findOrFail($id);
        $msg->update(['status' => 'read']);

        return response()->json(['status' => 'success']);
    }
}
