<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserContractAcceptance extends Model
{
    protected $fillable = [
        'user_id',
        'legal_document_id',
        'accepted_at',
        'ip_address',
        'user_agent',
        'acceptance_method',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legalDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class);
    }
}
