<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations_centres', function (Blueprint $table) {
            $table->timestamp('rules_accepted_at')->nullable();
            $table->string('rules_accepted_ip', 45)->nullable();
            $table->string('rules_accepted_user_agent', 500)->nullable();
            // Snapshot of the centre's rules (+ house_rules_notes) exactly as shown to the
            // camper at booking time — protects both sides if the centre edits its rules later.
            $table->json('rules_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations_centres', function (Blueprint $table) {
            $table->dropColumn([
                'rules_accepted_at',
                'rules_accepted_ip',
                'rules_accepted_user_agent',
                'rules_snapshot',
            ]);
        });
    }
};
