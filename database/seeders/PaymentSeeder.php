<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Payments;
use App\Models\RefundRequest;
use App\Models\Reservations_events;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data (disable FK checks for truncation)
        \DB::statement('SET FOREIGN_KEY_CHECKS=0');
        RefundRequest::truncate();
        Payments::truncate();
        \DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $paymentsData = [
            // event_id, user_id, reservation_id, montant, status, description, konnect_payment_id, days_ago
            [1, 3,  1, 250.00, 'paid',             'Réservation Camping Saharien - Douz',         'KNX-20250101-001', 30],
            [1, 9,  2, 250.00, 'paid',             'Réservation Camping Saharien - Douz',         'KNX-20250102-002', 28],
            [2, 3,  4, 180.00, 'paid',             'Réservation Trek Montagne Kasserine',         'KNX-20250103-003', 25],
            [2, 9,  5, 180.00, 'pending',          'Réservation Trek Montagne Kasserine',         null,               20],
            [3, 8,  8, 320.00, 'paid',             'Réservation Aventure Ain Draham',             'KNX-20250105-005', 18],
            [3, 10, 9, 320.00, 'failed',           'Réservation Aventure Ain Draham',             null,               15],
            [4, 3,  4, 150.00, 'paid',             'Réservation Randonnée Zaghouan',              'KNX-20250107-007', 12],
            [4, 9,  5, 150.00, 'refunded_total',   'Réservation Randonnée Zaghouan - Annulée',   'KNX-20250108-008', 10],
            [5, 8,  8, 400.00, 'paid',             'Réservation Camp Plage Bizerte',              'KNX-20250109-009',  8],
            [5, 10, 9, 400.00, 'refunded_partial', 'Réservation Camp Plage Bizerte - Partiel',   'KNX-20250110-010',  6],
            [6, 3,  1, 290.00, 'paid',             'Réservation Expedition Tataouine',            'KNX-20250111-011',  4],
            [6, 9,  2, 290.00, 'pending',          'Réservation Expedition Tataouine',            null,                2],
        ];

        $createdPayments = [];
        foreach ($paymentsData as $data) {
            [$eventId, $userId, $reservationId, $montant, $status, $description, $konnectId, $daysAgo] = $data;

            $commission  = round($montant * 0.10, 2);
            $netRevenue  = round($montant - $commission, 2);

            $payment = Payments::create([
                'user_id'            => $userId,
                'event_id'           => $eventId,
                'montant'            => $montant,
                'description'        => $description,
                'status'             => $status,
                'commission'         => $commission,
                'net_revenue'        => $netRevenue,
                'konnect_payment_id' => $konnectId,
                'konnect_session_id' => $konnectId ? 'SES-' . substr($konnectId, -6) : null,
                'created_at'         => now()->subDays($daysAgo),
                'updated_at'         => now()->subDays($daysAgo),
            ]);

            // Link reservation to this payment
            Reservations_events::where('id', $reservationId)->update(['payment_id' => $payment->id]);

            $createdPayments[] = $payment;
        }

        // RefundRequests: link to specific payments
        // Payment index 7 => refunded_total => accepté
        // Payment index 9 => refunded_partial => accepté
        // Two more en_attente for paid payments (index 0 and 2)
        $refunds = [
            ['payment_idx' => 0, 'reservation_id' => 1,  'montant' => 125.00, 'status' => 'en_attente'],
            ['payment_idx' => 2, 'reservation_id' => 4,  'montant' => 180.00, 'status' => 'refusé'],
            ['payment_idx' => 7, 'reservation_id' => 5,  'montant' => 150.00, 'status' => 'accepté'],
            ['payment_idx' => 9, 'reservation_id' => 9,  'montant' => 200.00, 'status' => 'accepté'],
        ];

        foreach ($refunds as $r) {
            RefundRequest::create([
                'payment_id'          => $createdPayments[$r['payment_idx']]->id,
                'reservation_event_id'=> $r['reservation_id'],
                'montant_rembourse'   => $r['montant'],
                'status'              => $r['status'],
                'created_at'          => now()->subDays(rand(1, 5)),
                'updated_at'          => now(),
            ]);
        }

        $this->command->info('12 payments and 4 refund requests created.');
    }
}
