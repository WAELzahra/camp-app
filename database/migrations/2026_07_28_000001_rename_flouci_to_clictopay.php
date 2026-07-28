<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rebrand: Flouci -> ClicToPay (BH Bank's payment gateway — merchant
 * application accepted, going live with this provider under its real name).
 * Existing data is migrated in place — this is the same payment method
 * renamed, not a new gateway.
 *
 * The 'clictopay' key/spelling (no second 'k') already existed as an inactive
 * placeholder in payment_gateways/payment_transactions — this migration
 * activates and reuses it rather than introducing a different spelling.
 *
 * Konnect is removed outright rather than renamed: there is no Konnect API
 * or webhook integration anywhere in the codebase, only unused columns, a
 * settings toggle, and dev seed data (payments.konnect_* held obviously
 * fake fixtures like "KNX-20250101-001", never real transactions).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── payments: drop dead Konnect columns ───────────────────────────────
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['konnect_session_id', 'konnect_payment_id', 'konnect_payment_url']);
        });

        // ── user_bank_infos: flouci_phone -> clictopay_phone (real user data,
        //    preserved via rename) ───────────────────────────────────────────
        Schema::table('user_bank_infos', function (Blueprint $table) {
            $table->renameColumn('flouci_phone', 'clictopay_phone');
        });

        // ── withdrawal_requests.methode: 'flouci' -> 'clictopay' ──────────────
        DB::statement("ALTER TABLE withdrawal_requests MODIFY COLUMN methode ENUM('virement_bancaire','chèque','espèces','flouci','clictopay') DEFAULT 'virement_bancaire'");
        DB::table('withdrawal_requests')->where('methode', 'flouci')->update(['methode' => 'clictopay']);
        DB::statement("ALTER TABLE withdrawal_requests MODIFY COLUMN methode ENUM('virement_bancaire','chèque','espèces','clictopay') DEFAULT 'virement_bancaire'");

        // ── refund_requests.payment_channel: 'konnect' -> 'clictopay'. This is
        //    the generic "non-wallet, standard payment-record refund" branch —
        //    AdminPaymentController::approveRefund only ever checks for the
        //    literal 'wallet' value, so the other option is safe to rename ────
        DB::statement("ALTER TABLE refund_requests MODIFY COLUMN payment_channel ENUM('konnect','wallet','clictopay') DEFAULT 'konnect'");
        DB::table('refund_requests')->where('payment_channel', 'konnect')->update(['payment_channel' => 'clictopay']);
        DB::statement("ALTER TABLE refund_requests MODIFY COLUMN payment_channel ENUM('clictopay','wallet') DEFAULT 'clictopay'");

        // ── wallet_recharge_requests.method: 'flouci' -> 'clictopay' ──────────
        DB::statement("ALTER TABLE wallet_recharge_requests MODIFY COLUMN method ENUM('flouci','bank_transfer','clictopay') DEFAULT 'flouci'");
        DB::table('wallet_recharge_requests')->where('method', 'flouci')->update(['method' => 'clictopay']);
        DB::statement("ALTER TABLE wallet_recharge_requests MODIFY COLUMN method ENUM('clictopay','bank_transfer') DEFAULT 'clictopay'");

        // ── payment_transactions.gateway: 'flouci' -> 'clictopay' (the
        //    'clictopay' value already existed in this enum, just unused) ────
        DB::statement("ALTER TABLE payment_transactions MODIFY COLUMN gateway ENUM('flouci','clictopay','bank_transfer','reservation_credit','cash')");
        DB::table('payment_transactions')->where('gateway', 'flouci')->update(['gateway' => 'clictopay']);
        DB::statement("ALTER TABLE payment_transactions MODIFY COLUMN gateway ENUM('clictopay','bank_transfer','reservation_credit','cash')");

        // ── payment_gateways lookup table: merge the 'flouci' row into the
        //    already-seeded-but-inactive 'clictopay' row (correct spelling,
        //    correct label — just needed activating), then drop 'flouci' ────
        DB::table('payment_gateways')->where('key', 'clictopay')->update([
            'is_active' => true, 'updated_at' => now(),
        ]);
        DB::table('payment_gateways')->where('key', 'flouci')->delete();

        // ── platform_settings: rename keys, drop the never-implemented Konnect
        //    toggle. Plain update()/delete() — safe no-ops if a row happens to
        //    be missing (AdminSettingsController falls back to code defaults
        //    and auto-creates the row on first save regardless) ─────────────
        DB::table('platform_settings')->where('key', 'payment_link_flouci')->update([
            'key' => 'payment_link_clictopay', 'label' => 'ClicToPay Payment Link', 'updated_at' => now(),
        ]);
        DB::table('platform_settings')->where('key', 'gateway_flouci_enabled')->update([
            'key' => 'gateway_clictopay_enabled', 'label' => 'ClicToPay activé', 'updated_at' => now(),
        ]);
        DB::table('platform_settings')->where('key', 'gateway_konnect_enabled')->delete();
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('konnect_session_id')->nullable();
            $table->string('konnect_payment_id')->nullable();
            $table->string('konnect_payment_url')->nullable();
        });

        Schema::table('user_bank_infos', function (Blueprint $table) {
            $table->renameColumn('clictopay_phone', 'flouci_phone');
        });

        DB::statement("ALTER TABLE withdrawal_requests MODIFY COLUMN methode ENUM('virement_bancaire','chèque','espèces','clictopay','flouci') DEFAULT 'virement_bancaire'");
        DB::table('withdrawal_requests')->where('methode', 'clictopay')->update(['methode' => 'flouci']);
        DB::statement("ALTER TABLE withdrawal_requests MODIFY COLUMN methode ENUM('virement_bancaire','chèque','espèces','flouci') DEFAULT 'virement_bancaire'");

        DB::statement("ALTER TABLE refund_requests MODIFY COLUMN payment_channel ENUM('clictopay','wallet','konnect') DEFAULT 'clictopay'");
        DB::table('refund_requests')->where('payment_channel', 'clictopay')->update(['payment_channel' => 'konnect']);
        DB::statement("ALTER TABLE refund_requests MODIFY COLUMN payment_channel ENUM('konnect','wallet') DEFAULT 'konnect'");

        DB::statement("ALTER TABLE wallet_recharge_requests MODIFY COLUMN method ENUM('clictopay','bank_transfer','flouci') DEFAULT 'clictopay'");
        DB::table('wallet_recharge_requests')->where('method', 'clictopay')->update(['method' => 'flouci']);
        DB::statement("ALTER TABLE wallet_recharge_requests MODIFY COLUMN method ENUM('flouci','bank_transfer') DEFAULT 'flouci'");

        DB::statement("ALTER TABLE payment_transactions MODIFY COLUMN gateway ENUM('clictopay','flouci','bank_transfer','reservation_credit','cash')");
        DB::table('payment_transactions')->where('gateway', 'clictopay')->update(['gateway' => 'flouci']);
        DB::statement("ALTER TABLE payment_transactions MODIFY COLUMN gateway ENUM('flouci','clictopay','bank_transfer','reservation_credit','cash')");

        DB::table('payment_gateways')->insert([
            'key' => 'flouci', 'label' => 'Flouci', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payment_gateways')->where('key', 'clictopay')->update([
            'is_active' => false, 'updated_at' => now(),
        ]);

        DB::table('platform_settings')->where('key', 'payment_link_clictopay')->update([
            'key' => 'payment_link_flouci', 'label' => 'Online Payment Link', 'updated_at' => now(),
        ]);
        DB::table('platform_settings')->where('key', 'gateway_clictopay_enabled')->update([
            'key' => 'gateway_flouci_enabled', 'label' => 'Paiement en ligne activé', 'updated_at' => now(),
        ]);
        DB::table('platform_settings')->insert([
            'key' => 'gateway_konnect_enabled', 'value' => '1', 'label' => 'Konnect activé',
            'description' => 'Passerelle de paiement principale (carte bancaire)', 'type' => 'boolean', 'group' => 'gateway',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
};
