<?php

namespace App\Services\Kyc;

use App\Models\User;
use App\Notifications\CustomNotification;

/**
 * Camper-side KYC trigger (Task A-01 §1B).
 * Withdrawals and equipment reservations proceed immediately — KYC runs in
 * parallel: we flip the status to 'pending' and notify, never blocking or
 * reversing the action.
 */
class KycTriggerService
{
    public static function triggerForCamper(?User $user): void
    {
        if (!$user || $user->kyc_status !== 'not_required') {
            return; // already pending / verified / rejected — nothing to trigger
        }

        $user->update(['kyc_status' => 'pending']);

        $user->notify(new CustomNotification([
            'title'      => 'Vérification d\'identité requise',
            'content'    => 'Votre identité doit être vérifiée. Veuillez soumettre vos documents dans votre profil.',
            'type'       => 'security_alert',
            'priority'   => 'medium',
            'action_url' => '/settings',
            'action_text' => 'Soumettre mes documents',
        ]));
    }
}
