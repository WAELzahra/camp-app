<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widens payment_transactions.gateway to accept 'cash' so cash-at-centre
 * payments trace into the same admin Transactions tab as every other
 * gateway, instead of being invisible there.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payment_transactions MODIFY COLUMN gateway ENUM('flouci','clictopay','bank_transfer','reservation_credit','cash')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payment_transactions MODIFY COLUMN gateway ENUM('flouci','clictopay','bank_transfer','reservation_credit')");
    }
};
