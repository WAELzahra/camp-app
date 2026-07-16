<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_type_id')->constrained('partner_types')->onDelete('restrict');
            // Nullable: admin-managed partner without a platform account (e.g. transporteur, restaurant at launch)
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('name', 255);
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->decimal('default_commission_rate', 5, 2)->default(10);
            $table->enum('status', ['active', 'pending', 'suspended'])->default('active');
            $table->timestamps();

            $table->index('user_id');
            $table->index(['partner_type_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
