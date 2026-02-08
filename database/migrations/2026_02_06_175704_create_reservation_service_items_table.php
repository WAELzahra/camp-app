<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create the pivot table for reservation services
        Schema::create('reservation_service_items', function (Blueprint $table) {
            $table->id();
            
            // Link to reservation
            $table->unsignedBigInteger('reservation_id');
            $table->foreign('reservation_id')
                  ->references('id')
                  ->on('reservations_centres')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
            
            // Link to the specific center service
            $table->unsignedBigInteger('profile_center_service_id');
            $table->foreign('profile_center_service_id')
                  ->references('id')
                  ->on('profile_center_services')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
            
            // Service details at time of booking (snapshot in case prices change)
            $table->string('service_name')->nullable();
            $table->text('service_description')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->string('unit', 50);
            $table->integer('quantity')->default(1);
            $table->decimal('subtotal', 10, 2);
            
            // Dates for this specific service (may differ from reservation dates)
            $table->date('service_date_debut')->nullable();
            $table->date('service_date_fin')->nullable();
            
            // Additional notes for this specific service
            $table->text('notes')->nullable();
            
            // Status for this specific service item
            $table->enum('status', ['pending', 'approved', 'rejected', 'canceled'])
                  ->default('pending');
            
            $table->timestamps();
            
            // Composite unique index with SHORT name
            $table->unique(['reservation_id', 'profile_center_service_id'], 'res_serv_unique');
            
            // Indexes for performance with SHORT names
            $table->index(['reservation_id', 'status'], 'res_status_idx');
            $table->index('profile_center_service_id', 'service_id_idx');
        });
        
        // Add total price and service count to reservations_centres table
        Schema::table('reservations_centres', function (Blueprint $table) {
            // Add total price field
            $table->decimal('total_price', 10, 2)->nullable()->after('payments_id');
            
            // Add service count (optional, can be calculated)
            $table->integer('service_count')->default(0)->after('total_price');
            
            // Add index with SHORT name
            $table->index(['centre_id', 'status', 'date_debut'], 'centre_status_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations_centres', function (Blueprint $table) {
            $table->dropIndex('centre_status_date_idx');
            $table->dropColumn('service_count');
            $table->dropColumn('total_price');
        });
        
        Schema::dropIfExists('reservation_service_items');
    }
};