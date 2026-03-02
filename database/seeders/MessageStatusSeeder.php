<?php
// database/seeders/MessageStatusSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MessageStatusSeeder extends Seeder
{
    public function run()
    {
        // Get messages
        $messages = DB::table('messages')->get();
        
        if ($messages->isEmpty()) {
            $this->command->info('No messages found. Skipping message statuses seeder.');
            return;
        }

        $statuses = [];

        foreach ($messages as $message) {
            // Convert created_at string to Carbon instance
            $messageCreatedAt = Carbon::parse($message->created_at);
            
            // Get all participants in this conversation except the sender
            $participants = DB::table('conversation_participants')
                ->where('conversation_id', $message->conversation_id)
                ->where('user_id', '!=', $message->sender_id)
                ->get();

            foreach ($participants as $participant) {
                // Randomly decide if message was delivered and read
                $deliveredAt = $messageCreatedAt->copy()->addMinutes(rand(1, 30));
                $readAt = rand(0, 1) ? $deliveredAt->copy()->addMinutes(rand(5, 120)) : null;

                $statuses[] = [
                    'message_id' => $message->id,
                    'user_id' => $participant->user_id,
                    'delivered_at' => $deliveredAt,
                    'read_at' => $readAt,
                    'created_at' => $message->created_at,
                    'updated_at' => $readAt ?? $deliveredAt,
                ];
            }

            // Also mark as read for sender
            $statuses[] = [
                'message_id' => $message->id,
                'user_id' => $message->sender_id,
                'delivered_at' => $message->created_at,
                'read_at' => $messageCreatedAt->copy()->addMinutes(1),
                'created_at' => $message->created_at,
                'updated_at' => $messageCreatedAt->copy()->addMinutes(1),
            ];
        }

        // Insert in chunks to avoid memory issues
        foreach (array_chunk($statuses, 100) as $chunk) {
            DB::table('message_statuses')->insert($chunk);
        }

        $this->command->info('Message statuses seeded successfully.');
    }
}