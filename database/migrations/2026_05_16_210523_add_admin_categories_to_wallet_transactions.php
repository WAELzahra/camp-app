<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE wallet_transactions
            MODIFY COLUMN category ENUM(
                'reservation_payment',
                'reservation_income',
                'refund_out',
                'refund_in',
                'withdrawal',
                'deposit',
                'admin_credit',
                'admin_debit'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE wallet_transactions
            MODIFY COLUMN category ENUM(
                'reservation_payment',
                'reservation_income',
                'refund_out',
                'refund_in',
                'withdrawal',
                'deposit'
            ) NOT NULL
        ");
    }
};
