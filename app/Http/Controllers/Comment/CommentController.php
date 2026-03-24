<?php

namespace App\Http\Controllers\Comment;

use App\Http\Controllers\Controller;
use App\Models\Annonce;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Events\CommentCreated;
use App\Events\CommentUpdated;
use App\Events\CommentDeleted;
use App\Events\CommentLiked;
use App\Events\CommentPinned;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CommentController extends Controller
{
    /**
     * Get all visible top-level comments + replies for an annonce.
     */
    public function index(int $annonceId)
    {
        $annonce = Annonce::findOrFail($annonceId);

        $comments = Comment::with(['user', 'replies.user', 'replies.likes'])
            ->where('annonce_id', $annonceId)
            ->whereNull('parent_id')
            ->where('is_hidden', false)
            ->orderByDesc('is_pinned')
            ->latest()
            ->get()
            ->map(fn($c) => $this->formatComment($c));

        return response()->json([
            'status'   => 'success',
            'comments' => $comments,
            'total'    => $comments->count(),
        ]);
    }

    /**
     * Post a new comment or reply.
     */
    public function store(Request $request, int $annonceId)
    {
        $request->validate([
            'content'   => 'required|string|max:2000',
            'parent_id' => 'nullable|integer|exists:comments,id',
        ]);

        $annonce = Annonce::findOrFail($annonceId);

        DB::beginTransaction();
        try {
            $comment = Comment::create([
                'user_id'    => Auth::id(),
                'annonce_id' => $annonceId,
                'parent_id'  => $request->parent_id,
                'content'    => $request->content,
                'likes_count' => 0,
                'is_edited'  => false,
                'is_pinned'  => false,
                'is_hidden'  => false,
            ]);

            // Increment comment count on annonce (top-level only)
            if (!$request->parent_id) {
                $annonce->increment('comments_count');
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }

        $comment->load(['user', 'replies']);
        $formatted = $this->formatComment($comment);

        // Broadcast to the annonce channel
        broadcast(new CommentCreated($formatted, $annonceId))->toOthers();

        return response()->json([
            'status'  => 'success',
            'comment' => $formatted,
        ], 201);
    }

    /**
     * Edit own comment.
     */
    public function update(Request $request, int $annonceId, int $commentId)
    {
        $request->validate(['content' => 'required|string|max:2000']);

        $comment = Comment::where('annonce_id', $annonceId)->findOrFail($commentId);

        if (Auth::id() !== $comment->user_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $comment->update(['content' => $request->content, 'is_edited' => true]);
        $comment->load(['user', 'replies']);
        $formatted = $this->formatComment($comment);

        broadcast(new CommentUpdated($formatted, $annonceId))->toOthers();

        return response()->json(['status' => 'success', 'comment' => $formatted]);
    }

    /**
     * Delete a comment (own or admin).
     */
    public function destroy(int $annonceId, int $commentId)
    {
        $comment = Comment::where('annonce_id', $annonceId)->findOrFail($commentId);
        $user    = Auth::user();

        if ($user->id !== $comment->user_id && $user->role_id !== 6) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $parentId = $comment->parent_id;
        $comment->delete();

        // Decrement count for top-level comments
        if (!$parentId) {
            Annonce::where('id', $annonceId)->decrement('comments_count');
        }

        broadcast(new CommentDeleted($commentId, $parentId, $annonceId))->toOthers();

        return response()->json(['status' => 'success', 'message' => 'Comment deleted.']);
    }

    /**
     * Like a comment.
     */
    public function like(int $annonceId, int $commentId)
    {
        $comment = Comment::where('annonce_id', $annonceId)->findOrFail($commentId);
        $userId  = Auth::id();

        if (CommentLike::where('comment_id', $commentId)->where('user_id', $userId)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Already liked.'], 400);
        }

        CommentLike::create(['comment_id' => $commentId, 'user_id' => $userId]);
        $comment->increment('likes_count');
        $count = $comment->fresh()->likes_count;

        broadcast(new CommentLiked($commentId, $comment->parent_id, $count, $annonceId))->toOthers();

        return response()->json(['status' => 'success', 'likes_count' => $count]);
    }

    /**
     * Unlike a comment.
     */
    public function unlike(int $annonceId, int $commentId)
    {
        $comment = Comment::where('annonce_id', $annonceId)->findOrFail($commentId);
        $userId  = Auth::id();

        $deleted = CommentLike::where('comment_id', $commentId)->where('user_id', $userId)->delete();

        if (!$deleted) {
            return response()->json(['status' => 'error', 'message' => 'Like not found.'], 404);
        }

        $comment->decrement('likes_count');
        $count = $comment->fresh()->likes_count;

        broadcast(new CommentLiked($commentId, $comment->parent_id, $count, $annonceId))->toOthers();

        return response()->json(['status' => 'success', 'likes_count' => $count]);
    }

    /**
     * Pin a comment (admin only).
     */
    public function pin(int $annonceId, int $commentId)
    {
        if (Auth::user()->role_id !== 6) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $comment = Comment::where('annonce_id', $annonceId)->findOrFail($commentId);
        $comment->update(['is_pinned' => true]);
        $comment->load('user');
        $formatted = $this->formatComment($comment);

        broadcast(new CommentPinned($formatted, $annonceId))->toOthers();

        return response()->json(['status' => 'success', 'comment' => $formatted]);
    }

    /**
     * Unpin a comment (admin only).
     */
    public function unpin(int $annonceId, int $commentId)
    {
        if (Auth::user()->role_id !== 6) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $comment = Comment::where('annonce_id', $annonceId)->findOrFail($commentId);
        $comment->update(['is_pinned' => false]);
        $comment->load('user');
        $formatted = $this->formatComment($comment);

        broadcast(new CommentPinned($formatted, $annonceId))->toOthers();

        return response()->json(['status' => 'success', 'comment' => $formatted]);
    }

    /**
     * Format a comment for JSON response, including liked_by_me flag.
     */
    private function formatComment(Comment $comment): array
    {
        $userId     = Auth::id();
        $likedByMe  = $userId
            ? CommentLike::where('comment_id', $comment->id)->where('user_id', $userId)->exists()
            : false;

        $replies = ($comment->replies ?? collect())->map(function ($reply) use ($userId) {
            $replyLiked = $userId
                ? CommentLike::where('comment_id', $reply->id)->where('user_id', $userId)->exists()
                : false;
            return array_merge($reply->toArray(), ['liked_by_me' => $replyLiked]);
        })->values()->toArray();

        return array_merge($comment->toArray(), [
            'liked_by_me' => $likedByMe,
            'replies'     => $replies,
        ]);
    }
}