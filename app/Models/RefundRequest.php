<?php

// app/Models/RefundRequest.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundRequest extends Model
{
 protected $fillable = [
    'reservation_event_id',
    'payment_id',
    'montant_rembourse',
    'status',
];

}
