<?php
// database/seeders/MessageReactionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MessageReactionSeeder extends Seeder
{
    public function run()
    {
        // Get messages
        $messages = DB::table('messages')->where('type', 'text')->get();
        
        if ($messages->isEmpty()) {
            $this->command->info('No messages found. Skipping reactions seeder.');
            return;
        }

        $reactions = [];
        $emojis = ['👍', '❤️', '😂', '😮', '😢', '🔥', '🎉', '👏'];

        foreach ($messages as $message) {
            // Convert created_at string to Carbon instance
            $messageCreatedAt = Carbon::parse($message->created_at);
            
            // Get random participants to react
            $participants = DB::table('conversation_participants')
                ->where('conversation_id', $message->conversation_id)
                ->inRandomOrder()
                ->limit(rand(0, 3))
                ->get();

            foreach ($participants as $participant) {
                $reaction = $emojis[array_rand($emojis)];
                
                // Check if this reaction already exists for this user on this message
                $exists = DB::table('message_reactions')
                    ->where('message_id', $message->id)
                    ->where('user_id', $participant->user_id)
                    ->where('reaction', $reaction)
                    ->exists();

                if (!$exists) {
                    $reactionTime = $messageCreatedAt->copy()->addMinutes(rand(10, 300));
                    
                    $reactions[] = [
                        'message_id' => $message->id,
                        'user_id' => $participant->user_id,
                        'reaction' => $reaction,
                        'created_at' => $reactionTime,
                        'updated_at' => $reactionTime,
                    ];
                }
            }
        }

        if (!empty($reactions)) {
            // Insert in chunks to avoid memory issues
            foreach (array_chunk($reactions, 100) as $chunk) {
                DB::table('message_reactions')->insert($chunk);
            }
        }

        $this->command->info('Message reactions seeded successfully.');
    }
}