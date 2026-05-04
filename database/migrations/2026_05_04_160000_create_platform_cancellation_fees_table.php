<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_cancellation_fees', function (Blueprint $table) {
            $table->id();
            $table->enum('actor_type', ['camper', 'centre', 'group']);
            $table->decimal('fee_percentage', 5, 2)->default(0.00);
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique('actor_type');
        });

        // Seed default rows (one per actor type, inactive at 0%)
        DB::table('platform_cancellation_fees')->insert([
            ['actor_type' => 'camper',  'fee_percentage' => 0.00, 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['actor_type' => 'centre',  'fee_percentage' => 0.00, 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['actor_type' => 'group',   'fee_percentage' => 0.00, 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_cancellation_fees');
    }
};
