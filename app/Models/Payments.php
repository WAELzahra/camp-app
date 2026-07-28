<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Reservations_events;
use App\Models\User;
use App\Models\Events;

class Payments extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'montant',                  // Montant payé
        'description',              //  Description du paiement
        'status',                   //  Statut ENUM: pending, paid, failed, refunded_partial, refunded_total
        'user_id',                  //  Utilisateur qui paie
        'event_id',                 //  Événement lié
        'commission',               //  Commission prélevée
        'net_revenue',              // Revenu net (montant - commission)
    ];

    /**
     * 🔁 Une réservation liée à ce paiement (relation 1:1)
     */
    public function reservation()
    {
        return $this->hasOne(Reservations_events::class, 'payment_id');
    }

    /**
     * 🔁 Utilisateur ayant effectué ce paiement
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 🔁 Événement concerné par ce paiement
     */
    public function event()
    {
        return $this->belongsTo(Events::class);
    }
}
