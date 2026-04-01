<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Balance extends Model
{
    protected $fillable = [
        'user_id',
        'solde_disponible',
        'solde_en_attente',
        'total_encaisse',
        'total_retire',
        'total_rembourse',
        'dernier_mouvement_at',
    ];

    protected $casts = [
        'solde_disponible'   => 'decimal:2',
        'solde_en_attente'   => 'decimal:2',
        'total_encaisse'     => 'decimal:2',
        'total_retire'       => 'decimal:2',
        'total_rembourse'    => 'decimal:2',
        'dernier_mouvement_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Crédite le solde disponible d'un montant.
     */
    public function crediter(float $montant): void
    {
        $this->increment('solde_disponible', $montant);
        $this->increment('total_encaisse', $montant);
        $this->update(['dernier_mouvement_at' => now()]);
    }

    /**
     * Débite le solde disponible (remboursement ou retrait).
     */
    public function debiter(float $montant): void
    {
        $this->decrement('solde_disponible', $montant);
        $this->update(['dernier_mouvement_at' => now()]);
    }

    /**
     * Retourne ou crée le balance d'un utilisateur.
     */
    public static function forUser(int $userId): self
    {
        return self::firstOrCreate(['user_id' => $userId]);
    }
}
