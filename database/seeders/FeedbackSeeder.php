<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class FeedbackSeeder extends Seeder
{
    public function run()
    {
        // Clear existing feedbacks to avoid duplicates
        DB::table('feedbacks')->truncate();

        $feedbacks = [];
        $now = Carbon::now();

        // ==================== FEEDBACK FOR MATERIELS (Equipment) ====================
        // Materiel IDs: 1-7 (from your MateriellesSeeder)
        
        // --- Materiel 1: Tente Quechua 2 Secondes 3P (ID: 1) ---
        $feedbacks = array_merge($feedbacks, $this->generateMaterielFeedbacks(1, [
            'Excellent tent! Very easy to set up, perfect for weekend camping.',
            'Used this for a 3-day trip, very comfortable and waterproof.',
            'Great quality, but a bit heavy to carry for long hikes.',
            'Perfect for two people with gear. Would rent again!',
            'The setup time is truly 2 seconds. Amazing design!',
        ], [5, 5, 4, 5, 5], [3, 9, 10, 3, 9]));

        // --- Materiel 2: Sac de Couchage Forclaz -5°C (ID: 2) ---
        $feedbacks = array_merge($feedbacks, $this->generateMaterielFeedbacks(2, [
            'Very warm sleeping bag, kept me comfortable at 0°C.',
            'Good quality, but a bit bulky for backpacking.',
            'Perfect for winter camping in Tunisia.',
            'The zipper got stuck a few times, but overall good.',
            'Worth every dinar! Very comfortable.',
        ], [5, 4, 5, 3, 5], [3, 9, 10, 3, 9]));

        // --- Materiel 3: Chaise Pliante Camping (ID: 3) ---
        $feedbacks = array_merge($feedbacks, $this->generateMaterielFeedbacks(3, [
            'Very comfortable chair, easy to fold and carry.',
            'Perfect for campfire evenings.',
            'Sturdy and stable, even on uneven ground.',
            'Lightweight but feels durable.',
            'Great value for money.',
        ], [5, 5, 4, 5, 4], [3, 9, 10, 3, 9]));

        // --- Materiel 4: Lampe Frontale LED 350 Lm (ID: 4) ---
        $feedbacks = array_merge($feedbacks, $this->generateMaterielFeedbacks(4, [
            'Very bright, perfect for night hiking.',
            'Battery lasts long, USB charging is convenient.',
            'Comfortable to wear, doesn\'t bounce when running.',
            'Great light output for the price.',
            'Water resistant, used it in light rain with no issues.',
        ], [5, 4, 5, 4, 5], [3, 9, 10, 3, 9]));

        // --- Materiel 5: Kit Gamelle Inox 4 pièces (ID: 5) ---
        $feedbacks = array_merge($feedbacks, $this->generateMaterielFeedbacks(5, [
            'Great cooking set, everything fits perfectly.',
            'Durable and easy to clean.',
            'Perfect for camp cooking, heats evenly.',
            'A bit heavy but worth it for the quality.',
            'Everything you need for two people.',
        ], [5, 4, 5, 4, 5], [3, 9, 10, 3, 9]));

        // --- Materiel 6: Sac à Dos Trek 50L Forclaz (ID: 6) ---
        $feedbacks = array_merge($feedbacks, $this->generateMaterielFeedbacks(6, [
            'Very comfortable backpack, great for long treks.',
            'Lots of pockets and compartments, very practical.',
            'The back support system is excellent.',
            'Durable material, survived rough terrain.',
            'Perfect size for weekend trips.',
        ], [5, 5, 4, 5, 5], [3, 9, 10, 3, 9]));

        // --- Materiel 7: Tente Dôme 4 Saisons 4P (ID: 7) ---
        $feedbacks = array_merge($feedbacks, $this->generateMaterielFeedbacks(7, [
            'Excellent tent, survived strong winds without issues.',
            'Spacious and well-ventilated.',
            'Professional quality, worth the rental price.',
            'Perfect for family camping.',
            'Easy to set up for a tent this size.',
        ], [5, 5, 5, 5, 4], [3, 9, 10, 3, 9]));

        // ==================== FEEDBACK FOR SUPPLIERS (target_id) ====================
        // Feedback for supplier user ID 7 (Camp Equipment supplier)
        $feedbacks = array_merge($feedbacks, $this->generateSupplierFeedbacks(7, [
            'Excellent quality camping equipment! The tent was sturdy and the sleeping bags were warm.',
            'Great supplier, fast shipping and great customer service.',
            'Very responsive to questions and concerns.',
            'High quality gear at reasonable prices.',
            'Will definitely order from them again.',
        ], [5, 5, 5, 4, 5], [3, 9, 10, 3, 9]));

        // ==================== FEEDBACK WITH RESPONSES FROM SUPPLIERS ====================
        
        // Feedback with supplier response (target_id is the supplier user)
        $feedbacks[] = [
            'user_id' => 3,
            'target_id' => 7, // Camp Equipment supplier user ID
            'event_id' => null,
            'zone_id' => null,
            'materielle_id' => null,
            'contenu' => 'The delivery was faster than expected! Great service.',
            'response' => 'Thank you for your feedback! We strive to provide fast delivery.',
            'note' => 5,
            'type' => 'fournisseur',
            'status' => 'approved',
            'created_at' => $now->copy()->subDays(15),
            'updated_at' => $now->copy()->subDays(14),
        ];

        $feedbacks[] = [
            'user_id' => 9,
            'target_id' => 7,
            'event_id' => null,
            'zone_id' => null,
            'materielle_id' => null,
            'contenu' => 'The equipment was in perfect condition. Would rent again.',
            'response' => 'We\'re glad you enjoyed it! Looking forward to serving you again.',
            'note' => 5,
            'type' => 'fournisseur',
            'status' => 'approved',
            'created_at' => $now->copy()->subDays(20),
            'updated_at' => $now->copy()->subDays(19),
        ];

        // ==================== PENDING FEEDBACKS (awaiting moderation) ====================
        $feedbacks = array_merge($feedbacks, $this->generatePendingMaterielFeedbacks(7, [
            'Great quality, will recommend to friends.',
            'The tent was like new, very clean.',
            'Excellent customer service.',
        ], [5, 5, 5], [3, 9, 10]));

        // ==================== REJECTED FEEDBACKS (inappropriate) ====================
        $feedbacks[] = [
            'user_id' => 10,
            'target_id' => null,
            'event_id' => null,
            'zone_id' => null,
            'materielle_id' => 1,
            'contenu' => 'This feedback is inappropriate and contains offensive language.',
            'response' => null,
            'note' => 1,
            'type' => 'materielle',
            'status' => 'rejected',
            'created_at' => $now->copy()->subDays(30),
            'updated_at' => $now->copy()->subDays(29),
        ];

        // ==================== FEEDBACK FOR ZONES ====================
        $feedbacks = array_merge($feedbacks, $this->generateZoneFeedbacks(1, [
            'Beautiful camping zone, well-maintained facilities.',
            'Great hiking trails nearby.',
            'The scenery is absolutely breathtaking.',
            'Peaceful and quiet, perfect for relaxation.',
        ], [5, 4, 5, 5], [3, 9, 10, 3]));

        // ==================== FEEDBACK FOR EVENTS ====================
        $feedbacks = array_merge($feedbacks, $this->generateEventFeedbacks(1, [
            'Amazing event! Well organized and great atmosphere.',
            'The guides were very knowledgeable.',
            'Met wonderful people, had a great time.',
            'Will definitely join future events.',
        ], [5, 5, 5, 5], [3, 9, 10, 3]));

        // ==================== FEEDBACK FOR CENTERS (target_id) ====================
        $feedbacks = array_merge($feedbacks, $this->generateCenterFeedbacks(2, [
            'Excellent camping center! Very clean facilities and friendly staff.',
            'Great location with amazing views of the mountains.',
            'The staff went above and beyond to help us.',
            'Perfect for a family weekend getaway.',
            'Beautiful natural surroundings. Will definitely come back!',
        ], [5, 5, 5, 4, 5], [3, 9, 10, 3, 9]));

        // ==================== ADDITIONAL RANDOM FEEDBACK FOR VARIETY ====================
        
        // Generate 50 random materiel feedbacks for more realistic data
        for ($i = 1; $i <= 50; $i++) {
            $materielId = rand(1, 7);
            $userId = rand(3, 10);
            $rating = rand(3, 5);
            $status = rand(0, 10) > 1 ? 'approved' : ($rating < 3 ? 'rejected' : 'pending');
            
            $feedbacks[] = [
                'user_id' => $userId,
                'target_id' => null,
                'event_id' => null,
                'zone_id' => null,
                'materielle_id' => $materielId,
                'contenu' => $this->getRandomMaterielFeedback(),
                'response' => rand(0, 1) && $status === 'approved' ? $this->getRandomSupplierResponse() : null,
                'note' => $rating,
                'type' => 'materielle',
                'status' => $status,
                'created_at' => $now->copy()->subDays(rand(1, 90)),
                'updated_at' => $now->copy()->subDays(rand(1, 90)),
            ];
        }

        // Generate 30 random supplier feedbacks
        for ($i = 1; $i <= 30; $i++) {
            $supplierId = rand(7, 10); // Supplier user IDs (7 is Camp Equipment, 8, 9, 10 are campers but we'll use them as suppliers for demo)
            $userId = rand(3, 10);
            $rating = rand(3, 5);
            $status = rand(0, 10) > 1 ? 'approved' : 'pending';
            
            $feedbacks[] = [
                'user_id' => $userId,
                'target_id' => $supplierId,
                'event_id' => null,
                'zone_id' => null,
                'materielle_id' => null,
                'contenu' => $this->getRandomSupplierFeedback(),
                'response' => rand(0, 1) && $status === 'approved' ? $this->getRandomSupplierResponse() : null,
                'note' => $rating,
                'type' => 'fournisseur',
                'status' => $status,
                'created_at' => $now->copy()->subDays(rand(1, 90)),
                'updated_at' => $now->copy()->subDays(rand(1, 90)),
            ];
        }

        // Insert all feedbacks
        DB::table('feedbacks')->insert($feedbacks);
        
        $this->command->info('✅ ' . count($feedbacks) . ' feedbacks inserted successfully.');
        $this->command->info('   - Approved: ' . count(array_filter($feedbacks, fn($f) => $f['status'] === 'approved')));
        $this->command->info('   - Pending: ' . count(array_filter($feedbacks, fn($f) => $f['status'] === 'pending')));
        $this->command->info('   - Rejected: ' . count(array_filter($feedbacks, fn($f) => $f['status'] === 'rejected')));
    }

    /**
     * Generate feedbacks for a specific materiel
     */
    private function generateMaterielFeedbacks($materielId, $comments, $ratings, $userIds)
    {
        $feedbacks = [];
        $now = Carbon::now();
        
        for ($i = 0; $i < count($comments); $i++) {
            $feedbacks[] = [
                'user_id' => $userIds[$i % count($userIds)],
                'target_id' => null,
                'event_id' => null,
                'zone_id' => null,
                'materielle_id' => $materielId,
                'contenu' => $comments[$i],
                'response' => rand(0, 1) ? $this->getRandomSupplierResponse() : null,
                'note' => $ratings[$i],
                'type' => 'materielle',
                'status' => 'approved',
                'created_at' => $now->copy()->subDays(rand(1, 60)),
                'updated_at' => $now->copy()->subDays(rand(1, 60)),
            ];
        }
        
        return $feedbacks;
    }

    /**
     * Generate feedbacks for a supplier (target_id = user_id of supplier)
     */
    private function generateSupplierFeedbacks($supplierId, $comments, $ratings, $userIds)
    {
        $feedbacks = [];
        $now = Carbon::now();
        
        for ($i = 0; $i < count($comments); $i++) {
            $feedbacks[] = [
                'user_id' => $userIds[$i % count($userIds)],
                'target_id' => $supplierId,
                'event_id' => null,
                'zone_id' => null,
                'materielle_id' => null,
                'contenu' => $comments[$i],
                'response' => rand(0, 1) ? $this->getRandomSupplierResponse() : null,
                'note' => $ratings[$i],
                'type' => 'fournisseur',
                'status' => 'approved',
                'created_at' => $now->copy()->subDays(rand(1, 60)),
                'updated_at' => $now->copy()->subDays(rand(1, 60)),
            ];
        }
        
        return $feedbacks;
    }

    /**
     * Generate feedbacks for a center (target_id = user_id of center)
     */
    private function generateCenterFeedbacks($centerId, $comments, $ratings, $userIds)
    {
        $feedbacks = [];
        $now = Carbon::now();
        
        for ($i = 0; $i < count($comments); $i++) {
            $feedbacks[] = [
                'user_id' => $userIds[$i % count($userIds)],
                'target_id' => $centerId,
                'event_id' => null,
                'zone_id' => null,
                'materielle_id' => null,
                'contenu' => $comments[$i],
                'response' => rand(0, 1) ? 'Thank you for your feedback! We appreciate your review.' : null,
                'note' => $ratings[$i],
                'type' => 'centre',
                'status' => 'approved',
                'created_at' => $now->copy()->subDays(rand(1, 90)),
                'updated_at' => $now->copy()->subDays(rand(1, 90)),
            ];
        }
        
        return $feedbacks;
    }

    /**
     * Generate pending materiel feedbacks
     */
    private function generatePendingMaterielFeedbacks($materielId, $comments, $ratings, $userIds)
    {
        $feedbacks = [];
        $now = Carbon::now();
        
        for ($i = 0; $i < count($comments); $i++) {
            $feedbacks[] = [
                'user_id' => $userIds[$i % count($userIds)],
                'target_id' => null,
                'event_id' => null,
                'zone_id' => null,
                'materielle_id' => $materielId,
                'contenu' => $comments[$i],
                'response' => null,
                'note' => $ratings[$i],
                'type' => 'materielle',
                'status' => 'pending',
                'created_at' => $now->copy()->subDays(rand(1, 30)),
                'updated_at' => $now->copy()->subDays(rand(1, 30)),
            ];
        }
        
        return $feedbacks;
    }

    /**
     * Generate zone feedbacks
     */
    private function generateZoneFeedbacks($zoneId, $comments, $ratings, $userIds)
    {
        $feedbacks = [];
        $now = Carbon::now();
        
        for ($i = 0; $i < count($comments); $i++) {
            $feedbacks[] = [
                'user_id' => $userIds[$i % count($userIds)],
                'target_id' => null,
                'event_id' => null,
                'zone_id' => $zoneId,
                'materielle_id' => null,
                'contenu' => $comments[$i],
                'response' => null,
                'note' => $ratings[$i],
                'type' => 'zone',
                'status' => 'approved',
                'created_at' => $now->copy()->subDays(rand(1, 60)),
                'updated_at' => $now->copy()->subDays(rand(1, 60)),
            ];
        }
        
        return $feedbacks;
    }

    /**
     * Generate event feedbacks
     */
    private function generateEventFeedbacks($eventId, $comments, $ratings, $userIds)
    {
        $feedbacks = [];
        $now = Carbon::now();
        
        for ($i = 0; $i < count($comments); $i++) {
            $feedbacks[] = [
                'user_id' => $userIds[$i % count($userIds)],
                'target_id' => null,
                'event_id' => $eventId,
                'zone_id' => null,
                'materielle_id' => null,
                'contenu' => $comments[$i],
                'response' => null,
                'note' => $ratings[$i],
                'type' => 'event',
                'status' => 'approved',
                'created_at' => $now->copy()->subDays(rand(1, 60)),
                'updated_at' => $now->copy()->subDays(rand(1, 60)),
            ];
        }
        
        return $feedbacks;
    }

    /**
     * Get random materiel feedback text
     */
    private function getRandomMaterielFeedback()
    {
        $feedbacks = [
            'Excellent quality equipment!',
            'Very durable and well-maintained.',
            'Perfect for my camping trip.',
            'Great value for money.',
            'Would definitely rent again.',
            'The equipment was like new.',
            'Fast delivery and great service.',
            'Very comfortable and practical.',
            'Highly recommended for campers.',
            'Works perfectly as described.',
            'Lightweight and easy to carry.',
            'Sturdy construction, feels premium.',
            'Great for beginners and experienced campers.',
            'Exceeded my expectations!',
            'Will recommend to friends.',
        ];
        
        return $feedbacks[array_rand($feedbacks)];
    }

    /**
     * Get random supplier feedback text
     */
    private function getRandomSupplierFeedback()
    {
        $feedbacks = [
            'Great supplier, very reliable!',
            'Fast shipping and great communication.',
            'High quality equipment at fair prices.',
            'Very professional and helpful.',
            'Will definitely order from them again.',
            'Excellent customer service.',
            'The equipment was exactly as described.',
            'Very responsive to questions.',
            'Trustworthy and dependable supplier.',
            'Great experience overall.',
        ];
        
        return $feedbacks[array_rand($feedbacks)];
    }

    /**
     * Get random supplier response
     */
    private function getRandomSupplierResponse()
    {
        $responses = [
            'Thank you for your kind words!',
            'We appreciate your feedback!',
            'Glad you enjoyed the equipment!',
            'Thanks for choosing us!',
            'We look forward to serving you again!',
            'Your satisfaction is our priority!',
            'Thank you for being a valued customer!',
            'We appreciate your review!',
            'Happy camping!',
            'Thanks for the 5-star rating!',
        ];
        
        return $responses[array_rand($responses)];
    }
}