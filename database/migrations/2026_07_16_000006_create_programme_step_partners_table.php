<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_step_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_step_id')->constrained('programme_steps')->onDelete('cascade');
            $table->foreignId('partner_id')->constrained('partners')->onDelete('restrict');
            $table->decimal('price', 10, 2)->default(0);
            // null = inherits partners.default_commission_rate at share-creation time
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->timestamps();

            $table->index('programme_step_id');
            $table->index('partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_step_partners');
    }
};
