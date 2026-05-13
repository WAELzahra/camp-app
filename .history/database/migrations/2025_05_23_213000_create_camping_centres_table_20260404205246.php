<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ================================
        // 1. TABLE reservation_service_items
        // ================================
        Schema::create('reservation_service_items', function (Blueprint $table) {
            $table->id();

            // FK → reservations_centres
            $table->foreignId('reservation_id')
                  ->constrained('reservations_centres')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();

            // FK → profile_center_services
            $table->foreignId('profile_center_service_id')
                  ->constrained('profile_center_services')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();

            // Snapshot service
            $table->string('service_name')->nullable();
            $table->text('service_description')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->string('unit', 50);
            $table->integer('quantity')->default(1);
            $table->decimal('subtotal', 10, 2);

            // Dates service
            $table->date('service_date_debut')->nullable();
            $table->date('service_date_fin')->nullable();

            $table->text('notes')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'canceled'])
                  ->default('pending');

            $table->timestamps();

            // Index
            $table->unique(['reservation_id', 'profile_center_service_id'], 'res_serv_unique');
            $table->index(['reservation_id', 'status'], 'res_status_idx');
            $table->index('profile_center_service_id', 'service_id_idx');
        });

        // ================================
        // 2. UPDATE reservations_centres
        // ================================
        Schema::table('reservations_centres', function (Blueprint $table) {

            $table->decimal('total_price', 10, 2)->nullable()->after('payments_id');
            $table->integer('service_count')->default(0)->after('total_price');

            // Index optimisé
            $table->index(['centre_id', 'status', 'date_debut'], 'centre_status_date_idx');
        });
    }

    public function down(): void
    {
        // ================================
        // IMPORTANT : ordre critique ⚠️
        // ================================

        Schema::table('reservations_centres', function (Blueprint $table) {

            // ❌ 1. DROP FOREIGN KEY AVANT INDEX
            if (Schema::hasColumn('reservations_centres', 'centre_id')) {
                try {
                    $table->dropForeign(['centre_id']);
                } catch (\Exception $e) {
                    // ignore si déjà supprimé
                }
            }

            // ❌ 2. DROP INDEX
            try {
                $table->dropIndex('centre_status_date_idx');
            } catch (\Exception $e) {
                // ignore si inexistant
            }

            // ❌ 3. DROP COLUMNS
            if (Schema::hasColumn('reservations_centres', 'service_count')) {
                $table->dropColumn('service_count');
            }

            if (Schema::hasColumn('reservations_centres', 'total_price')) {
                $table->dropColumn('total_price');
            }

            // ✅ 4. RECREATE FK (optionnel mais safe)
            try {
                $table->foreign('centre_id')
                      ->references('id')
                      ->on('camping_centres')
                      ->cascadeOnDelete();
            } catch (\Exception $e) {
                // ignore si déjà existe
            }
        });

        // ❌ 5. DROP TABLE EN DERNIER
        Schema::dropIfExists('reservation_service_items');
    }
};