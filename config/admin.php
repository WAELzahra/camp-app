<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin action password
    |--------------------------------------------------------------------------
    |
    | Extra confirmation password required for sensitive admin money actions
    | (refunds, balance adjustments, withdrawals, payment/commission settings).
    | Set ADMIN_ACTION_PASSWORD in the environment — never hard-code it.
    |
    */

    'action_password' => env('ADMIN_ACTION_PASSWORD'),

];
