<?php
// app/Http/Controllers/Message/ConversationController.php

namespace App\Http\Controllers\Message;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\MessageStatus;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Events\MessageSent;
use App\Events\MessageRead;
use App\Events\ConversationUpdated;
use Illuminate\Support\Facades\Log;

class ConversationController extends Controller
{
    /**
     * Get all conversations for the authenticated user
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Build the query
            $query = Conversation::whereHas('participants', function($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->whereNull('left_at');
                })
                ->with(['participants' => function($q) {
                    $q->with('user:id,first_name,last_name,avatar')
                      ->whereNull('left_at');
                }])
                ->with('latestMessage');

            // Filter by type
            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }

            // Order by last message time
            $conversations = $query->orderBy('last_message_at', 'desc')
                ->orderBy('updated_at', 'desc')
                ->paginate($request->get('per_page', 20));

            // Add unread counts and load sender for latest message
            foreach ($conversations as $conversation) {
                $conversation->unread_count = $this->getUnreadCountForUser($conversation, $user->id);
                
                // Load sender for latest message if it exists
                if ($conversation->latestMessage) {
                    $conversation->latestMessage->load('sender:id,first_name,last_name,avatar');
                }
            }

            return response()->json([
                'success' => true,
                'current_page' => $conversations->currentPage(),
                'data' => $conversations->items(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Conversation index error: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error loading conversations: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Helper method to get unread count
     */
    private function getUnreadCountForUser($conversation, $userId)
    {
        try {
            $participant = $conversation->participants()
                ->where('user_id', $userId)
                ->first();
            
            if (!$participant || !$participant->last_read_at) {
                return $conversation->messages()->count();
            }

            return $conversation->messages()
                ->where('created_at', '>', $participant->last_read_at)
                ->where('sender_id', '!=', $userId)
                ->count();
        } catch (\Exception $e) {
            Log::error('Error getting unread count: ' . $e->getMessage());
            return 0;
        }
    }
    /**
     * Start a new conversation or get existing one
     */
    public function start(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'user_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) use ($user) {
                    if ($value == $user->id) {
                        $fail('You cannot start a conversation with yourself.');
                    }
                },
            ],
            'initial_message' => 'nullable|string|max:5000'
        ]);

        // Check if conversation already exists
        $existing = Conversation::where('type', 'direct')
            ->whereHas('participants', function($q) use ($user, $request) {
                $q->where('user_id', $user->id);
            })
            ->whereHas('participants', function($q) use ($request) {
                $q->where('user_id', $request->user_id);
            })
            ->first();

        if ($existing) {
            // Reactivate if left
            $participant = $existing->getParticipant($user->id);
            if ($participant && $participant->left_at) {
                $participant->update([
                    'left_at' => null,
                    'joined_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Conversation already exists',
                'data' => $existing->load('participants.user')
            ]);
        }

        DB::beginTransaction();
        try {
            // Create new conversation
            $conversation = Conversation::create([
                'type' => 'direct',
                'created_by' => $user->id,
                'last_message_at' => now(),
            ]);

            // Add both participants
            ConversationParticipant::insert([
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $user->id,
                    'role' => 'member',
                    'joined_at' => now(),
                ],
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $request->user_id,
                    'role' => 'member',
                    'joined_at' => now(),
                ]
            ]);

            // Send initial message if provided
            if ($request->filled('initial_message')) {
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $user->id,
                    'content' => $request->initial_message,
                    'type' => 'text',
                ]);

                // Mark as delivered for sender
                $message->statuses()->create([
                    'user_id' => $user->id,
                    'delivered_at' => now(),
                    'read_at' => now(), // Sender has read their own message
                ]);

                // Create pending status for receiver
                $message->statuses()->create([
                    'user_id' => $request->user_id,
                    'delivered_at' => null,
                    'read_at' => null,
                ]);

                $conversation->update(['last_message_at' => now()]);
            }

            DB::commit();

            $conversation->load('participants.user');

            return response()->json([
                'success' => true,
                'message' => 'Conversation started',
                'data' => $conversation
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to start conversation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get messages for a specific conversation
     */
    public function messages(Request $request, $conversationId)
    {
        try {
            $user = Auth::user();

            $conversation = Conversation::findOrFail($conversationId);

            // Verify user is participant
            $participant = $conversation->participants()
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->first();

            if (!$participant) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a participant in this conversation'
                ], 403);
            }

            $messages = Message::where('conversation_id', $conversationId)
                ->with(['sender:id,first_name,last_name,avatar'])
                ->with(['attachments'])
                ->with(['reactions' => function($q) {
                    $q->with('user:id,first_name,last_name');
                }])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 30));

            // Transform reactions
            foreach ($messages as $message) {
                $message->reactions_grouped = $this->groupReactions($message->reactions);
                
                // Get read status
                $message->read_by = MessageStatus::where('message_id', $message->id)
                    ->whereNotNull('read_at')
                    ->pluck('user_id')
                    ->toArray();
            }

            // Mark messages as delivered for this user
            Message::where('conversation_id', $conversationId)
                ->where('sender_id', '!=', $user->id)
                ->whereDoesntHave('statuses', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->chunk(50, function($messages) use ($user) {
                    foreach ($messages as $message) {
                        $message->statuses()->updateOrCreate(
                            ['user_id' => $user->id],
                            ['delivered_at' => now()]
                        );
                    }
                });

            return response()->json([
                'success' => true,
                'data' => $messages
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting messages: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading messages'
            ], 500);
        }
    }

    /**
     * Send a message in a conversation
     */
    public function sendMessage(Request $request, $conversationId)
    {
        try {
            $user = Auth::user();

            $request->validate([
                'content' => 'required_without:attachments|string|max:5000',
                'reply_to_id' => 'nullable|exists:messages,id',
                'attachments' => 'nullable|array',
                'attachments.*' => 'file|mimes:jpeg,png,jpg,gif,pdf,doc,docx|max:25600',
            ]);

            $conversation = Conversation::findOrFail($conversationId);

            // Verify user is participant
            $participant = $conversation->participants()
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->first();

            if (!$participant) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a participant in this conversation'
                ], 403);
            }

            DB::beginTransaction();

            // Create message
            $message = Message::create([
                'conversation_id' => $conversationId,
                'sender_id' => $user->id,
                'content' => $request->content,
                'type' => $request->hasFile('attachments') ? 'image' : 'text',
                'reply_to_id' => $request->reply_to_id,
            ]);

            // Mark as delivered for sender
            $message->statuses()->create([
                'user_id' => $user->id,
                'delivered_at' => now(),
                'read_at' => now(),
            ]);

            // Handle attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('message-attachments/' . $conversationId, 'public');
                    
                    $message->attachments()->create([
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                        'metadata' => [
                            'original_name' => $file->getClientOriginalName(),
                            'extension' => $file->getClientOriginalExtension(),
                        ],
                    ]);
                }
            }

            // Create status entries for all other participants
            $conversation->participants()
                ->where('user_id', '!=', $user->id)
                ->whereNull('left_at')
                ->each(function($participant) use ($message) {
                    $message->statuses()->create([
                        'user_id' => $participant->user_id,
                        'delivered_at' => null,
                        'read_at' => null,
                    ]);
                });

            // Update conversation
            $conversation->update([
                'last_message_at' => now(),
            ]);

            DB::commit();

            $message->load(['sender:id,first_name,last_name,avatar', 'attachments']);

            // Broadcast to other participants
            try {
                broadcast(new MessageSent(Auth::user(), $message))->toOthers();
            } catch (\Exception $e) {
                Log::error('Broadcast error: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'data' => $message
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Send message error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Mark messages as read
     */
    public function markAsRead(Request $request, $conversationId)
    {
        try {
            $user = Auth::user();

            $conversation = Conversation::findOrFail($conversationId);

            // Verify user is an active participant
            $participant = $conversation->participants()
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->first();

            if (!$participant) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a participant in this conversation'
                ], 403);
            }

            $participant->update(['last_read_at' => now()]);

            $now = now();

            Message::where('conversation_id', $conversationId)
                ->where('sender_id', '!=', $user->id)  // don't mark own messages
                ->whereDoesntHave('statuses', function ($q) use ($user) {
                    $q->where('user_id', $user->id)->whereNotNull('read_at');
                })
                ->select('id')
                ->chunk(100, function ($messages) use ($user, $now) {
                    foreach ($messages as $message) {
                        MessageStatus::updateOrCreate(
                            [
                                'message_id' => $message->id,
                                'user_id'    => $user->id,
                            ],
                            [
                                'delivered_at' => $now,
                                'read_at'      => $now,
                            ]
                        );
                    }
                });

            return response()->json([
                'success' => true,
                'message' => 'Messages marked as read',
            ]);

        } catch (\Exception $e) {
            Log::error('Mark as read error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark as read'
            ], 500);
        }
    }

    /**
     * Archive/Unarchive conversation
     */
    public function toggleArchive($conversationId)
    {
        $user = Auth::user();

        $conversation = Conversation::findOrFail($conversationId);
        
        $participant = $conversation->getParticipant($user->id);
        if (!$participant) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found'
            ], 404);
        }

        $newStatus = $participant->status === 'archived' ? 'active' : 'archived';
        
        $participant->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => $newStatus === 'archived' ? 'Conversation archived' : 'Conversation restored',
            'data' => ['status' => $newStatus]
        ]);
    }

    /**
     * Delete conversation (for current user)
     */
    public function destroy($conversationId)
    {
        $user = Auth::user();

        $participant = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->first();

        if (!$participant) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found'
            ], 404);
        }

        // Soft delete by setting left_at
        $participant->update([
            'left_at' => now(),
            'status' => 'archived'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted'
        ]);
    }

    /**
     * Block user
     */
    public function blockUser(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'user_id' => 'required|exists:users,id|different:user_id',
        ]);

        // Find or create conversation
        $conversation = Conversation::where('type', 'direct')
            ->whereHas('participants', function($q) use ($user, $request) {
                $q->where('user_id', $user->id);
            })
            ->whereHas('participants', function($q) use ($request) {
                $q->where('user_id', $request->user_id);
            })
            ->first();

        if ($conversation) {
            $conversation->update([
                'status' => 'blocked',
                'blocked_by' => $user->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User blocked'
        ]);
    }

    /**
     * Unblock user
     */
    public function unblockUser(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $conversation = Conversation::where('type', 'direct')
            ->where('blocked_by', $user->id)
            ->whereHas('participants', function($q) use ($user, $request) {
                $q->where('user_id', $user->id);
            })
            ->whereHas('participants', function($q) use ($request) {
                $q->where('user_id', $request->user_id);
            })
            ->first();

        if ($conversation) {
            $conversation->update([
                'status' => 'active',
                'blocked_by' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User unblocked'
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

    /**
     * Get all conversations for a specific group (role_id = 2)
     */
    public function getGroupConversations(Request $request, $groupId)
    {
        $user = Auth::user();
        
        // Verify the group exists and user has access
        $group = User::where('id', $groupId)
            ->where('role_id', 2)
            ->firstOrFail();
        
        $conversations = Conversation::where('group_id', $groupId)
            ->orWhereHas('participants', function($q) use ($groupId) {
                $q->where('user_id', $groupId);
            })
            ->with(['participants' => function($q) {
                $q->with('user:id,first_name,last_name,avatar,last_seen_at');
            }])
            ->with('latestMessage.sender')
            ->orderBy('last_message_at', 'desc')
            ->paginate($request->get('per_page', 20)); // <-- Now $request is defined

        return response()->json([
            'success' => true,
            'data' => $conversations
        ]);
    }

    /**
     * Start a conversation with a group (for users messaging a group)
     */
    public function startWithGroup(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'group_id' => 'required|exists:users,id',
            'initial_message' => 'nullable|string|max:5000',
        ]);

        // Verify the target is actually a group user
        $group = User::where('id', $request->group_id)
            ->where('role_id', 2)
            ->firstOrFail();

        // Check if conversation already exists between this user and group
        $existing = Conversation::where('type', 'direct')
            ->where('group_id', $request->group_id)
            ->whereHas('participants', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Conversation already exists',
                'data' => $existing->load('participants.user')
            ]);
        }

        DB::beginTransaction();
        try {
            // Create new conversation linked to the group
            $conversation = Conversation::create([
                'type' => 'direct',
                'created_by' => $user->id,
                'group_id' => $request->group_id, // Link to the group
                'last_message_at' => now(),
            ]);

            // Add both participants
            ConversationParticipant::insert([
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $user->id,
                    'role' => 'member',
                    'joined_at' => now(),
                ],
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $request->group_id,
                    'role' => 'member',
                    'joined_at' => now(),
                ]
            ]);

            // Send initial message if provided
            if ($request->filled('initial_message')) {
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $user->id,
                    'content' => $request->initial_message,
                    'type' => 'text',
                ]);

                // Create status entries
                $message->statuses()->createMany([
                    [
                        'user_id' => $user->id,
                        'delivered_at' => now(),
                        'read_at' => now(),
                    ],
                    [
                        'user_id' => $request->group_id,
                        'delivered_at' => null,
                        'read_at' => null,
                    ]
                ]);
            }

            DB::commit();

            $conversation->load('participants.user');

            return response()->json([
                'success' => true,
                'message' => 'Conversation started with group',
                'data' => $conversation
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to start conversation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all group-owned conversations (for group users to see their conversations)
     */
    public function getMyGroupConversations()
    {
        $user = Auth::user();
        
        // Only group users can access this
        if ($user->role_id !== 2) {
            return response()->json([
                'success' => false,
                'message' => 'Only group users can access this'
            ], 403);
        }

        $conversations = Conversation::where('group_id', $user->id)
            ->orWhereHas('participants', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->with(['participants' => function($q) {
                $q->with('user:id,first_name,last_name,avatar');
            }])
            ->with('latestMessage.sender')
            ->orderBy('last_message_at', 'desc')
            ->get();

        // Add unread counts
        foreach ($conversations as $conversation) {
            $conversation->unread_count = $conversation->getUnreadCountForUser($user->id);
        }

        return response()->json([
            'success' => true,
            'data' => $conversations
        ]);
    }



}