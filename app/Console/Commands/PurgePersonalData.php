<?php

namespace App\Console\Commands;

use App\Models\Reservations_materielles;
use App\Models\User;
use App\Services\Storage\EncryptedDocumentStore;
use Illuminate\Console\Command;

/**
 * Personal-data retention (Données personnelles — durée de conservation).
 *
 * Scheduled daily. Two rules, both configurable in config/personal_data.php:
 *  1. KYC documents of REJECTED verifications are deleted after N days
 *     (default 90) when the user has not resubmitted.
 *  2. CIN snapshots on equipment reservations (cin_camper) are cleared
 *     N days after the reservation ended (default 365) — the snapshot is
 *     only needed while a rental dispute is plausible.
 *
 * Active documents (verified KYC, profile CIN, certificates, patentes,
 * legal documents) are kept for the lifetime of the account, as stated
 * in the privacy policy.
 */
class PurgePersonalData extends Command
{
    protected $signature = 'personal-data:purge {--dry-run : List what would be deleted without deleting}';

    protected $description = 'Applies the personal-data retention policy (rejected KYC docs, expired CIN snapshots)';

    public function handle(EncryptedDocumentStore $store): int
    {
        $dry = (bool) $this->option('dry-run');

        // ── Rule 1: rejected KYC documents older than the retention window ──
        $kycDays = (int) config('personal_data.rejected_kyc_retention_days', 90);
        $rejected = User::where('kyc_status', 'rejected')
            ->whereNotNull('kyc_document_path')
            ->where('kyc_rejected_at', '<', now()->subDays($kycDays))
            ->get();

        foreach ($rejected as $user) {
            $this->line(($dry ? '[dry] ' : '') . "KYC doc purge — user #{$user->id} (rejected {$user->kyc_rejected_at})");
            if (!$dry) {
                $store->delete($user->kyc_document_path);
                $user->update(['kyc_document_path' => null]);
            }
        }

        // ── Rule 2: CIN snapshots on finished equipment rentals ──
        $cinDays = (int) config('personal_data.reservation_cin_retention_days', 365);
        $query = Reservations_materielles::whereNotNull('cin_camper')
            ->whereNotNull('date_fin')
            ->where('date_fin', '<', now()->subDays($cinDays));

        $count = $query->count();
        $this->line(($dry ? '[dry] ' : '') . "CIN snapshots to clear: {$count}");
        if (!$dry && $count > 0) {
            $query->update(['cin_camper' => null]);
        }

        $this->info('Retention policy applied — ' . $rejected->count() . " KYC doc(s), {$count} CIN snapshot(s).");

        return self::SUCCESS;
    }
}
