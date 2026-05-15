<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds indexes on high-traffic query columns identified in security/perf audit.
 *
 * InnoDB already creates indexes for FK constraints, so this covers the
 * remaining columns used in WHERE/ORDER BY clauses that had no index.
 *
 * Deliberately omits low-cardinality booleans (is_active alone is a poor index
 * candidate — only two distinct values). Composite indexes noted inline.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── users ────────────────────────────────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            // Supports activity-feed and admin "filter by role" queries
            if (!$this->hasIndex('users', 'users_role_id_is_active_index')) {
                $table->index(['role_id', 'is_active'], 'users_role_id_is_active_index');
            }
            // Supports "last seen" and session-expiry cleanup queries
            if (!$this->hasIndex('users', 'users_last_login_at_index')) {
                $table->index('last_login_at', 'users_last_login_at_index');
            }
        });

        // ── reservations_events ──────────────────────────────────────────────
        Schema::table('reservations_events', function (Blueprint $table) {
            // Most list queries filter by status
            if (!$this->hasIndex('reservations_events', 'res_events_status_index')) {
                $table->index('status', 'res_events_status_index');
            }
            // Dashboard "pending reservations" sorted by date
            if (!$this->hasIndex('reservations_events', 'res_events_user_status_index')) {
                $table->index(['user_id', 'status'], 'res_events_user_status_index');
            }
        });

        // ── reservations_centres ─────────────────────────────────────────────
        Schema::table('reservations_centres', function (Blueprint $table) {
            if (!$this->hasIndex('reservations_centres', 'res_centres_status_index')) {
                $table->index('status', 'res_centres_status_index');
            }
            if (!$this->hasIndex('reservations_centres', 'res_centres_user_status_index')) {
                $table->index(['user_id', 'status'], 'res_centres_user_status_index');
            }
            // Date-range queries for availability checks
            if (!$this->hasIndex('reservations_centres', 'res_centres_date_range_index')) {
                $table->index(['date_debut', 'date_fin'], 'res_centres_date_range_index');
            }
        });

        // ── reservations_materielles ─────────────────────────────────────────
        Schema::table('reservations_materielles', function (Blueprint $table) {
            if (!$this->hasIndex('reservations_materielles', 'res_mat_status_index')) {
                $table->index('status', 'res_mat_status_index');
            }
            if (!$this->hasIndex('reservations_materielles', 'res_mat_user_status_index')) {
                $table->index(['user_id', 'status'], 'res_mat_user_status_index');
            }
        });

        // ── wallet_transactions ──────────────────────────────────────────────
        // (user_id, type, category already indexed per existing migration)
        Schema::table('wallet_transactions', function (Blueprint $table) {
            // Timeline queries sorted by created_at
            if (!$this->hasIndex('wallet_transactions', 'wallet_tx_created_at_index')) {
                $table->index('created_at', 'wallet_tx_created_at_index');
            }
        });

        // ── password_reset_tokens ─────────────────────────────────────────────
        // Cleanup scheduler scans for expired tokens
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            if (!$this->hasIndex('password_reset_tokens', 'prt_expires_at_index')) {
                $table->index('expires_at', 'prt_expires_at_index');
            }
        });

        // ── notifications ────────────────────────────────────────────────────
        // Unread notification badge queries
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (!$this->hasIndex('notifications', 'notifications_notifiable_read_at_index')) {
                    $table->index(['notifiable_id', 'read_at'], 'notifications_notifiable_read_at_index');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndexIfExists('users_role_id_is_active_index');
            $table->dropIndexIfExists('users_last_login_at_index');
        });
        Schema::table('reservations_events', function (Blueprint $table) {
            $table->dropIndexIfExists('res_events_status_index');
            $table->dropIndexIfExists('res_events_user_status_index');
        });
        Schema::table('reservations_centres', function (Blueprint $table) {
            $table->dropIndexIfExists('res_centres_status_index');
            $table->dropIndexIfExists('res_centres_user_status_index');
            $table->dropIndexIfExists('res_centres_date_range_index');
        });
        Schema::table('reservations_materielles', function (Blueprint $table) {
            $table->dropIndexIfExists('res_mat_status_index');
            $table->dropIndexIfExists('res_mat_user_status_index');
        });
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndexIfExists('wallet_tx_created_at_index');
        });
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropIndexIfExists('prt_expires_at_index');
        });
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndexIfExists('notifications_notifiable_read_at_index');
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        $indexes = \Illuminate\Support\Facades\DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$index]
        );
        return count($indexes) > 0;
    }
};
