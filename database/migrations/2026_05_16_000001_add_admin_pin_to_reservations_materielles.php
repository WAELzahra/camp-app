<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations_materielles', function (Blueprint $table) {
            $table->string('admin_pin_code')->nullable()->after('pin_used_at');
            $table->timestamp('admin_pin_used_at')->nullable()->after('admin_pin_code');
        });
    }

    public function down(): void
    {
        Schema::table('reservations_materielles', function (Blueprint $table) {
            $table->dropColumn(['admin_pin_code', 'admin_pin_used_at']);
        });
    }
};
