<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->where('first_name', 'like', "%{$request->search}%")
                        ->orWhere('last_name',  'like', "%{$request->search}%")
                        ->orWhere('email',      'like', "%{$request->search}%")
                        ->orWhere('subject',    'like', "%{$request->search}%")
                        ->orWhere('message',    'like', "%{$request->search}%");
                });
            })
            ->paginate($request->get('per_page', 20));

        return response()->json(['status' => 'success', 'data' => $messages]);
    }

    /**
     * Admin: mark a message as read.
     */
    public function markRead($id)
    {
        $msg = ContactMessage::findOrFail($id);
        $msg->update(['status' => 'read']);

        return response()->json(['status' => 'success', 'data' => $msg]);
    }

    /**
     * Admin: reply to a contact message by email.
     */
    public function reply(Request $request, $id)
    {
        $msg = ContactMessage::findOrFail($id);

        $request->validate([
            'reply_message' => 'required|string|max:5000',
        ]);

        // Mark as read
        $msg->update(['status' => 'read']);

        // Send reply email
        Mail::raw($request->reply_message, function ($mail) use ($msg, $request) {
            $mail->to($msg->email, "{$msg->first_name} {$msg->last_name}")
                 ->subject("Re: {$msg->subject}");
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Reply sent successfully.',
            'data'    => $msg->fresh(),
        ]);
    }

    /**
     * Admin: delete a contact message.
     */
    public function destroy($id)
    {
        $msg = ContactMessage::findOrFail($id);
        $msg->delete();

        return response()->json(['status' => 'success', 'message' => 'Message deleted.']);
    }
}
