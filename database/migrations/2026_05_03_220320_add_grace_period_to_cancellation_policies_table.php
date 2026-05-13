<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cancellation_policies', function (Blueprint $table) {
            // Reservations cancelled within this many hours of *creation* get a full refund,
            // regardless of how close to the start date they are.
            // null = no grace period (normal tier logic applies immediately).
            $table->unsignedInteger('grace_period_hours')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('cancellation_policies', function (Blueprint $table) {
            $table->dropColumn('grace_period_hours');
        });
    }
};
