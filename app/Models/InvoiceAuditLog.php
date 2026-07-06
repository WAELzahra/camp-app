<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceAuditLog extends Model
{
    public $timestamps = false; // created_at set by the DB default

    protected $fillable = ['invoice_id', 'user_id', 'action', 'details', 'created_at'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
