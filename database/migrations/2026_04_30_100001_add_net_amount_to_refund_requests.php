<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->decimal('net_amount', 10, 2)->nullable()->after('montant_rembourse');
            $table->decimal('commission_amount', 10, 2)->nullable()->after('net_amount');
            $table->decimal('commission_rate', 5, 2)->nullable()->after('commission_amount');
        });
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropColumn(['net_amount', 'commission_amount', 'commission_rate']);
        });
    }
};
