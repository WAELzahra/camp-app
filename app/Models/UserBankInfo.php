<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBankInfo extends Model
{
    protected $table = 'user_bank_infos';

    protected $fillable = [
        'user_id',
        'bank_name',
        'account_holder',
        'iban',
        'flouci_phone',
        'card_last4',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
