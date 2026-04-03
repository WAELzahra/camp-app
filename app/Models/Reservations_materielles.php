<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Reservations_materielles extends Model
{
    use HasFactory;

    protected $fillable = [
        'materielle_id',
        'user_id',
        'fournisseur_id',
        // Type
        'type_reservation',     // 'location' or 'achat'
        // Dates (rentals only)
        'date_debut',
        'date_fin',
        // Quantity & amount
        'quantite',
        'montant_total',        // renamed from montant_payer for clarity
        // Delivery
        'mode_livraison',       // 'pickup' or 'delivery'
        'adresse_livraison',
        'frais_livraison',
        // Legal
        'cin_camper',           // snapshot of CIN at reservation time (required for rentals)
        // Status & PIN
        'status',
        'pin_code',             // stored as bcrypt hash
        'pin_used_at',
        // Payment
        'payment_id',
        // Audit timestamps
        'confirmed_at',
        'retrieved_at',
        'returned_at',
        'promo_code_id',
        'discount_amount',
    ];

    protected $casts = [
        'date_debut'    => 'date',
        'date_fin'      => 'date',
        'confirmed_at'  => 'datetime',
        'retrieved_at'  => 'datetime',
        'returned_at'   => 'datetime',
        'pin_used_at'   => 'datetime',
        'montant_total' => 'float',
        'frais_livraison' => 'float',
    ];

    /**
     * Fields that should never appear in API responses.
     */
    protected $hidden = [
        'pin_code', // hashed — never expose
        'cin_camper', // sensitive legal data
    ];

    // -----------------------------------------------------------------------
    // Relations
    // -----------------------------------------------------------------------

    /**
     * The camper who made the reservation.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The supplier who owns the materiel.
     * Fixed: both user() and fournisseur() were pointing to User::class
     * without a foreign key — now explicit.
     */
    public function fournisseur()
    {
        return $this->belongsTo(User::class, 'fournisseur_id');
    }

    /**
     * The materiel being reserved.
     * Fixed: was hasOne (wrong direction) — should be belongsTo.
     */
    public function materielle()
    {
        return $this->belongsTo(Materielles::class, 'materielle_id');
    }

    /**
     * The payment associated with this reservation.
     * Fixed: was hasOne — should be belongsTo (reservation holds payment_id FK).
     */
    public function payment()
    {
        return $this->belongsTo(Payments::class, 'payment_id');
    }

    // -----------------------------------------------------------------------
    // PIN helpers
    // -----------------------------------------------------------------------

    /**
     * Generate a random 6-digit PIN, store it hashed, and return the raw value.
     * Call this when the supplier confirms the reservation.
     * The raw PIN is shown to the camper ONCE and never stored in plaintext.
     */
    public function generatePin(): string
    {
        $raw = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->pin_code = Hash::make($raw);
        $this->save();
        return $raw;
    }

    /**
     * Verify a PIN submitted by the supplier.
     * Returns true if correct, and marks the PIN as used.
     */
    public function verifyPin(string $raw): bool
    {
        if (!$this->pin_code || $this->pin_used_at) {
            return false; // already used or not generated
        }

        if (Hash::check($raw, $this->pin_code)) {
            $this->pin_used_at  = now();
            $this->retrieved_at = now();
            $this->status       = 'retrieved';
            $this->save();
            return true;
        }

        return false;
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopeRentals($query)
    {
        return $query->where('type_reservation', 'location');
    }

    public function scopeSales($query)
    {
        return $query->where('type_reservation', 'achat');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeAwaitingReturn($query)
    {
        // Rentals that have been retrieved but not yet returned
        return $query->rentals()
                     ->where('status', 'retrieved')
                     ->whereNull('returned_at');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Whether this reservation requires a CIN (rentals only).
     */
    public function requiresCin(): bool
    {
        return $this->type_reservation === 'location';
    }

    /**
     * Whether money can be released to the supplier.
     * - Sales: released on retrieval
     * - Rentals: released on return
     */
    public function isReadyForPayout(): bool
    {
        if ($this->type_reservation === 'achat') {
            return $this->status === 'retrieved';
        }

        return $this->status === 'returned';
    }
}