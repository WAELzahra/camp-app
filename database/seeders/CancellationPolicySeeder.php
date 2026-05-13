<?php

namespace Database\Seeders;

use App\Models\CancellationPolicy;
use Illuminate\Database\Seeder;

class CancellationPolicySeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'type' => 'centre',
                'name' => 'Default Centre Policy',
                'tiers' => [
                    ['hours_before' => 168, 'fee_percentage' => 0,  'label' => 'Free cancellation (7+ days before)'],
                    ['hours_before' => 24,  'fee_percentage' => 20, 'label' => '20% fee (1–7 days before)'],
                    ['hours_before' => 0,   'fee_percentage' => 50, 'label' => '50% fee (within 24 hours)'],
                ],
            ],
            [
                'type' => 'event',
                'name' => 'Default Event Policy',
                'tiers' => [
                    ['hours_before' => 72, 'fee_percentage' => 0,  'label' => 'Free cancellation (3+ days before)'],
                    ['hours_before' => 24, 'fee_percentage' => 15, 'label' => '15% fee (1–3 days before)'],
                    ['hours_before' => 0,  'fee_percentage' => 50, 'label' => '50% fee (within 24 hours)'],
                ],
            ],
            [
                'type' => 'materiel',
                'name' => 'Default Equipment Policy',
                'tiers' => [
                    ['hours_before' => 48, 'fee_percentage' => 0,  'label' => 'Free cancellation (2+ days before)'],
                    ['hours_before' => 0,  'fee_percentage' => 25, 'label' => '25% fee (within 48 hours)'],
                ],
            ],
        ];

        foreach ($defaults as $data) {
            // Skip if a global default already exists for this type
            if (CancellationPolicy::where('type', $data['type'])->whereNull('centre_id')->exists()) {
                continue;
            }

            $policy = CancellationPolicy::create([
                'type'      => $data['type'],
                'name'      => $data['name'],
                'centre_id' => null,
                'is_active' => true,
            ]);

            foreach ($data['tiers'] as $tier) {
                $policy->tiers()->create($tier);
            }
        }
    }
}
