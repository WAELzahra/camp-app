<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('commission_rate', 5, 2)->comment('Override rate in percent (e.g. 3.5 = 3.5%)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('custom_commission_rule_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('custom_commission_rules')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Prevent duplicate assignment
            $table->unique(['rule_id', 'user_id']);
            // Index for the hot query: SELECT * WHERE user_id = ? AND is_active = true
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_commission_rule_users');
        Schema::dropIfExists('custom_commission_rules');
    }
};
