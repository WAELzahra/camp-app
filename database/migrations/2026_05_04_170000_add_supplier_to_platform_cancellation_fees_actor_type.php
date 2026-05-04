<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE platform_cancellation_fees MODIFY actor_type ENUM('camper','centre','group','supplier') NOT NULL");

        DB::table('platform_cancellation_fees')->insertOrIgnore([
            ['actor_type' => 'supplier', 'fee_percentage' => 0.00, 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('platform_cancellation_fees')->where('actor_type', 'supplier')->delete();
        DB::statement("ALTER TABLE platform_cancellation_fees MODIFY actor_type ENUM('camper','centre','group') NOT NULL");
    }
};
