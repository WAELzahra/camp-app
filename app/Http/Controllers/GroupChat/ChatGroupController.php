<?php

namespace App\Http\Controllers\GroupChat;

use App\Http\Controllers\Controller;
use App\Models\ChatGroup;
use App\Models\ChatGroupUser;
use App\Models\ChatGroupMessage;
use App\Models\MessageReaction;
use App\Models\ChatGroupTypingStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Events\GroupMessageSent;
use App\Events\GroupMessageReactionUpdated;

class ChatGroupController extends Controller
{
    /**
     * Create a new chat group (only for group users)
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Only group users (role_id = 2) can create chat groups
        if ($user->role_id !== 2) {
            return response()->json(['error' => 'Only group users can create chat groups.'], 403);
        }

        // Rate limiting: one group per 24 hours
        $last = ChatGroup::where('group_user_id', $user->id)->latest()->first();
        if ($last && $last->created_at->diffInHours(now()) < 24) {
            return response()->json([
                'error' => 'You can create only one group every 24 hours.',
                'next_allowed' => $last->created_at->addHours(24)
            ], 429);
        }

        // Validation
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'nullable|in:event,camping,interest,private,announcement',
            'max_members' => 'nullable|integer|min:2|max:500',
            'is_private' => 'boolean',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'exists:users,id',
        ]);

        // Generate unique invitation token
        $token = Str::random(32);
        
        DB::beginTransaction();
        try {
            // Create the group
            $chatGroup = ChatGroup::create([
                'group_user_id' => $user->id,
                'name' => $request->name,
                'description' => $request->description,
                'invitation_token' => $token,
                'invitation_expires_at' => now()->addMonths(3),
                'type' => $request->type ?? 'private',
                'is_private' => $request->is_private ?? false,
                'max_members' => $request->max_members,
                'members_count' => 1, // Creator is first member
                'last_activity_at' => now(),
            ]);

            // Add creator as admin
            ChatGroupUser::create([
                'chat_group_id' => $chatGroup->id,
                'user_id' => $user->id,
                'role' => 'admin',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            // Add initial members if provided
            if ($request->has('member_ids')) {
                foreach ($request->member_ids as $memberId) {
                    if ($memberId != $user->id) {
                        ChatGroupUser::create([
                            'chat_group_id' => $chatGroup->id,
                            'user_id' => $memberId,
                            'role' => 'member',
                            'status' => 'active',
                            'joined_at' => now(),
                        ]);
                        
                        $chatGroup->increment('members_count');
                    }
                }
            }

            DB::commit();

            // Send system message
            $this->sendSystemMessage(
                $chatGroup->id,
                "Group created by {$user->first_name} {$user->last_name}"
            );

            return response()->json([
                'success' => true,
                'message' => 'Chat group created successfully',
                'data' => $chatGroup->load('users'),
                'invitation_link' => url("/api/group-chat/join/{$token}"),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create group: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get groups created by the authenticated group user
     */
    public function myGroups()
    {
        $user = Auth::user();
        
        $groups = ChatGroup::where('group_user_id', $user->id)
            ->withCount(['users', 'messages'])
            ->with(['users' => function($q) {
                $q->where('role', 'admin')->first();
            }])
            ->orderBy('last_activity_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $groups
        ]);
    }

    /**
     * Get groups the user is a member of
     */
    public function myMemberships()
    {
        $user = Auth::user();
        
        $groups = ChatGroup::whereHas('users', function($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->where('status', 'active');
            })
            ->with(['groupUser' => function($q) {
                $q->select('id', 'first_name', 'last_name', 'avatar');
            }])
            ->withCount('users')
            ->orderBy('last_activity_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $groups
        ]);
    }

    /**
     * Delete a group (only by creator)
     */
    public function destroy($id)
    {
        $user = Auth::user();
        
        $chatGroup = ChatGroup::where('id', $id)
            ->where('group_user_id', $user->id)
            ->first();

        if (!$chatGroup) {
            return response()->json(['error' => 'Group not found or access denied'], 403);
        }

        DB::beginTransaction();
        try {
            // Delete all related data (cascade should handle this, but just in case)
            ChatGroupMessage::where('chat_group_id', $id)->delete();
            ChatGroupUser::where('chat_group_id', $id)->delete();
            ChatGroupTypingStatus::where('chat_group_id', $id)->delete();
            
            $chatGroup->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Group deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete group'], 500);
        }
    }

    /**
     * Join a group via invitation token
     */
    public function joinByToken($token)
    {
        $user = Auth::user();
        
        $group = ChatGroup::where('invitation_token', $token)
            ->where(function($q) {
                $q->whereNull('invitation_expires_at')
                  ->orWhere('invitation_expires_at', '>', now());
            })
            ->first();

        if (!$group) {
            return response()->json(['error' => 'Invalid or expired invitation link'], 404);
        }

        // Check if already a member
        $existing = ChatGroupUser::where('chat_group_id', $group->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            if ($existing->status === 'left') {
                // Reactivate membership
                $existing->update([
                    'status' => 'active',
                    'joined_at' => now(),
                    'left_at' => null
                ]);
                $group->increment('members_count');
            } else {
                return response()->json(['message' => 'You are already a member of this group']);
            }
        } else {
            // Check max members limit
            if ($group->max_members && $group->users()->count() >= $group->max_members) {
                return response()->json(['error' => 'Group has reached maximum members'], 400);
            }

            // Add as member
            ChatGroupUser::create([
                'chat_group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 'member',
                'status' => 'active',
                'joined_at' => now(),
            ]);
            
            $group->increment('members_count');
        }

        $group->update(['last_activity_at' => now()]);

        // Send system message
        $this->sendSystemMessage(
            $group->id,
            "{$user->first_name} {$user->last_name} joined the group"
        );

        return response()->json([
            'success' => true,
            'message' => 'You have joined the group successfully',
            'data' => $group
        ]);
    }


    /**
     * Send a message to a group
     */
    public function sendMessage(Request $request, $chat_group_id)
    {
        $user = Auth::user();

        $request->validate([
            'message' => 'required|string|max:5000',
            'type' => 'nullable|in:text,image,file',
            'reply_to_id' => 'nullable|exists:chat_group_messages,id',
        ]);

        // Check membership
        $membership = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$membership) {
            return response()->json(['error' => 'You are not a member of this group'], 403);
        }

        // Check if muted
        if ($membership->muted_until && $membership->muted_until > now()) {
            return response()->json([
                'error' => 'You are muted until ' . $membership->muted_until->format('Y-m-d H:i')
            ], 403);
        }

        DB::beginTransaction();
        try {
            $message = ChatGroupMessage::create([
                'chat_group_id' => $chat_group_id,
                'sender_id' => $user->id,
                'message' => $request->message,
                'type' => $request->type ?? 'text',
                'reply_to_id' => $request->reply_to_id,
                'sent_at' => now(),
            ]);

            // Update group activity
            ChatGroup::where('id', $chat_group_id)->update([
                'last_message_at' => now(),
                'last_activity_at' => now(),
            ]);

            // Increment messages count
            ChatGroup::where('id', $chat_group_id)->increment('messages_count');

            DB::commit();

            // Load relationships
            $message->load('sender:id,first_name,last_name,avatar');

            // Broadcast the message to all group members
            broadcast(new GroupMessageSent($message))->toOthers();

            return response()->json([
                'success' => true,
                'message' => 'Message sent',
                'data' => $message
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to send message'], 500);
        }
    }

    /**
     * React to a message
     */
    public function reactToMessage(Request $request, $message_id)
    {
        $user = Auth::user();

        $request->validate([
            'reaction' => 'required|string|max:50|in:👍,❤️,😂,😮,😢,😡,🎉,👏,🔥,✅',
        ]);

        $message = ChatGroupMessage::findOrFail($message_id);

        // Check if user is member
        $isMember = ChatGroupUser::where('chat_group_id', $message->chat_group_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (!$isMember) {
            return response()->json(['error' => 'Not a member'], 403);
        }

        $existingReaction = MessageReaction::where('message_id', $message_id)
            ->where('user_id', $user->id)
            ->where('reaction', $request->reaction)
            ->first();

        $action = $existingReaction ? 'removed' : 'added';

        if ($existingReaction) {
            $existingReaction->delete();
        } else {
            MessageReaction::create([
                'message_id' => $message_id,
                'user_id' => $user->id,
                'reaction' => $request->reaction
            ]);
        }

        // Get updated reactions
        $message->load('reactions.user');
        $reactions_grouped = $message->reactions
            ->groupBy('reaction')
            ->map(function ($reactions) {
                return [
                    'count' => $reactions->count(),
                    'users' => $reactions->pluck('user_id'),
                ];
            });

        // Broadcast the reaction update to all group members
        broadcast(new GroupMessageReactionUpdated(
            $message,
            $user,
            $request->reaction,
            $action,
            $reactions_grouped
        ))->toOthers();

        return response()->json([
            'success' => true,
            'message' => $action === 'added' ? 'Reaction added' : 'Reaction removed',
            'data' => [
                'reactions_grouped' => $reactions_grouped
            ]
        ]);
    }
    /**
     * Get messages from a group
     */
    public function getMessages(Request $request, $chat_group_id)
    {
        $user = Auth::user();

        $isMember = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (!$isMember) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $messages = ChatGroupMessage::where('chat_group_id', $chat_group_id)
            ->with(['sender:id,first_name,last_name,avatar', 'reactions'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 30));

        // Transform collection safely
        $messages->getCollection()->transform(function ($message) {

            $grouped = [];

            foreach ($message->reactions as $reaction) {
                $emoji = $reaction->reaction;

                if (!isset($grouped[$emoji])) {
                    $grouped[$emoji] = [
                        'count' => 0,
                        'users' => []
                    ];
                }

                $grouped[$emoji]['count']++;
                $grouped[$emoji]['users'][] = $reaction->user_id;
            }

            $message->reactions_grouped = $grouped;

            unset($message->reactions);

            return $message;
        });

        return response()->json([
            'success' => true,
            'data' => $messages
        ]);
    }


    /**
     * Get group members
     */
    public function getMembers($chat_group_id)
    {
        $user = Auth::user();

        $isMember = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMember) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $members = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->with('user:id,first_name,last_name,email,avatar')
            ->orderBy('role')
            ->orderBy('joined_at')
            ->get()
            ->map(function($member) {
                return [
                    'id' => $member->user->id,
                    'first_name' => $member->user->first_name,
                    'last_name' => $member->user->last_name,
                    'email' => $member->user->email,
                    'avatar' => $member->user->avatar,
                    'role' => $member->role,
                    'status' => $member->status,
                    'joined_at' => $member->joined_at,
                    'muted_until' => $member->muted_until,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $members
        ]);
    }

    /**
     * Rename group (admin only)
     */
    public function renameGroup(Request $request, $chat_group_id)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $membership = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $user->id)
            ->whereIn('role', ['admin'])
            ->first();

        if (!$membership) {
            return response()->json(['error' => 'Only admins can rename the group'], 403);
        }

        $chatGroup = ChatGroup::findOrFail($chat_group_id);
        $oldName = $chatGroup->name;
        $chatGroup->name = $request->name;
        $chatGroup->save();

        // Send system message
        $this->sendSystemMessage(
            $chat_group_id,
            "Group renamed from '{$oldName}' to '{$request->name}' by {$user->first_name}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Group renamed successfully',
            'data' => $chatGroup
        ]);
    }

    /**
     * Leave a group
     */
    public function leaveGroup($chat_group_id)
    {
        $user = Auth::user();

        $membership = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$membership) {
            return response()->json(['error' => 'You are not a member of this group'], 404);
        }

        // Check if creator
        $group = ChatGroup::find($chat_group_id);
        if ($group->group_user_id === $user->id) {
            return response()->json([
                'error' => 'The group creator cannot leave. Delete the group instead.'
            ], 403);
        }

        $membership->update([
            'status' => 'left',
            'left_at' => now()
        ]);

        $group->decrement('members_count');

        // Send system message
        $this->sendSystemMessage(
            $chat_group_id,
            "{$user->first_name} {$user->last_name} left the group"
        );

        return response()->json([
            'success' => true,
            'message' => 'You have left the group'
        ]);
    }

    /**
     * Update typing status
     */
    public function typingStatus(Request $request, $chat_group_id)
    {
        $user = Auth::user();

        $request->validate([
            'is_typing' => 'required|boolean',
        ]);

        $isMember = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (!$isMember) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        ChatGroupTypingStatus::updateOrCreate(
            [
                'chat_group_id' => $chat_group_id,
                'user_id' => $user->id
            ],
            [
                'is_typing' => $request->is_typing,
                'updated_at' => now()
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Get users currently typing
     */
    public function typingUsers($chat_group_id)
    {
        $users = ChatGroupTypingStatus::where('chat_group_id', $chat_group_id)
            ->where('is_typing', true)
            ->where('updated_at', '>=', now()->subSeconds(10))
            ->with('user:id,first_name,last_name')
            ->get()
            ->pluck('user');

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Archive/unarchive a group (admin only)
     */
    public function archive($chat_group_id)
    {
        $user = Auth::user();

        $membership = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $user->id)
            ->whereIn('role', ['admin'])
            ->first();

        if (!$membership) {
            return response()->json(['error' => 'Only admins can archive the group'], 403);
        }

        $chatGroup = ChatGroup::findOrFail($chat_group_id);
        $chatGroup->is_archived = !$chatGroup->is_archived;
        $chatGroup->save();

        return response()->json([
            'success' => true,
            'message' => $chatGroup->is_archived ? 'Group archived' : 'Group unarchived',
            'data' => $chatGroup
        ]);
    }

    /**
     * Remove a member (admin only)
     */
    public function removeMember($chat_group_id, $user_id)
    {
        $currentUser = Auth::user();

        // Check if current user is admin
        $isAdmin = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $currentUser->id)
            ->whereIn('role', ['admin'])
            ->exists();

        if (!$isAdmin) {
            return response()->json(['error' => 'Only admins can remove members'], 403);
        }

        // Cannot remove yourself
        if ($currentUser->id == $user_id) {
            return response()->json(['error' => 'You cannot remove yourself. Use leave instead.'], 403);
        }

        $membership = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $user_id)
            ->first();

        if (!$membership) {
            return response()->json(['error' => 'User not found in group'], 404);
        }

        $user = User::find($user_id);
        
        $membership->delete();
        
        ChatGroup::where('id', $chat_group_id)->decrement('members_count');

        // Send system message
        $this->sendSystemMessage(
            $chat_group_id,
            "{$user->first_name} {$user->last_name} was removed from the group by {$currentUser->first_name}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Member removed successfully'
        ]);
    }

    /**
     * Mute a member (admin only)
     */
    public function muteMember(Request $request, $chat_group_id, $user_id)
    {
        $currentUser = Auth::user();

        $request->validate([
            'duration_hours' => 'required|integer|min:1|max:720', // Max 30 days
        ]);

        // Check if current user is admin
        $isAdmin = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $currentUser->id)
            ->whereIn('role', ['admin'])
            ->exists();

        if (!$isAdmin) {
            return response()->json(['error' => 'Only admins can mute members'], 403);
        }

        $membership = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $user_id)
            ->first();

        if (!$membership) {
            return response()->json(['error' => 'User not found in group'], 404);
        }

        $mutedUntil = now()->addHours($request->duration_hours);
        
        $membership->update([
            'status' => 'muted',
            'muted_until' => $mutedUntil
        ]);

        $user = User::find($user_id);

        return response()->json([
            'success' => true,
            'message' => "User muted until {$mutedUntil->format('Y-m-d H:i')}",
            'data' => [
                'user_id' => $user_id,
                'muted_until' => $mutedUntil
            ]
        ]);
    }

    /**
     * Unmute a member (admin only)
     */
    public function unmuteMember($chat_group_id, $user_id)
    {
        $currentUser = Auth::user();

        // Check if current user is admin
        $isAdmin = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $currentUser->id)
            ->whereIn('role', ['admin'])
            ->exists();

        if (!$isAdmin) {
            return response()->json(['error' => 'Only admins can unmute members'], 403);
        }

        $membership = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $user_id)
            ->first();

        if (!$membership) {
            return response()->json(['error' => 'User not found in group'], 404);
        }

        $membership->update([
            'status' => 'active',
            'muted_until' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User unmuted successfully'
        ]);
    }


    /**
     * Remove reaction from a message
     */
    public function removeReaction($message_id, $reaction)
    {
        $user = Auth::user();

        MessageReaction::where('message_id', $message_id)
            ->where('user_id', $user->id)
            ->where('reaction', $reaction)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reaction removed'
        ]);
    }

    /**
     * Pin a message (admin only)
     */
    public function pinMessage($message_id)
    {
        $user = Auth::user();

        $message = ChatGroupMessage::findOrFail($message_id);

        // Check if user is admin
        $isAdmin = ChatGroupUser::where('chat_group_id', $message->chat_group_id)
            ->where('user_id', $user->id)
            ->whereIn('role', ['admin'])
            ->exists();

        if (!$isAdmin) {
            return response()->json(['error' => 'Only admins can pin messages'], 403);
        }

        $message->update(['is_pinned' => !$message->is_pinned]);

        return response()->json([
            'success' => true,
            'message' => $message->is_pinned ? 'Message pinned' : 'Message unpinned',
            'data' => $message
        ]);
    }

    /**
     * Get group statistics
     */
    public function getStats($chat_group_id)
    {
        $user = Auth::user();

        // Check if user is member
        $isMember = ChatGroupUser::where('chat_group_id', $chat_group_id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMember) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $group = ChatGroup::withCount(['users', 'messages'])->find($chat_group_id);
        
        $stats = [
            'total_members' => $group->users_count,
            'total_messages' => $group->messages_count,
            'active_today' => ChatGroupUser::where('chat_group_id', $chat_group_id)
                ->where('last_read_message_id', '>', 0)
                ->count(),
            'created_at' => $group->created_at,
            'last_message_at' => $group->last_message_at,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Send a system message
     */
    private function sendSystemMessage($chat_group_id, $content)
    {
        ChatGroupMessage::create([
            'chat_group_id' => $chat_group_id,
            'sender_id' => 1, // Admin user or system user
            'message' => $content,
            'type' => 'system',
            'is_system_message' => true,
            'sent_at' => now(),
        ]);

        ChatGroup::where('id', $chat_group_id)->update([
            'last_message_at' => now(),
            'last_activity_at' => now(),
        ]);
        ChatGroup::where('id', $chat_group_id)->increment('messages_count');
    }
}