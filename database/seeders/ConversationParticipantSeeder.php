<?php
// database/seeders/ConversationParticipantSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ConversationParticipantSeeder extends Seeder
{
    public function run()
    {
        // Get conversation IDs in order
        $conversations = DB::table('conversations')->orderBy('id')->get();
        
        if ($conversations->isEmpty()) {
            $this->command->info('No conversations found. Skipping participants seeder.');
            return;
        }

        $participants = [];
        $now = Carbon::now();

        // ===== DIRECT MESSAGE PARTICIPANTS =====
        
        // Conversation 1: Admin (1) and Center Manager (2)
        $participants[] = [
            'conversation_id' => $conversations[0]->id,
            'user_id' => 1,
            'role' => 'member',
            'last_read_at' => Carbon::now()->subHours(2),
            'joined_at' => Carbon::now()->subDays(5),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subDays(5),
            'updated_at' => Carbon::now()->subHours(2),
        ];
        $participants[] = [
            'conversation_id' => $conversations[0]->id,
            'user_id' => 2,
            'role' => 'member',
            'last_read_at' => Carbon::now()->subHours(3),
            'joined_at' => Carbon::now()->subDays(5),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subDays(5),
            'updated_at' => Carbon::now()->subHours(3),
        ];

        // Conversation 2: Camper (3) and Group (4)
        $participants[] = [
            'conversation_id' => $conversations[1]->id,
            'user_id' => 3,
            'role' => 'member',
            'last_read_at' => Carbon::now()->subDay(),
            'joined_at' => Carbon::now()->subDays(3),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subDays(3),
            'updated_at' => Carbon::now()->subDay(),
        ];
        $participants[] = [
            'conversation_id' => $conversations[1]->id,
            'user_id' => 4,
            'role' => 'member',
            'last_read_at' => Carbon::now()->subDay()->addHours(2),
            'joined_at' => Carbon::now()->subDays(3),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subDays(3),
            'updated_at' => Carbon::now()->subDay()->addHours(2),
        ];

        // Conversation 3: Forest Rangers (5) and Coastal Adventures (6)
        $participants[] = [
            'conversation_id' => $conversations[2]->id,
            'user_id' => 5,
            'role' => 'member',
            'last_read_at' => Carbon::now()->subHours(5),
            'joined_at' => Carbon::now()->subDays(2),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subDays(2),
            'updated_at' => Carbon::now()->subHours(5),
        ];
        $participants[] = [
            'conversation_id' => $conversations[2]->id,
            'user_id' => 6,
            'role' => 'member',
            'last_read_at' => Carbon::now()->subHours(6),
            'joined_at' => Carbon::now()->subDays(2),
            'left_at' => null,
            'is_muted' => true,
            'created_at' => Carbon::now()->subDays(2),
            'updated_at' => Carbon::now()->subHours(6),
        ];

        // Conversation 4: Supplier (7) and Guide (8)
        $participants[] = [
            'conversation_id' => $conversations[3]->id,
            'user_id' => 7,
            'role' => 'member',
            'last_read_at' => Carbon::now()->subDays(2),
            'joined_at' => Carbon::now()->subDays(7),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subDays(7),
            'updated_at' => Carbon::now()->subDays(2),
        ];
        $participants[] = [
            'conversation_id' => $conversations[3]->id,
            'user_id' => 8,
            'role' => 'member',
            'last_read_at' => Carbon::now()->subDays(2)->addHours(5),
            'joined_at' => Carbon::now()->subDays(7),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subDays(7),
            'updated_at' => Carbon::now()->subDays(2)->addHours(5),
        ];

        // Conversation 5: Sarah (9) and Mike (10)
        $participants[] = [
            'conversation_id' => $conversations[4]->id,
            'user_id' => 9,
            'role' => 'member',
            'last_read_at' => Carbon::now()->subMinutes(30),
            'joined_at' => Carbon::now()->subMonths(1),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subMonths(1),
            'updated_at' => Carbon::now()->subMinutes(30),
        ];
        $participants[] = [
            'conversation_id' => $conversations[4]->id,
            'user_id' => 10,
            'role' => 'member',
            'last_read_at' => Carbon::now()->subMinutes(45),
            'joined_at' => Carbon::now()->subMonths(1),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subMonths(1),
            'updated_at' => Carbon::now()->subMinutes(45),
        ];

        // ===== GROUP CHAT PARTICIPANTS =====

        // Group 1: Sahara Camping Trip (created by user 4)
        $participants[] = [
            'conversation_id' => $conversations[5]->id,
            'user_id' => 4, // Creator - Nejikh Group
            'role' => 'admin',
            'last_read_at' => Carbon::now()->subHours(3),
            'joined_at' => Carbon::now()->subWeeks(2),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subWeeks(2),
            'updated_at' => Carbon::now()->subHours(3),
        ];
        $participants[] = [
            'conversation_id' => $conversations[5]->id,
            'user_id' => 3, // DeadXShot Camper
            'role' => 'member',
            'last_read_at' => Carbon::now()->subHours(4),
            'joined_at' => Carbon::now()->subWeeks(2)->addDays(1),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subWeeks(2)->addDays(1),
            'updated_at' => Carbon::now()->subHours(4),
        ];
        $participants[] = [
            'conversation_id' => $conversations[5]->id,
            'user_id' => 9, // Sarah
            'role' => 'member',
            'last_read_at' => Carbon::now()->subHours(5),
            'joined_at' => Carbon::now()->subWeeks(2)->addDays(2),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subWeeks(2)->addDays(2),
            'updated_at' => Carbon::now()->subHours(5),
        ];
        $participants[] = [
            'conversation_id' => $conversations[5]->id,
            'user_id' => 10, // Mike
            'role' => 'member',
            'last_read_at' => Carbon::now()->subHours(6),
            'joined_at' => Carbon::now()->subWeeks(2)->addDays(3),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subWeeks(2)->addDays(3),
            'updated_at' => Carbon::now()->subHours(6),
        ];

        // Group 2: North Tunisia Hikers (created by user 5)
        $participants[] = [
            'conversation_id' => $conversations[6]->id,
            'user_id' => 5, // Forest Rangers
            'role' => 'admin',
            'last_read_at' => Carbon::now()->subDay(),
            'joined_at' => Carbon::now()->subMonths(1),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subMonths(1),
            'updated_at' => Carbon::now()->subDay(),
        ];
        $participants[] = [
            'conversation_id' => $conversations[6]->id,
            'user_id' => 6, // Coastal Adventures
            'role' => 'admin',
            'last_read_at' => Carbon::now()->subDay()->addHours(2),
            'joined_at' => Carbon::now()->subMonths(1)->addDays(1),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subMonths(1)->addDays(1),
            'updated_at' => Carbon::now()->subDay()->addHours(2),
        ];
        $participants[] = [
            'conversation_id' => $conversations[6]->id,
            'user_id' => 3, // DeadXShot Camper
            'role' => 'member',
            'last_read_at' => Carbon::now()->subDays(2),
            'joined_at' => Carbon::now()->subMonths(1)->addDays(3),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subMonths(1)->addDays(3),
            'updated_at' => Carbon::now()->subDays(2),
        ];
        $participants[] = [
            'conversation_id' => $conversations[6]->id,
            'user_id' => 8, // Ahmed Guide
            'role' => 'member',
            'last_read_at' => Carbon::now()->subDay(),
            'joined_at' => Carbon::now()->subMonths(1)->addDays(5),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subMonths(1)->addDays(5),
            'updated_at' => Carbon::now()->subDay(),
        ];

        // Group 3: Camping Gear Exchange (created by user 7)
        $participants[] = [
            'conversation_id' => $conversations[7]->id,
            'user_id' => 7, // Camp Equipment Supplier
            'role' => 'admin',
            'last_read_at' => Carbon::now()->subHours(8),
            'joined_at' => Carbon::now()->subWeeks(3),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subWeeks(3),
            'updated_at' => Carbon::now()->subHours(8),
        ];
        $participants[] = [
            'conversation_id' => $conversations[7]->id,
            'user_id' => 3, // DeadXShot Camper
            'role' => 'member',
            'last_read_at' => Carbon::now()->subHours(10),
            'joined_at' => Carbon::now()->subWeeks(3)->addDays(2),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subWeeks(3)->addDays(2),
            'updated_at' => Carbon::now()->subHours(10),
        ];
        $participants[] = [
            'conversation_id' => $conversations[7]->id,
            'user_id' => 9, // Sarah
            'role' => 'member',
            'last_read_at' => Carbon::now()->subHours(12),
            'joined_at' => Carbon::now()->subWeeks(3)->addDays(3),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subWeeks(3)->addDays(3),
            'updated_at' => Carbon::now()->subHours(12),
        ];
        $participants[] = [
            'conversation_id' => $conversations[7]->id,
            'user_id' => 10, // Mike
            'role' => 'member',
            'last_read_at' => Carbon::now()->subHours(9),
            'joined_at' => Carbon::now()->subWeeks(3)->addDays(4),
            'left_at' => null,
            'is_muted' => true,
            'created_at' => Carbon::now()->subWeeks(3)->addDays(4),
            'updated_at' => Carbon::now()->subHours(9),
        ];

        // Group 4: Summer Camp Festival (created by user 1 - Admin)
        $participants[] = [
            'conversation_id' => $conversations[8]->id,
            'user_id' => 1, // Admin
            'role' => 'admin',
            'last_read_at' => Carbon::now()->subMinutes(45),
            'joined_at' => Carbon::now()->subMonths(2),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subMonths(2),
            'updated_at' => Carbon::now()->subMinutes(45),
        ];
        $participants[] = [
            'conversation_id' => $conversations[8]->id,
            'user_id' => 2, // Center Manager
            'role' => 'admin',
            'last_read_at' => Carbon::now()->subHours(1),
            'joined_at' => Carbon::now()->subMonths(2)->addDays(1),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subMonths(2)->addDays(1),
            'updated_at' => Carbon::now()->subHours(1),
        ];
        $participants[] = [
            'conversation_id' => $conversations[8]->id,
            'user_id' => 4, // Nejikh Group
            'role' => 'member',
            'last_read_at' => Carbon::now()->subHours(2),
            'joined_at' => Carbon::now()->subMonths(2)->addDays(3),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subMonths(2)->addDays(3),
            'updated_at' => Carbon::now()->subHours(2),
        ];
        $participants[] = [
            'conversation_id' => $conversations[8]->id,
            'user_id' => 5, // Forest Rangers
            'role' => 'member',
            'last_read_at' => Carbon::now()->subHours(3),
            'joined_at' => Carbon::now()->subMonths(2)->addDays(4),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subMonths(2)->addDays(4),
            'updated_at' => Carbon::now()->subHours(3),
        ];
        $participants[] = [
            'conversation_id' => $conversations[8]->id,
            'user_id' => 6, // Coastal Adventures
            'role' => 'member',
            'last_read_at' => Carbon::now()->subHours(4),
            'joined_at' => Carbon::now()->subMonths(2)->addDays(5),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subMonths(2)->addDays(5),
            'updated_at' => Carbon::now()->subHours(4),
        ];

        // Group 5: Young Adventurers Club (created by user 9 - Sarah)
        $participants[] = [
            'conversation_id' => $conversations[9]->id,
            'user_id' => 9, // Sarah
            'role' => 'admin',
            'last_read_at' => Carbon::now()->subHours(1),
            'joined_at' => Carbon::now()->subWeeks(1),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subWeeks(1),
            'updated_at' => Carbon::now()->subHours(1),
        ];
        $participants[] = [
            'conversation_id' => $conversations[9]->id,
            'user_id' => 3, // DeadXShot Camper
            'role' => 'member',
            'last_read_at' => Carbon::now()->subHours(2),
            'joined_at' => Carbon::now()->subWeeks(1)->addDay(),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subWeeks(1)->addDay(),
            'updated_at' => Carbon::now()->subHours(2),
        ];
        $participants[] = [
            'conversation_id' => $conversations[9]->id,
            'user_id' => 10, // Mike
            'role' => 'member',
            'last_read_at' => Carbon::now()->subHours(3),
            'joined_at' => Carbon::now()->subWeeks(1)->addDay(),
            'left_at' => null,
            'is_muted' => false,
            'created_at' => Carbon::now()->subWeeks(1)->addDay(),
            'updated_at' => Carbon::now()->subHours(3),
        ];

        // Insert all participants
        foreach ($participants as $participant) {
            DB::table('conversation_participants')->insert($participant);
        }

        $this->command->info('Conversation participants seeded successfully.');
    }
}