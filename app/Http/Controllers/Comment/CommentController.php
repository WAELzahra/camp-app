<?php

namespace App\Http\Controllers\Comment;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Annonce;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    /**
     * Get comments for an annonce
     */
    public function index($annonceId)
    {
        $user = Auth::user();
        
        $comments = Comment::with(['user', 'replies.user'])
            ->where('annonce_id', $annonceId)
            ->whereNull('parent_id') // Get only top-level comments
            ->visible()
            ->pinnedFirst()
            ->get();

        // Add liked_by_user field to each comment and its replies
        $comments = $comments->map(function ($comment) use ($user) {
            return $this->addLikedByUser($comment, $user);
        });

        // Count total comments including replies
        $totalComments = Comment::where('annonce_id', $annonceId)->count();

        return response()->json([
            'success' => true,
            'comments' => $comments,
            'total' => $totalComments
        ]);
    }

    /**
     * Recursively add liked_by_user field to comment and its replies
     */
    private function addLikedByUser($comment, $user)
    {
        // Check if current user has liked this comment using CommentLike model
        $comment->liked_by_user = $user ? 
            \App\Models\CommentLike::where('user_id', $user->id)
                                ->where('comment_id', $comment->id)
                                ->exists() : false;
        
        // Process replies recursively
        if ($comment->replies && $comment->replies->count() > 0) {
            $comment->replies = $comment->replies->map(function ($reply) use ($user) {
                return $this->addLikedByUser($reply, $user);
            });
        }
        
        return $comment;
    }

    /**
     * Store a new comment
     */
    public function store(Request $request, $annonceId)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:comments,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if annonce exists
        $annonce = Annonce::findOrFail($annonceId);

        $comment = Comment::create([
            'user_id' => Auth::id(),
            'annonce_id' => $annonceId,
            'parent_id' => $request->parent_id,
            'content' => $request->content
        ]);

        // Update annonce comments count
        $annonce->increment('comments_count');

        // Load relationships
        $comment->load('user');

        // Add liked_by_user field to the newly created comment
        $user = Auth::user();
        $comment->liked_by_user = false; // New comment is not liked yet

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully',
            'comment' => $comment
        ], 201);
    }

    /**
     * Update a comment
     */
    public function update(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);

        // Check if user owns the comment
        if ($comment->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $comment->update([
            'content' => $request->content,
            'is_edited' => true
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Comment updated successfully',
            'comment' => $comment
        ]);
    }

    /**
     * Delete a comment
     */
    public function destroy($id)
    {
        $comment = Comment::findOrFail($id);

        // Check if user owns the comment or is admin
        if ($comment->user_id !== Auth::id() && Auth::user()->role_id !== 6) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Update annonce comments count (decrement by 1 + number of replies)
        $totalDeleted = 1 + $comment->replies()->count();
        $comment->annonce()->decrement('comments_count', $totalDeleted);

        // Soft delete the comment (replies will be cascade deleted)
        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully'
        ]);
    }

    /**
     * Toggle like on a comment
     */
    public function toggleLike($id)
    {
        $comment = Comment::findOrFail($id);
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Use the CommentLike model directly
        $existingLike = \App\Models\CommentLike::where('user_id', $user->id)
                                            ->where('comment_id', $comment->id)
                                            ->first();

        if ($existingLike) {
            // Unlike
            $existingLike->delete();
            $comment->decrement('likes_count');
            $liked = false;
        } else {
            // Like
            \App\Models\CommentLike::create([
                'user_id' => $user->id,
                'comment_id' => $comment->id
            ]);
            $comment->increment('likes_count');
            $liked = true;
        }

        return response()->json([
            'success' => true,
            'liked' => $liked,
            'likes_count' => $comment->fresh()->likes_count
        ]);
    }
    /**
     * Pin/unpin a comment (admin only)
     */
    public function togglePin($id)
    {
        // Check if user is admin
        if (Auth::user()->role_id !== 6) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $comment = Comment::findOrFail($id);
        $comment->update(['is_pinned' => !$comment->is_pinned]);

        return response()->json([
            'success' => true,
            'message' => $comment->is_pinned ? 'Comment pinned' : 'Comment unpinned',
            'is_pinned' => $comment->is_pinned
        ]);
    }

    /**
     * Hide/unhide a comment (admin only)
     */
    public function toggleHide($id)
    {
        // Check if user is admin
        if (Auth::user()->role_id !== 6) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $comment = Comment::findOrFail($id);
        $comment->update(['is_hidden' => !$comment->is_hidden]);

        return response()->json([
            'success' => true,
            'message' => $comment->is_hidden ? 'Comment hidden' : 'Comment shown',
            'is_hidden' => $comment->is_hidden
        ]);
    }
}