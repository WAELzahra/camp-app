<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number', 'invoice_type', 'reservation_id', 'reservation_type',
        'issuer_entity', 'issuer_fiscal_id', 'client_name', 'client_fiscal_id',
        'amount_ht', 'tva_rate', 'tva_amount', 'timbre_fiscal', 'amount_ttc',
        'payment_method', 'issued_at', 'pdf_path',
        'voided_at', 'voided_by', 'void_reason',
    ];

    protected $casts = [
        'amount_ht'     => 'decimal:2',
        'tva_rate'      => 'decimal:2',
        'tva_amount'    => 'decimal:2',
        'timbre_fiscal' => 'decimal:2',
        'amount_ttc'    => 'decimal:2',
        'issued_at'     => 'datetime',
        'voided_at'     => 'datetime',
    ];

    public function auditLogs()
    {
        return $this->hasMany(InvoiceAuditLog::class);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
