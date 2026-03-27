<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $table = 'feedbacks';

    protected $fillable = [
        'user_id',
        'target_id',
        'event_id',
        'zone_id',
        'contenu',
        'response',
        'note',
        'type',
        'status',
        'rejection_reason'
    ];

    protected $casts = [
        'note' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = [
        'target_name',
        'target_type',
        'author_name',
        'author_avatar',
        'period',
        'is_fournisseur'
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Auteur du feedback
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Alias pour user (pour le code existant)
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * User ciblé (guide, fournisseur, centre, etc.)
     */
    public function userTarget()
    {
        return $this->belongsTo(User::class, 'target_id');
    }

    /**
     * Event ciblé - Note: votre modèle s'appelle Events (avec 's')
     */
    public function event()
    {
        return $this->belongsTo(Events::class, 'event_id');
    }

    /**
     * Zone ciblée
     */
    public function zone()
    {
        return $this->belongsTo(Camping_Zones::class, 'zone_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope pour les feedbacks en attente
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope pour les feedbacks approuvés
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope pour les feedbacks rejetés
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope pour les feedbacks de fournisseurs
     */
    public function scopeFournisseurs($query)
    {
        return $query->where('type', 'fournisseur');
    }

    /**
     * Scope pour les feedbacks de guides
     */
    public function scopeGuides($query)
    {
        return $query->whereIn('type', ['guide', 'groupe', 'user']);
    }

    /**
     * Scope pour les feedbacks de zones
     */
    public function scopeZones($query)
    {
        return $query->where('type', 'zone');
    }

    /**
     * Scope pour les feedbacks de centres
     */
    public function scopeCentres($query)
    {
        return $query->whereIn('type', ['centre_user', 'centre_camping']);
    }

    /**
     * Scope pour les feedbacks d'événements
     */
    public function scopeEvents($query)
    {
        return $query->where('type', 'event');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    /**
     * Récupère le nom de l'auteur
     */
    public function getAuthorNameAttribute()
    {
        if (!$this->user) {
            return 'Utilisateur inconnu';
        }
        return trim($this->user->first_name . ' ' . $this->user->last_name);
    }

    /**
     * Récupère l'avatar de l'auteur
     */
    public function getAuthorAvatarAttribute()
    {
        if ($this->user && $this->user->avatar) {
            return $this->user->avatar;
        }
        
        $name = $this->user ? 
            trim($this->user->first_name . ' ' . $this->user->last_name) : 
            'Utilisateur';
            
        return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=random';
    }

    /**
     * Récupère le nom de la cible
     */
    public function getTargetNameAttribute()
    {
        switch ($this->type) {
            case 'zone':
                return $this->zone?->nom ?? 'Zone inconnue';
            
            case 'event':
                return $this->event?->title ?? $this->event?->nom ?? 'Événement inconnu';
            
            case 'fournisseur':
            case 'user':
            case 'groupe':
            case 'guide':
            case 'centre_user':
            case 'centre_camping':
                if ($this->userTarget) {
                    return trim($this->userTarget->first_name . ' ' . $this->userTarget->last_name);
                }
                
                // Chercher dans les tables spécifiques si userTarget est null
                if ($this->type === 'fournisseur') {
                    $profileFournisseur = ProfileFournisseur::where('profile_id', $this->target_id)->first();
                    return $profileFournisseur ? 'Fournisseur' : 'Fournisseur inconnu';
                }
                
                if (in_array($this->type, ['centre_user', 'centre_camping'])) {
                    $centre = CampingCentre::where('user_id', $this->target_id)->first();
                    return $centre?->nom ?? 'Centre inconnu';
                }
                
                return 'Cible inconnue';
            
            default:
                return 'Cible';
        }
    }

    /**
     * Récupère le type de cible pour l'affichage
     */
    public function getTargetTypeAttribute()
    {
        $map = [
            'user' => 'guide',
            'groupe' => 'guide',
            'guide' => 'guide',
            'fournisseur' => 'fournisseur',
            'centre_user' => 'centre',
            'centre_camping' => 'centre',
            'zone' => 'zone',
            'event' => 'event'
        ];
        
        return $map[$this->type] ?? 'guide';
    }

    /**
     * Récupère la période relative
     */
    public function getPeriodAttribute()
    {
        return $this->created_at ? $this->created_at->diffForHumans() : null;
    }

    /**
     * Vérifie si c'est un feedback de fournisseur
     */
    public function getIsFournisseurAttribute()
    {
        return $this->type === 'fournisseur';
    }

    /**
     * Vérifie si c'est un feedback de guide
     */
    public function getIsGuideAttribute()
    {
        return in_array($this->type, ['guide', 'groupe', 'user']);
    }

    /**
     * Vérifie si la cible existe encore
     */
    public function getTargetExistsAttribute()
    {
        switch ($this->type) {
            case 'zone':
                return !is_null($this->zone);
            case 'event':
                return !is_null($this->event);
            default:
                return !is_null($this->userTarget);
        }
    }

    /**
     * Récupère les détails complets de la cible
     */
    public function getTargetDetailsAttribute()
    {
        switch ($this->type) {
            case 'zone':
                return $this->zone ? [
                    'id' => $this->zone->id,
                    'nom' => $this->zone->nom,
                    'adresse' => $this->zone->adresse,
                    'image' => $this->zone->image,
                    'type_activitee' => $this->zone->type_activitee,
                ] : null;
            
            case 'event':
                return $this->event ? [
                    'id' => $this->event->id,
                    'title' => $this->event->title,
                    'date_sortie' => $this->event->date_sortie,
                    'category' => $this->event->category,
                    'prix' => $this->event->prix_place,
                ] : null;
            
            case 'fournisseur':
                if ($this->userTarget) {
                    $profile = ProfileFournisseur::where('profile_id', $this->userTarget->profile?->id)->first();
                    return [
                        'id' => $this->userTarget->id,
                        'nom' => trim($this->userTarget->first_name . ' ' . $this->userTarget->last_name),
                        'email' => $this->userTarget->email,
                        'product_category' => $profile?->product_category,
                    ];
                }
                return null;
            
            case 'centre_user':
            case 'centre_camping':
                if ($this->userTarget) {
                    $centre = CampingCentre::where('user_id', $this->userTarget->id)->first();
                    return [
                        'id' => $this->userTarget->id,
                        'nom' => $centre?->nom ?? trim($this->userTarget->first_name . ' ' . $this->userTarget->last_name),
                        'adresse' => $centre?->adresse,
                    ];
                }
                return null;
            
            case 'guide':
            case 'groupe':
            case 'user':
                return $this->userTarget ? [
                    'id' => $this->userTarget->id,
                    'nom' => trim($this->userTarget->first_name . ' ' . $this->userTarget->last_name),
                    'email' => $this->userTarget->email,
                ] : null;
            
            default:
                return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Approuve le feedback
     */
    public function approve()
    {
        return $this->update(['status' => 'approved', 'rejection_reason' => null]);
    }

    /**
     * Rejette le feedback avec une raison
     */
    public function reject(string $reason)
    {
        return $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason
        ]);
    }

    /**
     * Vérifie si le feedback peut être approuvé
     */
 /**
 * Vérifie si le feedback peut être approuvé
 */
public function canBeApproved()
{
    return true; // Toujours possible de changer d'avis
}

/**
 * Vérifie si le feedback peut être rejeté
 */
public function canBeRejected()
{
    return true; // Toujours possible de changer d'avis
}

    /**
     * Récupère les statistiques globales
     */
    public static function getStats()
    {
        return [
            'total' => self::count(),
            'pending' => self::pending()->count(),
            'approved' => self::approved()->count(),
            'rejected' => self::rejected()->count(),
            'average_rating' => round(self::whereNotNull('note')->avg('note') ?? 0, 1),
            'today' => self::whereDate('created_at', today())->count(),
            'this_week' => self::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'by_type' => [
                'fournisseurs' => self::fournisseurs()->count(),
                'guides' => self::guides()->count(),
                'zones' => self::zones()->count(),
                'centres' => self::centres()->count(),
                'events' => self::events()->count(),
            ],
            'by_rating' => [
                1 => self::where('note', 1)->count(),
                2 => self::where('note', 2)->count(),
                3 => self::where('note', 3)->count(),
                4 => self::where('note', 4)->count(),
                5 => self::where('note', 5)->count(),
            ]
        ];
    }
}