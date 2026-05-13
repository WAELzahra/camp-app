<?php
// database/seeders/MessageSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MessageSeeder extends Seeder
{
    public function run()
    {
        // Get conversation IDs
        $conversations = DB::table('conversations')->orderBy('id')->get();
        
        if ($conversations->isEmpty()) {
            $this->command->info('No conversations found. Skipping messages seeder.');
            return;
        }

        $messages = [];
        $now = Carbon::now();

        // ===== MESSAGES FOR CONVERSATION 1 (Admin & Center Manager) =====
        $messages[] = [
            'conversation_id' => $conversations[0]->id,
            'sender_id' => 1,
            'content' => 'Hello! How is the center doing?',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subDays(5)->addHours(1),
            'updated_at' => Carbon::now()->subDays(5)->addHours(1),
        ];
        $messages[] = [
            'conversation_id' => $conversations[0]->id,
            'sender_id' => 2,
            'content' => 'Everything is great! We have new camping equipment available.',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subDays(5)->addHours(2),
            'updated_at' => Carbon::now()->subDays(5)->addHours(2),
        ];
        $messages[] = [
            'conversation_id' => $conversations[0]->id,
            'sender_id' => 1,
            'content' => 'Perfect! Can you send me the list?',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subDays(5)->addHours(3),
            'updated_at' => Carbon::now()->subDays(5)->addHours(3),
        ];
        $messages[] = [
            'conversation_id' => $conversations[0]->id,
            'sender_id' => 2,
            'content' => 'Sure, I will email it to you.',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subDays(5)->addHours(4),
            'updated_at' => Carbon::now()->subDays(5)->addHours(4),
        ];
        $messages[] = [
            'conversation_id' => $conversations[0]->id,
            'sender_id' => 2,
            'content' => 'Thanks!',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subHours(2),
            'updated_at' => Carbon::now()->subHours(2),
        ];

        // ===== MESSAGES FOR CONVERSATION 2 (Camper & Group) =====
        $messages[] = [
            'conversation_id' => $conversations[1]->id,
            'sender_id' => 3,
            'content' => 'Hi! I am interested in your camping event next week.',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subDays(3)->addHours(2),
            'updated_at' => Carbon::now()->subDays(3)->addHours(2),
        ];
        $messages[] = [
            'conversation_id' => $conversations[1]->id,
            'sender_id' => 4,
            'content' => 'Great! We still have spots available. How many people?',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subDays(3)->addHours(3),
            'updated_at' => Carbon::now()->subDays(3)->addHours(3),
        ];
        $messages[] = [
            'conversation_id' => $conversations[1]->id,
            'sender_id' => 3,
            'content' => 'Just me and my friend. Two people.',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subDays(3)->addHours(4),
            'updated_at' => Carbon::now()->subDays(3)->addHours(4),
        ];
        $messages[] = [
            'conversation_id' => $conversations[1]->id,
            'sender_id' => 4,
            'content' => 'Perfect! I will reserve 2 spots for you.',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subDay()->addHours(5),
            'updated_at' => Carbon::now()->subDay()->addHours(5),
        ];
        $messages[] = [
            'conversation_id' => $conversations[1]->id,
            'sender_id' => 3,
            'content' => 'Thank you so much! 🏕️',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subDay()->addHours(6),
            'updated_at' => Carbon::now()->subDay()->addHours(6),
        ];

        // ===== MESSAGES FOR CONVERSATION 5 (Sarah & Mike - active chat) =====
        $messages[] = [
            'conversation_id' => $conversations[4]->id,
            'sender_id' => 9,
            'content' => 'Hey Mike! Are you going to the camping trip this weekend?',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subHours(5),
            'updated_at' => Carbon::now()->subHours(5),
        ];
        $messages[] = [
            'conversation_id' => $conversations[4]->id,
            'sender_id' => 10,
            'content' => 'Hey Sarah! Yes, I am definitely going! 🏕️',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subHours(4)->subMinutes(30),
            'updated_at' => Carbon::now()->subHours(4)->subMinutes(30),
        ];
        $messages[] = [
            'conversation_id' => $conversations[4]->id,
            'sender_id' => 9,
            'content' => 'Awesome! Should we bring extra firewood?',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subHours(4),
            'updated_at' => Carbon::now()->subHours(4),
        ];
        $messages[] = [
            'conversation_id' => $conversations[4]->id,
            'sender_id' => 10,
            'content' => 'Good idea! I will bring some. Also, do you have a tent?',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subHours(3),
            'updated_at' => Carbon::now()->subHours(3),
        ];
        $messages[] = [
            'conversation_id' => $conversations[4]->id,
            'sender_id' => 9,
            'content' => 'Yes, I have a 3-person tent. We can share if you want.',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subHours(2)->subMinutes(30),
            'updated_at' => Carbon::now()->subHours(2)->subMinutes(30),
        ];
        $messages[] = [
            'conversation_id' => $conversations[4]->id,
            'sender_id' => 10,
            'content' => 'That would be great! Thanks!',
            'type' => 'text',
            'reply_to_id' => 5,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subHours(2),
            'updated_at' => Carbon::now()->subHours(2),
        ];
        $messages[] = [
            'conversation_id' => $conversations[4]->id,
            'sender_id' => 9,
            'content' => 'No problem! See you Saturday morning!',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subHours(1)->subMinutes(30),
            'updated_at' => Carbon::now()->subHours(1)->subMinutes(30),
        ];
        $messages[] = [
            'conversation_id' => $conversations[4]->id,
            'sender_id' => 10,
            'content' => 'See you then! 👋',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subHours(1),
            'updated_at' => Carbon::now()->subHours(1),
        ];
        $messages[] = [
            'conversation_id' => $conversations[4]->id,
            'sender_id' => 10,
            'content' => 'I just bought marshmallows for the campfire! 🔥',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subMinutes(30),
            'updated_at' => Carbon::now()->subMinutes(30),
        ];

        // ===== GROUP CHAT MESSAGES =====
        
        // Group 1: Sahara Camping Trip messages
        $messages[] = [
            'conversation_id' => $conversations[5]->id,
            'sender_id' => 4,
            'content' => 'Welcome everyone to the Sahara Camping Trip group! 🏜️',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subWeeks(2)->addHours(1),
            'updated_at' => Carbon::now()->subWeeks(2)->addHours(1),
        ];
        $messages[] = [
            'conversation_id' => $conversations[5]->id,
            'sender_id' => 3,
            'content' => 'Thanks! So excited for this trip!',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subWeeks(2)->addDays(1)->addHours(2),
            'updated_at' => Carbon::now()->subWeeks(2)->addDays(1)->addHours(2),
        ];
        $messages[] = [
            'conversation_id' => $conversations[5]->id,
            'sender_id' => 9,
            'content' => 'What should we bring?',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subWeeks(2)->addDays(2)->addHours(3),
            'updated_at' => Carbon::now()->subWeeks(2)->addDays(2)->addHours(3),
        ];
        $messages[] = [
            'conversation_id' => $conversations[5]->id,
            'sender_id' => 4,
            'content' => 'Bring plenty of water, sunscreen, and warm clothes for the night.',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subWeeks(2)->addDays(2)->addHours(4),
            'updated_at' => Carbon::now()->subWeeks(2)->addDays(2)->addHours(4),
        ];
        $messages[] = [
            'conversation_id' => $conversations[5]->id,
            'sender_id' => 10,
            'content' => 'Is there any specific gear required?',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subWeeks(2)->addDays(3)->addHours(1),
            'updated_at' => Carbon::now()->subWeeks(2)->addDays(3)->addHours(1),
        ];
        $messages[] = [
            'conversation_id' => $conversations[5]->id,
            'sender_id' => 4,
            'content' => 'We will provide tents and sleeping bags. Just bring personal items.',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subWeeks(2)->addDays(3)->addHours(2),
            'updated_at' => Carbon::now()->subWeeks(2)->addDays(3)->addHours(2),
        ];
        $messages[] = [
            'conversation_id' => $conversations[5]->id,
            'sender_id' => 3,
            'content' => 'Great! See you all there!',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subHours(3),
            'updated_at' => Carbon::now()->subHours(3),
        ];

        // Group 3: Camping Gear Exchange messages
        $messages[] = [
            'conversation_id' => $conversations[7]->id,
            'sender_id' => 7,
            'content' => 'Welcome to Camping Gear Exchange! Buy, sell, or trade your gear here.',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subWeeks(3)->addHours(1),
            'updated_at' => Carbon::now()->subWeeks(3)->addHours(1),
        ];
        $messages[] = [
            'conversation_id' => $conversations[7]->id,
            'sender_id' => 3,
            'content' => 'I am looking for a lightweight hiking backpack. Anyone selling?',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subWeeks(2)->addHours(5),
            'updated_at' => Carbon::now()->subWeeks(2)->addHours(5),
        ];
        $messages[] = [
            'conversation_id' => $conversations[7]->id,
            'sender_id' => 7,
            'content' => 'I have a 50L Osprey backpack in excellent condition. DM me for details.',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subWeeks(2)->addDays(1)->addHours(3),
            'updated_at' => Carbon::now()->subWeeks(2)->addDays(1)->addHours(3),
        ];
        $messages[] = [
            'conversation_id' => $conversations[7]->id,
            'sender_id' => 9,
            'content' => 'Selling a practically new tent. Used once. Message me for photos.',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subWeeks(1)->addHours(2),
            'updated_at' => Carbon::now()->subWeeks(1)->addHours(2),
        ];
        $messages[] = [
            'conversation_id' => $conversations[7]->id,
            'sender_id' => 10,
            'content' => 'How much for the tent?',
            'type' => 'text',
            'reply_to_id' => 4,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subWeeks(1)->addHours(3),
            'updated_at' => Carbon::now()->subWeeks(1)->addHours(3),
        ];
        $messages[] = [
            'conversation_id' => $conversations[7]->id,
            'sender_id' => 9,
            'content' => '150 TND. It was 300 new.',
            'type' => 'text',
            'reply_to_id' => 5,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subWeeks(1)->addHours(4),
            'updated_at' => Carbon::now()->subWeeks(1)->addHours(4),
        ];
        $messages[] = [
            'conversation_id' => $conversations[7]->id,
            'sender_id' => 10,
            'content' => 'Deal! I will take it.',
            'type' => 'text',
            'reply_to_id' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'created_at' => Carbon::now()->subHours(8),
            'updated_at' => Carbon::now()->subHours(8),
        ];

        // Insert all messages
        foreach ($messages as $message) {
            DB::table('messages')->insert($message);
        }

        $this->command->info('Messages seeded successfully.');
    }
}