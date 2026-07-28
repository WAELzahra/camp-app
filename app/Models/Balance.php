<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Balance extends Model
{
    protected $fillable = [
        'user_id',
        'solde_disponible',
        'solde_en_attente',
        'solde_du_plateforme',
        'total_encaisse',
        'total_retire',
        'total_rembourse',
        'dernier_mouvement_at',
    ];

    protected $casts = [
        'solde_disponible'   => 'decimal:2',
        'solde_en_attente'   => 'decimal:2',
        'solde_du_plateforme' => 'decimal:2',
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
     * Moves funds from disponible → en_attente (escrow lock at reservation creation).
     */
    public function lockFunds(float $montant): void
    {
        $this->decrement('solde_disponible', $montant);
        $this->increment('solde_en_attente', $montant);
        $this->update(['dernier_mouvement_at' => now()]);
    }

    /**
     * Decrements en_attente when escrowed funds are released to the beneficiary.
     * Safe: uses GREATEST(0,...) so legacy reservations (created via debiter) never go negative.
     */
    public function releaseEscrow(float $montant): void
    {
        // Bound parameters (not string interpolation) while keeping the update atomic
        // at the DB level — GREATEST(0, col - ?) still avoids a read-modify-write race.
        DB::update(
            'UPDATE balances SET solde_en_attente = GREATEST(0, solde_en_attente - ?), dernier_mouvement_at = ? WHERE id = ?',
            [$montant, now(), $this->id]
        );
    }

    /**
     * Returns escrowed funds to disponible (refund on rejection or cancellation).
     * Safe: always credits disponible; clears en_attente without going below 0.
     */
    public function refundEscrow(float $montant): void
    {
        DB::update(
            'UPDATE balances SET solde_en_attente = GREATEST(0, solde_en_attente - ?), solde_disponible = solde_disponible + ?, dernier_mouvement_at = ? WHERE id = ?',
            [$montant, $montant, now(), $this->id]
        );
    }

    /**
     * Increments the amount this provider owes the platform — used for cash-at-centre
     * payments, where the provider collected the full amount directly and now owes the
     * platform its commission + the camper's service fee that never passed through us.
     */
    public function crediterDette(float $montant): void
    {
        $this->increment('solde_du_plateforme', $montant);
        $this->update(['dernier_mouvement_at' => now()]);
    }

    /**
     * Reduces the amount owed to the platform — either an admin settling it manually
     * (cash collected from the provider directly) or an automatic offset against a
     * withdrawal payout. Floored at 0 via GREATEST(), same pattern as releaseEscrow().
     */
    public function debiterDette(float $montant): void
    {
        DB::update(
            'UPDATE balances SET solde_du_plateforme = GREATEST(0, solde_du_plateforme - ?), dernier_mouvement_at = ? WHERE id = ?',
            [$montant, now(), $this->id]
        );
    }

    /**
     * Retourne ou crée le balance d'un utilisateur.
     */
    public static function forUser(int $userId): self
    {
        return self::firstOrCreate(['user_id' => $userId]);
    }
}
