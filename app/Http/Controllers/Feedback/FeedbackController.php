<?php

namespace App\Http\Controllers\Feedback;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Feedbacks;
use Illuminate\Support\Facades\DB;

class FeedbackController extends Controller
{
    /**
     * Display all feedbacks submitted for a given target (user).
     */
    public function index(int $id)
    {
        $feedbacks = Feedbacks::where('target_id', $id)->get();

        return response()->json([
            'message' => 'Feedbacks retrieved successfully.',
            'feedbacks' => $feedbacks,
        ], 200);
    }

    /**
     * Display all feedbacks created by the currently authenticated user.
     */
    public function index_user()
    {
        $userId = Auth::id();

        $feedbacks = Feedbacks::where('user_id', $userId)->get();

        return response()->json([
            'message' => 'Your feedbacks retrieved successfully.',
            'feedbacks' => $feedbacks,
        ], 200);
    }

    /**
     * Return a form or placeholder for feedback creation (optional for frontend use).
     */
    public function create()
    {
        return response()->json([
            'message' => 'You can now create a feedback.',
        ]);
    }

    /**
     * Return a form or data for editing an existing feedback.
     */
    public function edit(int $feedback_id)
    {
        $feedback = Feedbacks::findOrFail($feedback_id);

        if ($feedback->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized: you can only edit your own feedback.',
            ], 403);
        }

        return response()->json([
            'message' => 'Feedback loaded for editing.',
            'feedback' => $feedback,
        ], 200);
    }

    /**
     * Show a specific feedback by its ID.
     */
    public function show(int $feedback_id)
    {
        $feedback = Feedbacks::findOrFail($feedback_id);

        return response()->json([
            'message' => 'Feedback retrieved successfully.',
            'feedback' => $feedback,
        ], 200);
    }

    /**
     * Store a newly created feedback in the database.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'target_id' => 'required|integer|exists:users,id',
            'event_id' => 'nullable|integer|exists:event,id',
            'zone_id' => 'nullable|integer|exists:zone,id',
            'materielle_id' => 'nullable|integer|exists:materielles,id',
            'contenu' => 'nullable|string|max:1000',
            'note' => 'required|numeric|min:0|max:5',
        ]);

        $userId = Auth::id();

        DB::beginTransaction();

        try {
            $feedback = Feedbacks::create([
                'user_id' => $userId,
                'target_id' => $request->target_id,
                'event_id' => $request->event_id,
                'zone_id' => $request->zone_id,
                'materielle_id' => $request->materielle_id,
                'contenu' => $request->contenu,
                'note' => $request->note
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Feedback added successfully.',
                'feedback' => $feedback,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'An error occurred while saving feedback: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing feedback (only if user is owner).
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'note' => 'required|numeric|min:0|max:5',
            'contenu' => 'nullable|string|max:1000',
        ]);

        $userId = Auth::id();
        $feedback = Feedbacks::findOrFail($id);

        if ($feedback->user_id !== $userId) {
            return response()->json([
                'message' => 'Prohibited action: you are not the owner of this feedback.',
            ], 403);
        }

        $feedback->update($validated);

        return response()->json([
            'message' => 'Feedback updated successfully.',
            'feedback' => $feedback
        ]);
    }

    /**
     * Delete a feedback (only if user is the owner).
     */
    public function destroy($id)
    {
        $feedback = Feedbacks::findOrFail($id);

        if ($feedback->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized: only the author can delete this feedback.',
            ], 403);
        }

        $feedback->delete();

        return response()->json([
            'message' => 'Feedback deleted successfully.'
        ]);
    }
}
