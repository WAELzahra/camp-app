<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_reservation_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_reservation_id')->constrained('programme_reservations')->onDelete('cascade');
            $table->foreignId('partner_id')->constrained('partners')->onDelete('restrict');
            $table->foreignId('programme_step_partner_id')->nullable()->constrained('programme_step_partners')->onDelete('set null');
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->decimal('net_amount', 10, 2);
            $table->boolean('partner_credited')->default(false);
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index('programme_reservation_id');
            $table->index('partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_reservation_shares');
    }
};
