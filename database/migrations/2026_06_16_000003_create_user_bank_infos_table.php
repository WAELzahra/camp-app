<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user's own payout bank details — where the platform sends their earnings when
 * a withdrawal is processed. One row per user, available to every role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_bank_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('bank_name')->nullable();
            $table->string('account_holder')->nullable();
            $table->string('iban', 60)->nullable();
            $table->string('flouci_phone', 30)->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_bank_infos');
    }
};
