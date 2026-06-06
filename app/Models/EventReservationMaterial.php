<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EventReservationMaterial extends Model
{
    use HasFactory;

    protected $table = 'event_reservation_materials';

    protected $fillable = [
        'event_reservation_id',
        'materielle_id',
        'supplier_id',
        'quantite',
        'prix_unitaire',
        'montant_total',
        'platform_fee_amount',
        'platform_fee_rate',
        'supplier_net_revenue',
        'supplier_credited',
    ];

    protected $casts = [
        'prix_unitaire'       => 'float',
        'montant_total'       => 'float',
        'platform_fee_amount' => 'float',
        'platform_fee_rate'   => 'float',
        'supplier_net_revenue'=> 'float',
        'supplier_credited'   => 'boolean',
    ];

    public function eventReservation()
    {
        return $this->belongsTo(Reservations_events::class, 'event_reservation_id');
    }

    public function materielle()
    {
        return $this->belongsTo(Materielles::class, 'materielle_id');
    }

    public function supplier()
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }
}
