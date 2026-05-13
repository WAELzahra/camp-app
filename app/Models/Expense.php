<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'titre',
        'montant',
        'categorie',
        'status',
        'date_depense',
        'event_id',
        'reference',
        'notes',
    ];

    protected $casts = [
        'montant'      => 'decimal:2',
        'date_depense' => 'date:Y-m-d',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Events::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
