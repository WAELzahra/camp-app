<?php
// database/seeders/ReservationServiceItemSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReservationServiceItemSeeder extends Seeder
{
    public function run()
    {
        // First, get the actual reservation IDs that were created
        $reservationIds = DB::table('reservations_centres')
            ->orderBy('id')
            ->pluck('id')
            ->toArray();
        
        // If no reservations exist, we can't create service items
        if (empty($reservationIds)) {
            $this->command->info('No reservations found. Skipping ReservationServiceItemSeeder.');
            return;
        }
        
        // Map reservation IDs to indices (reservations are seeded in order)
        // Assuming reservations are seeded in this order:
        // 1. Past reservation (completed) - User ID 2
        // 2. Current reservation (pending) - User ID 2
        // 3. Future reservation (approved) - User ID 2
        // 4. Rejected reservation - User ID 2
        // 5. Canceled reservation - User ID 2
        // 6. Another user's reservation (pending) - User ID 3
        // 7. Another user's reservation (approved) - User ID 4
        
        $serviceItems = [
            // Reservation 1 (Basic Camping + Breakfast) - Past completed reservation
            [
                'reservation_id' => $reservationIds[0] ?? 1,
                'profile_center_service_id' => 1, // Basic Camping
                'service_name' => 'Basic Camping',
                'service_description' => 'Sleep with your own tent - access to basic facilities (toilets, drinking water, parking)',
                'unit_price' => 15.00,
                'unit' => 'person/night',
                'quantity' => 3,
                'subtotal' => 135.00, // 3 people × 3 nights × 15.00 = 135.00
                'service_date_debut' => Carbon::now()->subDays(10)->format('Y-m-d'),
                'service_date_fin' => Carbon::now()->subDays(7)->format('Y-m-d'),
                'notes' => 'Bringing our own tent',
                'status' => 'approved',
                'created_at' => Carbon::now()->subDays(12),
                'updated_at' => Carbon::now()->subDays(12),
            ],
            [
                'reservation_id' => $reservationIds[0] ?? 1,
                'profile_center_service_id' => 2, // Breakfast
                'service_name' => 'Breakfast',
                'service_description' => 'Continental breakfast with coffee, bread, jam, and fruits',
                'unit_price' => 20.00,
                'unit' => 'person',
                'quantity' => 3,
                'subtotal' => 60.00, // 3 people × 20.00
                'service_date_debut' => Carbon::now()->subDays(10)->format('Y-m-d'),
                'service_date_fin' => Carbon::now()->subDays(7)->format('Y-m-d'),
                'notes' => 'Vegetarian options needed',
                'status' => 'approved',
                'created_at' => Carbon::now()->subDays(12),
                'updated_at' => Carbon::now()->subDays(12),
            ],
            
            // Reservation 2 (Cabin Rental) - Current pending reservation
            [
                'reservation_id' => $reservationIds[1] ?? 2,
                'profile_center_service_id' => 3, // Cabin Rental
                'service_name' => 'Cabin Rental',
                'service_description' => 'Cozy cabin accommodation with basic furniture, electricity, and shared facilities',
                'unit_price' => 80.00,
                'unit' => 'night',
                'quantity' => 3, // 3 nights
                'subtotal' => 240.00,
                'service_date_debut' => Carbon::now()->addDays(5)->format('Y-m-d'),
                'service_date_fin' => Carbon::now()->addDays(8)->format('Y-m-d'),
                'notes' => 'Need extra blankets',
                'status' => 'pending',
                'created_at' => Carbon::now()->subDays(2),
                'updated_at' => Carbon::now()->subDays(2),
            ],
            
            // Reservation 3 (Multiple services) - Future approved reservation
            [
                'reservation_id' => $reservationIds[2] ?? 3,
                'profile_center_service_id' => 1, // Basic Camping
                'service_name' => 'Basic Camping',
                'service_description' => 'Sleep with your own tent - access to basic facilities',
                'unit_price' => 15.00,
                'unit' => 'person/night',
                'quantity' => 4,
                'subtotal' => 180.00, // 4 people × 3 nights × 15.00 = 180.00
                'service_date_debut' => Carbon::now()->addDays(15)->format('Y-m-d'),
                'service_date_fin' => Carbon::now()->addDays(18)->format('Y-m-d'),
                'notes' => 'Two tents needed',
                'status' => 'approved',
                'created_at' => Carbon::now()->subDays(1),
                'updated_at' => Carbon::now()->subDays(1),
            ],
            [
                'reservation_id' => $reservationIds[2] ?? 3,
                'profile_center_service_id' => 2, // Breakfast
                'service_name' => 'Breakfast',
                'service_description' => 'Continental breakfast',
                'unit_price' => 20.00,
                'unit' => 'person',
                'quantity' => 4,
                'subtotal' => 80.00, // 4 people × 20.00
                'service_date_debut' => Carbon::now()->addDays(15)->format('Y-m-d'),
                'service_date_fin' => Carbon::now()->addDays(18)->format('Y-m-d'),
                'notes' => null,
                'status' => 'approved',
                'created_at' => Carbon::now()->subDays(1),
                'updated_at' => Carbon::now()->subDays(1),
            ],
            [
                'reservation_id' => $reservationIds[2] ?? 3,
                'profile_center_service_id' => 3, // Cabin Rental
                'service_name' => 'Cabin Rental',
                'service_description' => 'Cozy cabin accommodation',
                'unit_price' => 80.00,
                'unit' => 'night',
                'quantity' => 1,
                'subtotal' => 80.00, // 1 cabin for 1 night
                'service_date_debut' => Carbon::now()->addDays(15)->format('Y-m-d'),
                'service_date_fin' => Carbon::now()->addDays(16)->format('Y-m-d'),
                'notes' => 'For elderly family members',
                'status' => 'approved',
                'created_at' => Carbon::now()->subDays(1),
                'updated_at' => Carbon::now()->subDays(1),
            ],
            
            // Reservation 4 (Rejected)
            [
                'reservation_id' => $reservationIds[3] ?? 4,
                'profile_center_service_id' => 1, // Basic Camping
                'service_name' => 'Basic Camping',
                'service_description' => 'Sleep with your own tent',
                'unit_price' => 15.00,
                'unit' => 'person/night',
                'quantity' => 5,
                'subtotal' => 225.00, // 5 people × 3 nights × 15.00 = 225.00
                'service_date_debut' => Carbon::now()->addDays(25)->format('Y-m-d'),
                'service_date_fin' => Carbon::now()->addDays(28)->format('Y-m-d'),
                'notes' => 'Large group booking',
                'status' => 'rejected',
                'created_at' => Carbon::now()->subDays(3),
                'updated_at' => Carbon::now()->subDays(2),
            ],
            
            // Reservation 5 (Canceled)
            [
                'reservation_id' => $reservationIds[4] ?? 5,
                'profile_center_service_id' => 3, // Cabin Rental
                'service_name' => 'Cabin Rental',
                'service_description' => 'Cozy cabin accommodation',
                'unit_price' => 80.00,
                'unit' => 'night',
                'quantity' => 3,
                'subtotal' => 240.00,
                'service_date_debut' => Carbon::now()->addDays(30)->format('Y-m-d'),
                'service_date_fin' => Carbon::now()->addDays(33)->format('Y-m-d'),
                'notes' => 'Canceled by user',
                'status' => 'canceled',
                'created_at' => Carbon::now()->subDays(5),
                'updated_at' => Carbon::now()->subDays(4),
            ],
            
            // Reservation 6 (Another user - pending)
            [
                'reservation_id' => $reservationIds[5] ?? 6,
                'profile_center_service_id' => 3, // Cabin Rental
                'service_name' => 'Cabin Rental',
                'service_description' => 'Cozy cabin accommodation',
                'unit_price' => 80.00,
                'unit' => 'night',
                'quantity' => 3,
                'subtotal' => 240.00,
                'service_date_debut' => Carbon::now()->addDays(3)->format('Y-m-d'),
                'service_date_fin' => Carbon::now()->addDays(6)->format('Y-m-d'),
                'notes' => 'Anniversary special request',
                'status' => 'pending',
                'created_at' => Carbon::now()->subDays(1),
                'updated_at' => Carbon::now()->subDays(1),
            ],
            [
                'reservation_id' => $reservationIds[5] ?? 6,
                'profile_center_service_id' => 2, // Breakfast
                'service_name' => 'Breakfast',
                'service_description' => 'Continental breakfast',
                'unit_price' => 20.00,
                'unit' => 'person',
                'quantity' => 2,
                'subtotal' => 40.00,
                'service_date_debut' => Carbon::now()->addDays(3)->format('Y-m-d'),
                'service_date_fin' => Carbon::now()->addDays(6)->format('Y-m-d'),
                'notes' => null,
                'status' => 'pending',
                'created_at' => Carbon::now()->subDays(1),
                'updated_at' => Carbon::now()->subDays(1),
            ],
            
            // Reservation 7 (Another user - approved)
            [
                'reservation_id' => $reservationIds[6] ?? 7,
                'profile_center_service_id' => 1, // Basic Camping
                'service_name' => 'Basic Camping',
                'service_description' => 'Sleep with your own tent',
                'unit_price' => 15.00,
                'unit' => 'person/night',
                'quantity' => 1,
                'subtotal' => 30.00, // 1 person × 2 nights × 15.00 = 30.00
                'service_date_debut' => Carbon::now()->addDays(10)->format('Y-m-d'),
                'service_date_fin' => Carbon::now()->addDays(12)->format('Y-m-d'),
                'notes' => 'Solo backpacker',
                'status' => 'approved',
                'created_at' => Carbon::now()->subDays(3),
                'updated_at' => Carbon::now()->subDays(3),
            ],
        ];

        DB::table('reservation_service_items')->insert($serviceItems);
        
        $this->command->info('Reservation service items seeded successfully.');
    }
}