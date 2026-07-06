<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Providers that existed BEFORE the KYC system and were already activated
     * by an admin are grandfathered as verified — otherwise the kyc.verified
     * middleware would instantly block every active provider on deploy.
     * New registrations still start as pending_kyc.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereIn('role_id', [2, 3, 4, 5])      // organizer, centre, fournisseur, guide
            ->where('is_active', 1)                  // already vetted & activated by admin
            ->where('kyc_status', 'not_required')    // untouched by the new system
            ->update([
                'kyc_status'      => 'verified',
                'kyc_verified_at' => now(),
                'account_status'  => 'active',
                'kyc_notes'       => '[migration] grandfathered — active provider predating KYC system',
            ]);

        // Inactive pre-existing providers enter the normal pending flow.
        DB::table('users')
            ->whereIn('role_id', [2, 3, 4, 5])
            ->where('is_active', 0)
            ->where('kyc_status', 'not_required')
            ->update([
                'kyc_status'     => 'pending',
                'account_status' => 'pending_kyc',
            ]);
    }

    public function down(): void
    {
        // Data migration — not reversible (original statuses unknown).
    }
};
