<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_transactions')) {
            Schema::create('payment_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('reservation_id')->nullable();
                $table->string('reservation_type', 30)->nullable(); // centre|event|materielle
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('gateway', ['flouci', 'clictopay', 'bank_transfer', 'reservation_credit']);
                $table->decimal('amount', 10, 2);
                $table->string('currency', 3)->default('TND');
                $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending')->index();
                $table->string('gateway_reference')->nullable();
                $table->json('gateway_response')->nullable();
                $table->enum('payment_type', ['credit_load', 'reservation', 'refund', 'withdrawal'])->index();
                // Refunds link back to the original transaction (originals are never modified).
                $table->unsignedBigInteger('original_transaction_id')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->index('gateway');
            });
        }

        if (!Schema::hasTable('payment_gateways')) {
            Schema::create('payment_gateways', function (Blueprint $table) {
                $table->id();
                $table->string('key', 30)->unique();  // flouci | clictopay | d17 | bank_transfer
                $table->string('label');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            DB::table('payment_gateways')->insert([
                ['key' => 'flouci',        'label' => 'Flouci',          'is_active' => true,  'created_at' => now(), 'updated_at' => now()],
                ['key' => 'clictopay',     'label' => 'ClicToPay',       'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'bank_transfer', 'label' => 'Virement bancaire', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
        Schema::dropIfExists('payment_transactions');
    }
};
