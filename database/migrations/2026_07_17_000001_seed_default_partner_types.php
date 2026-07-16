<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds default partner_types so admins can create Partners and assign them
 * to Programme steps immediately, without first needing a "manage types" UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [
            ['code' => 'centre',        'label' => 'Hébergement / Centre', 'icon' => 'Building2', 'requires_platform_account' => true],
            ['code' => 'guide',         'label' => 'Guide',                'icon' => 'Compass',   'requires_platform_account' => true],
            ['code' => 'fournisseur',   'label' => 'Fournisseur matériel', 'icon' => 'Package',   'requires_platform_account' => true],
            ['code' => 'transporteur',  'label' => 'Transporteur',         'icon' => 'Truck',      'requires_platform_account' => false],
            ['code' => 'restaurant',    'label' => 'Restaurant',           'icon' => 'UtensilsCrossed', 'requires_platform_account' => false],
        ];

        foreach ($rows as $row) {
            DB::table('partner_types')->where('code', $row['code'])->exists()
                ?: DB::table('partner_types')->insert($row + ['created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('partner_types')->whereIn('code', [
            'centre', 'guide', 'fournisseur', 'transporteur', 'restaurant',
        ])->delete();
    }
};
