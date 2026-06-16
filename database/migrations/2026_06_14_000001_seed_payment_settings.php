<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the platform_settings table with payment-related configuration rows:
 *  - payment_link_flouci       : external Flouci payment URL (admin-editable)
 *  - manual_payment_enabled    : master switch for manual payment workflow
 *  - deposit_min_percentage    : minimum deposit % providers may require
 *  - deposit_max_percentage    : maximum deposit % providers may require
 *  - deposit_min_total         : booking total (TND) below which deposits are disallowed
 */
return new class extends Migration
{
    public function up(): void
    {
        $now  = now();
        $rows = [
            [
                'key'         => 'payment_link_flouci',
                'value'       => '',
                'label'       => 'Online Payment Link',
                'description' => 'External payment link shown to campers for manual payment.',
                'type'        => 'string',
                'group'       => 'payment',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'manual_payment_enabled',
                'value'       => '0',
                'label'       => 'Manual Payment Enabled',
                'description' => 'Master switch — disable to hide the online payment option from all booking flows.',
                'type'        => 'boolean',
                'group'       => 'payment',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'deposit_min_percentage',
                'value'       => '20',
                'label'       => 'Minimum Deposit Percentage',
                'description' => 'Providers cannot set a deposit lower than this percentage.',
                'type'        => 'integer',
                'group'       => 'payment',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'deposit_max_percentage',
                'value'       => '80',
                'label'       => 'Maximum Deposit Percentage',
                'description' => 'Providers cannot set a deposit higher than this percentage.',
                'type'        => 'integer',
                'group'       => 'payment',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'deposit_min_total',
                'value'       => '150',
                'label'       => 'Minimum Total for Deposits (TND)',
                'description' => 'Bookings below this total must be paid in full — no deposits allowed.',
                'type'        => 'integer',
                'group'       => 'payment',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ];

        foreach ($rows as $row) {
            DB::table('platform_settings')
                ->where('key', $row['key'])
                ->exists()
                    ?: DB::table('platform_settings')->insert($row);
        }
    }

    public function down(): void
    {
        DB::table('platform_settings')->whereIn('key', [
            'payment_link_flouci',
            'manual_payment_enabled',
            'deposit_min_percentage',
            'deposit_max_percentage',
            'deposit_min_total',
        ])->delete();
    }
};
