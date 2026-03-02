<?php
// app/Http/Controllers/Message/MessageController.php

namespace App\Http\Controllers\Message;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\MessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Events\MessageReactionUpdated;
use App\Events\MessageUpdated;

class MessageController extends Controller
{
    /**
     * Edit a message
     */
    public function update(Request $request, $messageId)
    {
        $user = Auth::user();

        $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $message = Message::where('id', $messageId)
            ->where('sender_id', $user->id)
            ->first();

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found or you cannot edit it'
            ], 404);
        }

        // Check if message is too old to edit (e.g., 1 hour)
        if ($message->created_at->diffInMinutes(now()) > 60) {
            return response()->json([
                'success' => false,
                'message' => 'Messages can only be edited within 1 hour of sending'
            ], 403);
        }

        $oldContent = $message->content;
        $message->update([
            'content' => $request->content,
            'edited_at' => now(),
        ]);

        // Broadcast update
        broadcast(new MessageUpdated($message, 'edited'))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Message updated',
            'data' => $message
        ]);
    }

    /**
     * Delete a message (soft delete)
     */
    public function destroy($messageId)
    {
        $user = Auth::user();

        $message = Message::where('id', $messageId)
            ->where('sender_id', $user->id)
            ->first();

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found or you cannot delete it'
            ], 404);
        }

        // Check if message is too old to delete (e.g., 24 hours)
        if ($message->created_at->diffInHours(now()) > 24) {
            return response()->json([
                'success' => false,
                'message' => 'Messages can only be deleted within 24 hours of sending'
            ], 403);
        }

        $message->delete();

        // Broadcast deletion
        broadcast(new MessageUpdated($message, 'deleted'))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Message deleted'
        ]);
    }
    /**
     * Mark a conversation's messages as read
     */
    public function markAsRead(Request $request, $conversationId)
    {
        $user = Auth::user();

        // Get unread messages for this user in the conversation
        $messages = Message::where('conversation_id', $conversationId)
            ->whereHas('statuses', function($q) use ($user) {
                $q->where('user_id', $user->id)
                ->whereNull('read_at');
            })
            ->get();

        foreach ($messages as $message) {
            $status = $message->statuses()->firstOrCreate([
                'user_id' => $user->id,
            ]);
            $status->read_at = now();
            $status->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Messages marked as read',
        ]);
    }
    /**
     * React to a message
     */
    public function react(Request $request, $messageId)
    {
        $user = Auth::user();

        $request->validate([
            'reaction' => 'required|string|max:50|in:👍,❤️,😂,😮,😢,😡,🎉,👏,🔥,✅,⭐',
        ]);

        $message = Message::findOrFail($messageId);

        // Check if user has access to this conversation
        $isParticipant = $message->conversation->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();

        if (!$isParticipant) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this message'
            ], 403);
        }

        $existing = MessageReaction::where('message_id', $messageId)
            ->where('user_id', $user->id)
            ->where('reaction', $request->reaction)
            ->first();

        $action = $existing ? 'removed' : 'added';

        if ($existing) {
            $existing->delete();
        } else {
            MessageReaction::create([
                'message_id' => $messageId,
                'user_id' => $user->id,
                'reaction' => $request->reaction,
            ]);
        }

        // Get updated reactions
        $message->load('reactions.user');
        $reactionsGrouped = $this->groupReactions($message->reactions);

        // Broadcast reaction update
        broadcast(new MessageReactionUpdated(
            $message,
            $user,
            $request->reaction,
            $action,
            $reactionsGrouped
        ))->toOthers();

        return response()->json([
            'success' => true,
            'message' => $action === 'added' ? 'Reaction added' : 'Reaction removed',
            'data' => [
                'reactions_grouped' => $reactionsGrouped
            ]
        ]);
    }

    /**
     * Get message details
     */
    public function show($messageId)
    {
        $user = Auth::user();

        $message = Message::with(['sender:id,first_name,last_name,avatar', 'attachments', 'reactions.user:id,first_name,last_name'])
            ->with(['replyTo' => function($q) {
                $q->with('sender:id,first_name,last_name');
            }])
            ->findOrFail($messageId);

        // Check if user has access
        $isParticipant = $message->conversation->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();

        if (!$isParticipant) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        $message->reactions_grouped = $this->groupReactions($message->reactions);
        unset($message->reactions);

        return response()->json([
            'success' => true,
            'data' => $message
        ]);
    }

    /**
     * Get message read receipts
     */
    public function readReceipts($messageId)
    {
        $user = Auth::user();

        $message = Message::findOrFail($messageId);

        // Check access
        $isParticipant = $message->conversation->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();

        if (!$isParticipant) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        $readBy = $message->statuses()
            ->with('user:id,first_name,last_name,avatar')
            ->whereNotNull('read_at')
            ->get()
            ->map(function($status) {
                return [
                    'user' => $status->user,
                    'read_at' => $status->read_at,
                ];
            });

        $deliveredTo = $message->statuses()
            ->with('user:id,first_name,last_name,avatar')
            ->whereNotNull('delivered_at')
            ->whereNull('read_at')
            ->get()
            ->map(function($status) {
                return [
                    'user' => $status->user,
                    'delivered_at' => $status->delivered_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'read_by' => $readBy,
                'delivered_to' => $deliveredTo,
                'total_participants' => $message->conversation->participants()->count(),
            ]
        ]);
    }

    /**
     * Helper: Group reactions
     */
    private function groupReactions($reactions)
    {
        $grouped = [];
        foreach ($reactions as $reaction) {
            if (!isset($grouped[$reaction->reaction])) {
                $grouped[$reaction->reaction] = [
                    'count' => 0,
                    'users' => []
                ];
            }
            $grouped[$reaction->reaction]['count']++;
            $grouped[$reaction->reaction]['users'][] = [
                'id' => $reaction->user->id,
                'name' => $reaction->user->first_name . ' ' . $reaction->user->last_name,
            ];
        }
        return $grouped;
    }
}