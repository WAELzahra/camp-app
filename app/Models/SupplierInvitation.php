<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class SupplierInvitation extends Model
{
    use HasFactory;

    protected $table = 'supplier_invitations';

    protected $fillable = [
        'organizer_id',
        'email',
        'status',
        'token',
        'expires_at',
        'registered_at',
        'supplier_id',
    ];

    protected $casts = [
        'expires_at'     => 'datetime',
        'registered_at'  => 'datetime',
    ];

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function supplier()
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }
}
