<?php
// database/seeders/ConversationSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ConversationSeeder extends Seeder
{
    public function run()
    {
        // Get all user IDs
        $userIds = DB::table('users')->pluck('id')->toArray();
        
        // Get group user IDs (role_id = 2) - these are the group creators
        $groupUserIds = DB::table('users')->where('role_id', 2)->pluck('id')->toArray();
        
        if (count($userIds) < 3) {
            $this->command->info('Not enough users to create conversations. Skipping.');
            return;
        }

        $now = Carbon::now();
        
        $conversations = [
            // ===== DIRECT MESSAGES (2 users) =====
            
            // Conversation 1: Admin (1) and Center Manager (2)
            [
                'type' => 'direct',
                'name' => null,
                'avatar' => null,
                'created_by' => 1,
                'group_id' => null, // No group linked
                'last_message_at' => Carbon::now()->subHours(2),
                'metadata' => json_encode(['source' => 'initial']),
                'created_at' => Carbon::now()->subDays(5),
                'updated_at' => Carbon::now()->subHours(2),
            ],
            // Conversation 2: Camper (3) and Group (4)
            [
                'type' => 'direct',
                'name' => null,
                'avatar' => null,
                'created_by' => 3,
                'group_id' => 4, // Linked to group user 4
                'last_message_at' => Carbon::now()->subDay(),
                'metadata' => json_encode(['source' => 'event_inquiry']),
                'created_at' => Carbon::now()->subDays(3),
                'updated_at' => Carbon::now()->subDay(),
            ],
            // Conversation 3: Forest Rangers (5) and Coastal Adventures (6)
            [
                'type' => 'direct',
                'name' => null,
                'avatar' => null,
                'created_by' => 5,
                'group_id' => 6, // Linked to group user 6
                'last_message_at' => Carbon::now()->subHours(5),
                'metadata' => json_encode(['source' => 'collaboration']),
                'created_at' => Carbon::now()->subDays(2),
                'updated_at' => Carbon::now()->subHours(5),
            ],
            // Conversation 4: Supplier (7) and Guide (8)
            [
                'type' => 'direct',
                'name' => null,
                'avatar' => null,
                'created_by' => 7,
                'group_id' => null, // No group linked
                'last_message_at' => Carbon::now()->subDays(2),
                'metadata' => json_encode(['source' => 'equipment']),
                'created_at' => Carbon::now()->subDays(7),
                'updated_at' => Carbon::now()->subDays(2),
            ],
            // Conversation 5: Sarah (9) and Mike (10) - active chat
            [
                'type' => 'direct',
                'name' => null,
                'avatar' => null,
                'created_by' => 9,
                'group_id' => null, // No group linked
                'last_message_at' => Carbon::now()->subMinutes(30),
                'metadata' => json_encode(['source' => 'friends']),
                'created_at' => Carbon::now()->subMonths(1),
                'updated_at' => Carbon::now()->subMinutes(30),
            ],
            
            // ===== GROUP CHATS (3+ users) - These are conversations OF group users =====
            
            // Group Chat 1: Sahara Camping Trip - Created by Group (4)
            [
                'type' => 'group',
                'name' => '🏕️ Sahara Camping Trip',
                'avatar' => null,
                'created_by' => 4, // Nejikh Group (role_id = 2)
                'group_id' => 4, // Linked to the group user that created it
                'last_message_at' => Carbon::now()->subHours(3),
                'metadata' => json_encode([
                    'description' => 'Planning our desert camping adventure',
                    'purpose' => 'event_planning',
                    'tags' => ['camping', 'sahara', 'adventure']
                ]),
                'created_at' => Carbon::now()->subWeeks(2),
                'updated_at' => Carbon::now()->subHours(3),
            ],
            // Group Chat 2: Hiking Enthusiasts - Created by Forest Rangers (5)
            [
                'type' => 'group',
                'name' => '🥾 North Tunisia Hikers',
                'avatar' => null,
                'created_by' => 5, // Forest Rangers (role_id = 2)
                'group_id' => 5, // Linked to the group user that created it
                'last_message_at' => Carbon::now()->subDay(),
                'metadata' => json_encode([
                    'description' => 'Group for hiking enthusiasts in northern Tunisia',
                    'purpose' => 'community',
                    'tags' => ['hiking', 'mountains', 'nature']
                ]),
                'created_at' => Carbon::now()->subMonths(1),
                'updated_at' => Carbon::now()->subDay(),
            ],
            // Group Chat 3: Equipment Exchange - Created by Supplier (7) - NOT a group user
            [
                'type' => 'group',
                'name' => '⛺ Camping Gear Exchange',
                'avatar' => null,
                'created_by' => 7, // Camp Equipment Supplier (role_id = 4)
                'group_id' => null, // No group linked (supplier is not a group user)
                'last_message_at' => Carbon::now()->subHours(8),
                'metadata' => json_encode([
                    'description' => 'Buy, sell, and trade camping equipment',
                    'purpose' => 'marketplace',
                    'tags' => ['gear', 'equipment', 'marketplace']
                ]),
                'created_at' => Carbon::now()->subWeeks(3),
                'updated_at' => Carbon::now()->subHours(8),
            ],
            // Group Chat 4: Summer Festival 2024 - Created by Admin (1)
            [
                'type' => 'group',
                'name' => '🎪 Summer Camp Festival 2024',
                'avatar' => null,
                'created_by' => 1, // Admin (role_id = 6)
                'group_id' => null, // No group linked (admin is not a group user)
                'last_message_at' => Carbon::now()->subMinutes(45),
                'metadata' => json_encode([
                    'description' => 'Organizing the annual summer camping festival',
                    'purpose' => 'event_planning',
                    'tags' => ['festival', 'summer', 'music']
                ]),
                'created_at' => Carbon::now()->subMonths(2),
                'updated_at' => Carbon::now()->subMinutes(45),
            ],
            // Group Chat 5: Young Campers - Created by Sarah (9) - Camper
            [
                'type' => 'group',
                'name' => '🌟 Young Adventurers Club',
                'avatar' => null,
                'created_by' => 9, // Sarah (role_id = 1 - camper)
                'group_id' => null, // No group linked (camper is not a group user)
                'last_message_at' => Carbon::now()->subHours(1),
                'metadata' => json_encode([
                    'description' => 'For young campers aged 18-25 to connect and plan trips',
                    'purpose' => 'social',
                    'tags' => ['youth', 'social', 'adventure']
                ]),
                'created_at' => Carbon::now()->subWeeks(1),
                'updated_at' => Carbon::now()->subHours(1),
            ],
        ];

        foreach ($conversations as $conv) {
            DB::table('conversations')->insert($conv);
        }

        $this->command->info('Conversations (DMs and Groups) created successfully.');
    }
}