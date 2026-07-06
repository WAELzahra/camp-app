<?php

/*
|--------------------------------------------------------------------------
| Personal data retention (Données personnelles)
|--------------------------------------------------------------------------
| Windows used by the daily `personal-data:purge` command. These values are
| what the privacy policy communicates to users — change both together.
*/

return [

    // KYC documents of rejected verifications, when not resubmitted
    'rejected_kyc_retention_days' => env('RETENTION_REJECTED_KYC_DAYS', 90),

    // CIN snapshots attached to equipment rentals, after the rental ends
    'reservation_cin_retention_days' => env('RETENTION_RESERVATION_CIN_DAYS', 365),
];
