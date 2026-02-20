<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeedbackSeeder extends Seeder
{
    public function run()
    {
        $feedbacks = [
            // ==================== FEEDBACK FOR CENTERS ====================
            // Feedback for Center ID 1 from Camper ID 2
            [
                'user_id' => 2, // Camper User (deadxshot660@gmail.com)
                'target_id' => 1, // Center Manager (njkhouja@gmail.com)
                'event_id' => null,
                'zone_id' => null,
                'contenu' => 'Excellent camping center! Very clean facilities and friendly staff. The location is beautiful and well-maintained.',
                'response' => null,
                'note' => 5,
                'type' => 'centre',
                'status' => 'approved',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ],
            [
                'user_id' => 3, // Sarah Camper
                'target_id' => 1, // Center Manager
                'event_id' => null,
                'zone_id' => null,
                'contenu' => 'Great camping experience! The tents were comfortable and the amenities were top-notch.',
                'response' => 'Thank you for your kind words! We hope to see you again soon.',
                'note' => 5,
                'type' => 'centre',
                'status' => 'approved',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(9),
            ],
            [
                'user_id' => 4, // Mike Smith
                'target_id' => 1, // Center Manager
                'event_id' => null,
                'zone_id' => null,
                'contenu' => 'Nice location but the facilities need some maintenance. The bathrooms could be cleaner.',
                'response' => 'We apologize for the inconvenience. We are working on improving our facilities. Thank you for your feedback.',
                'note' => 3,
                'type' => 'centre',
                'status' => 'approved',
                'created_at' => now()->subDays(15),
                'updated_at' => now()->subDays(14),
            ],
            
            // ==================== FEEDBACK FOR SUPPLIERS (fournisseurs) ====================
            // Supplier/Guide user IDs (assuming role_id = 4 or 5)
            // Let's create a supplier user first (you may need to add this to UserSeeder)
            [
                'user_id' => 2, // Camper User
                'target_id' => 3, // Sarah is actually a supplier (role_id=4 in your seeder)
                'event_id' => null,
                'zone_id' => null,
                'contenu' => 'Excellent quality camping equipment! The tent was sturdy and the sleeping bags were warm.',
                'response' => 'Thank you for your purchase! We take pride in our quality equipment.',
                'note' => 5,
                'type' => 'fournisseur',
                'status' => 'approved',
                'created_at' => now()->subDays(7),
                'updated_at' => now()->subDays(6),
            ],
            [
                'user_id' => 4, // Mike Smith
                'target_id' => 3, // Sarah (supplier)
                'event_id' => null,
                'zone_id' => null,
                'contenu' => 'Good quality gear but delivery was a bit slow. Took 5 days instead of the promised 2.',
                'response' => 'We apologize for the delay. We have improved our shipping process.',
                'note' => 4,
                'type' => 'fournisseur',
                'status' => 'approved',
                'created_at' => now()->subDays(20),
                'updated_at' => now()->subDays(19),
            ],
            
            // Feedback for Mike Smith (supplier) from other users
            [
                'user_id' => 2, // Camper User
                'target_id' => 4, // Mike Smith (supplier)
                'event_id' => null,
                'zone_id' => null,
                'contenu' => 'Great camping supplies! The portable stove works perfectly.',
                'response' => null,
                'note' => 5,
                'type' => 'fournisseur',
                'status' => 'pending', // Pending approval
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
            
            // ==================== FEEDBACK WITH RESPONSES ====================
            [
                'user_id' => 3, // Sarah
                'target_id' => 4, // Mike Smith
                'event_id' => null,
                'zone_id' => null,
                'contenu' => 'The hiking boots I bought are very comfortable. Perfect for long trails!',
                'response' => 'We\'re glad you love them! They\'re our best-selling model.',
                'note' => 5,
                'type' => 'fournisseur',
                'status' => 'approved',
                'created_at' => now()->subDays(12),
                'updated_at' => now()->subDays(11),
            ],
            
            // ==================== REJECTED FEEDBACK ====================
            [
                'user_id' => 4, // Mike
                'target_id' => 1, // Center
                'event_id' => null,
                'zone_id' => null,
                'contenu' => 'This feedback is inappropriate and contains offensive language.',
                'response' => null,
                'note' => 1,
                'type' => 'centre',
                'status' => 'rejected',
                'created_at' => now()->subDays(25),
                'updated_at' => now()->subDays(24),
            ],
            

            

        ];

        DB::table('feedbacks')->insert($feedbacks);
        
        // Add more feedback for centers
        $centerFeedbacks = [];
        for ($i = 1; $i <= 10; $i++) {
            $centerFeedbacks[] = [
                'user_id' => rand(2, 4), // Random user
                'target_id' => 1, // Center ID 1
                'event_id' => null,
                'zone_id' => null,
                'contenu' => $this->getRandomCenterFeedback(),
                'response' => rand(0, 1) ? 'Thank you for your feedback! We appreciate your review.' : null,
                'note' => rand(3, 5),
                'type' => 'centre',
                'status' => 'approved',
                'created_at' => now()->subDays(rand(1, 60)),
                'updated_at' => now()->subDays(rand(1, 60)),
            ];
        }
        
        DB::table('feedbacks')->insert($centerFeedbacks);
        
        // Add more feedback for suppliers
        $supplierFeedbacks = [];
        $supplierIds = [3, 4]; // Sarah and Mike as suppliers
        for ($i = 1; $i <= 15; $i++) {
            $supplierFeedbacks[] = [
                'user_id' => rand(2, 4), // Random user
                'target_id' => $supplierIds[array_rand($supplierIds)],
                'event_id' => null,
                'zone_id' => null,
                'contenu' => $this->getRandomSupplierFeedback(),
                'response' => rand(0, 1) ? 'Thanks for your review! We value your business.' : null,
                'note' => rand(3, 5),
                'type' => 'fournisseur',
                'status' => 'approved',
                'created_at' => now()->subDays(rand(1, 60)),
                'updated_at' => now()->subDays(rand(1, 60)),
            ];
        }
        
        DB::table('feedbacks')->insert($supplierFeedbacks);
    }
    
    private function getRandomCenterFeedback()
    {
        $feedbacks = [
            'Great location with amazing views!',
            'Very clean facilities and friendly staff.',
            'Perfect for a family weekend getaway.',
            'The campsites are well-maintained and spacious.',
            'Excellent amenities including hot showers and clean toilets.',
            'Beautiful natural surroundings. Will definitely come back!',
            'Good value for money. The kids loved it here.',
            'Peaceful and quiet, exactly what we needed.',
            'The staff went above and beyond to help us.',
            'Well-organized camping center with everything you need.',
            'The fire pits and picnic areas are perfect for groups.',
            'Great hiking trails nearby. Very convenient location.',
            'Clean, safe, and family-friendly environment.',
            'The reception was very helpful with recommendations.',
            'Spacious sites with good privacy between campers.',
        ];
        
        return $feedbacks[array_rand($feedbacks)];
    }
    
    private function getRandomSupplierFeedback()
    {
        $feedbacks = [
            'High quality camping gear at reasonable prices.',
            'Fast shipping and great customer service.',
            'The tent I bought is excellent quality.',
            'Very responsive to questions and concerns.',
            'Products exactly as described. Would recommend!',
            'Good selection of camping equipment.',
            'Fair prices and good quality items.',
            'The sleeping bags are warm and comfortable.',
            'Great portable stove, works perfectly.',
            'Excellent customer support when I had questions.',
            'The hiking boots are very comfortable.',
            'Durable equipment that lasts.',
            'Quick delivery and well-packaged items.',
            'Good communication throughout the process.',
            'Will definitely order from them again.',
        ];
        
        return $feedbacks[array_rand($feedbacks)];
    }
}