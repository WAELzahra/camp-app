<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrganizerSupplierLink extends Model
{
    use HasFactory;

    protected $table = 'organizer_supplier_links';

    protected $fillable = [
        'organizer_id',
        'supplier_id',
        'status',
        'message',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function supplier()
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeForOrganizer($query, int $organizerId)
    {
        return $query->where('organizer_id', $organizerId);
    }

    public function scopeForSupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }
}
