<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancellation_policy_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')
                  ->constrained('cancellation_policies')
                  ->onDelete('cascade');
            // If hours_remaining >= hours_before, this tier applies.
            // Evaluated: pick tier with highest hours_before that still satisfies the condition.
            $table->unsignedInteger('hours_before');
            $table->decimal('fee_percentage', 5, 2); // 0.00 – 100.00
            $table->string('label', 100)->nullable(); // e.g. "Free cancellation", "20% fee"
            $table->timestamps();

            $table->index(['policy_id', 'hours_before']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancellation_policy_tiers');
    }
};
