<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('profiles', 'engagement_mode')) {
                $table->enum('engagement_mode', ['agency', 'commission'])
                    ->default('commission')->after('is_public');
            }
            if (!Schema::hasColumn('profiles', 'commission_rate')) {
                $table->decimal('commission_rate', 5, 2)->nullable()->after('engagement_mode');
            }
            if (!Schema::hasColumn('profiles', 'agency_margin')) {
                $table->decimal('agency_margin', 5, 2)->nullable()->after('commission_rate');
            }
            if (!Schema::hasColumn('profiles', 'engagement_mode_locked_at')) {
                $table->timestamp('engagement_mode_locked_at')->nullable()->after('agency_margin');
            }
            if (!Schema::hasColumn('profiles', 'engagement_mode_change_requested_at')) {
                $table->timestamp('engagement_mode_change_requested_at')->nullable()->after('engagement_mode_locked_at');
            }
            if (!Schema::hasColumn('profiles', 'engagement_mode_change_to')) {
                $table->enum('engagement_mode_change_to', ['agency', 'commission'])->nullable()
                    ->after('engagement_mode_change_requested_at');
            }
        });

        // Immutable per-reservation snapshot of the provider's mode + rate at
        // booking time (Task 2E — must never change retroactively).
        foreach (['reservations_centres', 'reservations_events', 'reservations_materielles'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'engagement_mode_snapshot')) {
                    $table->enum('engagement_mode_snapshot', ['agency', 'commission'])->nullable();
                }
                if (!Schema::hasColumn($tableName, 'engagement_rate_snapshot')) {
                    // commission_rate OR agency_margin depending on the mode
                    $table->decimal('engagement_rate_snapshot', 5, 2)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn([
                'engagement_mode', 'commission_rate', 'agency_margin',
                'engagement_mode_locked_at', 'engagement_mode_change_requested_at', 'engagement_mode_change_to',
            ]);
        });

        foreach (['reservations_centres', 'reservations_events', 'reservations_materielles'] as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn(['engagement_mode_snapshot', 'engagement_rate_snapshot']);
                });
            }
        }
    }
};
