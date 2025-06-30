<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Payments;

class PaymentSeeder extends Seeder
{
    public function run()
    {
        Payments::create([
            'event_reservation_id' => 1,  // ID de la réservation créée plus haut
            'amount' => 400,              // 2 places * 200 prix de l’event
            'payment_method' => 'flouci',
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }
}
