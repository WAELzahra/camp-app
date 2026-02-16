<?php

namespace Database\Seeders;

use App\Models\ChatGroup;
use App\Models\ChatGroupMessage;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChatMessagesSeeder extends Seeder
{
    public function run(): void
    {
        $groups = ChatGroup::all();
        
        if ($groups->isEmpty()) {
            $this->command->warn('No chat groups found. Please run ChatGroupsSeeder first.');
            return;
        }

        // Get specific users
        $camper = User::where('email', 'deadxshot660@gmail.com')->first();
        $centerManager = User::where('email', 'njkhouja@gmail.com')->first();
        $groupUser = User::where('email', 'nejikh57@gmail.com')->first();

        $messageTemplates = [
            'greetings' => [
                "Hey everyone! 👋",
                "Hello group! So excited for this!",
                "Hi all, glad to be here!",
                "Good morning/afternoon everyone!",
                "Hey folks, what's up?",
            ],
            'questions' => [
                "What gear should I bring for this trip?",
                "Has anyone been to this location before?",
                "What's the weather forecast looking like?",
                "Does anyone have recommendations for tents?",
                "What time should we meet up?",
            ],
            'info' => [
                "I checked the forecast - looks perfect! ☀️",
                "Just got a new tent, can't wait to try it out!",
                "The trail conditions are supposed to be excellent.",
                "I'll bring snacks for everyone! 🍪",
                "Don't forget bug spray! 🦟",
            ],
            'excitement' => [
                "So excited for this trip! 🏕️",
                "Countdown begins! Only 3 days left!",
                "The stars are supposed to be incredible this weekend ✨",
                "Can't wait for the bonfire! 🔥",
                "This is going to be epic!",
            ],
            'planning' => [
                "I think we should meet at the entrance at 10am.",
                "Does anyone need a ride? I have space in my car.",
                "We should split into groups for the hike.",
                "Who's in charge of food planning?",
                "I'll bring the camping stove and fuel.",
            ],
        ];

        $reactions = ['👍', '❤️', '😂', '🔥', '🎉'];

        foreach ($groups as $group) {
            // Get group members
            $members = $group->users()->wherePivot('status', 'active')->get();
            
            if ($members->isEmpty()) {
                continue;
            }

            $messageCount = rand(8, 15); // Smaller range for demo
            $messages = [];
            $lastMessageTime = $group->created_at;

            for ($i = 0; $i < $messageCount; $i++) {
                // Pick random category and message
                $category = array_rand($messageTemplates);
                $template = $messageTemplates[$category][array_rand($messageTemplates[$category])];
                
                // Random sender from members (prefer the camper for some messages)
                if ($camper && $members->contains('id', $camper->id) && rand(0, 10) > 7) {
                    $sender = $camper;
                } elseif ($centerManager && $members->contains('id', $centerManager->id) && rand(0, 10) > 8) {
                    $sender = $centerManager;
                } elseif ($groupUser && $members->contains('id', $groupUser->id) && rand(0, 10) > 8) {
                    $sender = $groupUser;
                } else {
                    $sender = $members->random();
                }
                
                // Random time increment
                $timeIncrement = rand(30, 180); // minutes
                $messageTime = $lastMessageTime->copy()->addMinutes($timeIncrement);
                
                // Don't create messages in the future
                if ($messageTime > now()) {
                    $messageTime = now()->subMinutes(rand(1, 60));
                }

                // Create message
                $message = ChatGroupMessage::create([
                    'chat_group_id' => $group->id,
                    'sender_id' => $sender->id,
                    'message' => $template,
                    'type' => 'text',
                    'is_edited' => rand(0, 10) > 9,
                    'is_pinned' => $i === 2 && rand(0, 10) > 5,
                    'is_system_message' => false,
                    'sent_at' => $messageTime,
                    'created_at' => $messageTime,
                    'updated_at' => $messageTime,
                ]);

                $messages[] = ['id' => $message->id, 'time' => $messageTime];
                $lastMessageTime = $messageTime;

                // Add reactions to some messages
                if (rand(0, 10) > 6) { // 40% chance
                    $reactionCount = rand(1, 2);
                    for ($r = 0; $r < $reactionCount; $r++) {
                        $reactor = $members->random();
                        $reaction = $reactions[array_rand($reactions)];
                        
                        try {
                            $message->reactions()->create([
                                'user_id' => $reactor->id,
                                'reaction' => $reaction,
                                'created_at' => $messageTime->copy()->addMinutes(rand(1, 30)),
                            ]);
                        } catch (\Exception $e) {
                            // Skip duplicate reactions
                        }
                    }
                }
            }

            // Update group stats
            $group->update([
                'messages_count' => $messageCount,
                'last_message_at' => $lastMessageTime,
                'last_activity_at' => $lastMessageTime,
            ]);

            $this->command->info("Created {$messageCount} messages for group: {$group->name}");
        }

        // Create welcome messages for groups that have the camper
        $groupsWithCamper = ChatGroup::whereHas('users', function($q) use ($camper) {
            $q->where('user_id', $camper->id);
        })->get();

        foreach ($groupsWithCamper->take(2) as $group) {
            ChatGroupMessage::create([
                'chat_group_id' => $group->id,
                'sender_id' => $camper->id,
                'message' => "Thanks for adding me to the group! Can't wait for the camping trip! 🏕️",
                'type' => 'text',
                'is_system_message' => false,
                'sent_at' => $group->created_at->copy()->addHours(2),
                'created_at' => $group->created_at->copy()->addHours(2),
                'updated_at' => $group->created_at->copy()->addHours(2),
            ]);
        }

        $this->command->info('Chat messages created successfully!');
    }
}