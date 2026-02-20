<?php

namespace Database\Seeders;

use App\Models\ChatGroup;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ChatGroupsSeeder extends Seeder
{
    public function run(): void
    {
        // Get the specific group user (nejikh57@gmail.com) and other group users
        $nejikhGroupUser = User::where('email', 'nejikh57@gmail.com')->first();
        $otherGroupUsers = User::where('role_id', 2)->where('email', '!=', 'nejikh57@gmail.com')->get();
        $groupUsers = $otherGroupUsers->push($nejikhGroupUser); // Combine with nejikh as priority

        if ($groupUsers->isEmpty()) {
            $this->command->error('No group users found. Please run UserSeeder first.');
            return;
        }

        // Get users by role for members
        $campers = User::where('role_id', 1)->get();
        $guides = User::where('role_id', 5)->get();
        $suppliers = User::where('role_id', 4)->get();
        
        // Get specific users
        $centerManager = User::where('email', 'njkhouja@gmail.com')->first();
        $camper = User::where('email', 'deadxshot660@gmail.com')->first();

        // 1. Event-based groups (created by nejikh57@gmail.com)
        $eventGroups = [
            [
                'name' => '🏕️ Summer Camp 2025',
                'description' => 'Join us for an amazing summer camping experience! Discuss plans, share tips, and connect with fellow campers. We\'ll be at the beautiful beach campsite from July 15-20.',
                'type' => 'event',
                'is_private' => false,
                'max_members' => 50,
                'members_count' => 1,
                'messages_count' => 0,
                'created_by_email' => 'nejikh57@gmail.com',
            ],
            [
                'name' => '⛰️ Atlas Mountain Trek',
                'description' => 'Group for the upcoming Atlas Mountain trek. Prepare together, share gear lists, and get excited for this 5-day adventure through the mountains.',
                'type' => 'event',
                'is_private' => false,
                'max_members' => 20,
                'members_count' => 1,
                'messages_count' => 0,
                'created_by_email' => 'nejikh57@gmail.com',
            ],
            [
                'name' => '🏖️ Beach Camping Weekend',
                'description' => 'Planning a beach camping weekend in Hammamet. All welcome! Bring your tents and enthusiasm for a weekend of sun, sand, and campfires.',
                'type' => 'event',
                'is_private' => false,
                'max_members' => 30,
                'members_count' => 1,
                'messages_count' => 0,
                'created_by_email' => 'nejikh57@gmail.com',
            ],
        ];

        // 2. Interest-based groups
        $interestGroups = [
            [
                'name' => '🔥 Bonfire Stories',
                'description' => 'Share your best camping stories, legends, and experiences around the virtual bonfire.',
                'type' => 'interest',
                'is_private' => false,
                'max_members' => 100,
                'members_count' => 1,
                'messages_count' => 0,
                'created_by_email' => null, // Will be randomly assigned
            ],
            [
                'name' => '🥾 Hiking Enthusiasts',
                'description' => 'For those who love hiking and trekking. Share trail recommendations, organize group hikes, discuss gear.',
                'type' => 'interest',
                'is_private' => false,
                'max_members' => null,
                'members_count' => 1,
                'messages_count' => 0,
                'created_by_email' => null,
            ],
            [
                'name' => '📸 Nature Photography',
                'description' => 'Share your best nature shots, photography tips, and camping photography spots.',
                'type' => 'interest',
                'is_private' => false,
                'max_members' => 75,
                'members_count' => 1,
                'messages_count' => 0,
                'created_by_email' => null,
            ],
            [
                'name' => '🎣 Fishing Camp',
                'description' => 'Discuss fishing spots, techniques, and camping near water.',
                'type' => 'interest',
                'is_private' => false,
                'max_members' => 50,
                'members_count' => 1,
                'messages_count' => 0,
                'created_by_email' => null,
            ],
        ];

        // 3. Private groups
        $privateGroups = [
            [
                'name' => '🤫 Secret Planning Committee',
                'description' => 'Private group for organizing surprise camping events and secret gatherings.',
                'type' => 'private',
                'is_private' => true,
                'max_members' => 10,
                'members_count' => 1,
                'messages_count' => 0,
                'created_by_email' => 'nejikh57@gmail.com',
            ],
        ];

        // 4. Announcement groups
        $announcementGroups = [
            [
                'name' => '📢 TunisiaCamp Announcements',
                'description' => 'Official announcements from TunisiaCamp. Important updates, new features, and community news.',
                'type' => 'announcement',
                'is_private' => false,
                'max_members' => null,
                'members_count' => 1,
                'messages_count' => 0,
                'created_by_email' => null,
            ],
        ];

        // Combine all groups
        $allGroups = array_merge($eventGroups, $interestGroups, $privateGroups, $announcementGroups);
        
        foreach ($allGroups as $groupData) {
            // Determine creator
            $creator = null;
            if ($groupData['created_by_email']) {
                $creator = User::where('email', $groupData['created_by_email'])->first();
            }
            
            if (!$creator) {
                $creator = $groupUsers->random();
            }
            
            // Generate unique invitation token
            $token = Str::random(32);
            
            $group = ChatGroup::create([
                'group_user_id' => $creator->id,
                'name' => $groupData['name'],
                'description' => $groupData['description'],
                'invitation_token' => $token,
                'invitation_expires_at' => now()->addMonths(6),
                'type' => $groupData['type'],
                'is_private' => $groupData['is_private'],
                'max_members' => $groupData['max_members'],
                'members_count' => 1,
                'messages_count' => 0,
                'is_active' => true,
                'is_archived' => false,
                'last_activity_at' => now()->subHours(rand(1, 72)),
                'created_at' => now()->subDays(rand(1, 30)),
                'updated_at' => now(),
            ]);

            // Add creator as admin
            $group->users()->attach($creator->id, [
                'role' => 'admin',
                'status' => 'active',
                'joined_at' => $group->created_at,
                'notifications_enabled' => true,
                'notification_mode' => 'all',
            ]);

            // Add key members
            $membersToAdd = [];
            
            // Always add the camper (deadxshot660@gmail.com) to some groups
            if ($camper && rand(0, 1)) {
                $membersToAdd[] = $camper->id;
            }
            
            // Add center manager to some groups
            if ($centerManager && rand(0, 1)) {
                $membersToAdd[] = $centerManager->id;
            }
            
            // Add random members
            $availableUsers = $campers->merge($guides)->merge($suppliers)->shuffle();
            $memberCount = rand(3, 7);
            
            for ($i = 0; $i < $memberCount; $i++) {
                if ($i < $availableUsers->count()) {
                    $member = $availableUsers[$i];
                    if ($member->id !== $creator->id && !in_array($member->id, $membersToAdd)) {
                        $membersToAdd[] = $member->id;
                    }
                }
            }
            
            // Attach members
            foreach ($membersToAdd as $memberId) {
                $joinedAt = $group->created_at->copy()->addHours(rand(1, 48));
                
                $group->users()->attach($memberId, [
                    'role' => 'member',
                    'status' => rand(0, 10) > 8 ? 'muted' : 'active',
                    'joined_at' => $joinedAt,
                    'muted_until' => rand(0, 10) > 8 ? now()->addHours(24) : null,
                    'notifications_enabled' => rand(0, 10) > 2,
                    'notification_mode' => ['all', 'mentions', 'none'][rand(0, 2)],
                ]);
                
                $group->increment('members_count');
            }
        }

        $this->command->info('Chat groups created successfully!');
        $this->command->info('✓ Group user: nejikh57@gmail.com created groups');
        $this->command->info('✓ Members include: deadxshot660@gmail.com and njkhouja@gmail.com');
    }
}