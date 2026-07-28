<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the debt-to-platform column needed by cash-at-centre payments: unlike
 * every other balance field here (platform owes the provider), this one runs
 * the other direction — the provider collected cash directly, so they now owe
 * the platform its commission + the camper's service fee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('balances', function (Blueprint $table) {
            if (!Schema::hasColumn('balances', 'solde_du_plateforme')) {
                $table->decimal('solde_du_plateforme', 12, 2)->default(0)->after('solde_en_attente');
            }
        });
    }

    public function down(): void
    {
        Schema::table('balances', function (Blueprint $table) {
            if (Schema::hasColumn('balances', 'solde_du_plateforme')) {
                $table->dropColumn('solde_du_plateforme');
            }
        });
    }
};
