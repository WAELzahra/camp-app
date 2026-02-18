<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',      
        'last_name',      
        'email',
        'adresse',
        'phone_number',
        'password',
        'role_id',
        'is_active',
        'ville',               
        'date_naissance',      
        'sexe',                
        'langue',
        // Nouveaux champs
        'cin',
        'cin_recto',
        'cin_verso',
        'certificat',
        'patente',
        'registre_commerce',
        'licence',
        'documents_status',
        'documents_verified_at',
        'documents_verified_by',
        'siret',
        'tva_number',
        'company_name',
        'legal_representative',
        'representative_cin',
        'first_login',         
        'nombre_signalement',  
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',   
        'first_login' => 'boolean',      
        'password' => 'hashed',
        'preferences' => 'array',
        'documents_verified_at' => 'datetime',
    ];

    
    

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */

    public function role()
{
    return $this->belongsTo(Role::class);
}

public function profile()
{
    return $this->hasOne(Profile::class);
}

public function profileGroupe()
{
    // profile_groupe via profile
    return $this->hasOneThrough(ProfileGroupe::class, Profile::class, 'user_id', 'profile_id');
}

public function events()
{
    return $this->hasMany(Events::class, 'group_id');
}

public function followedGroups()
{
    return $this->belongsToMany(ProfileGroupe::class, 'follows')->withTimestamps();
}

public function interestedEvents()
{
    return $this->belongsToMany(Events::class, 'interested_events', 'user_id', 'event_id');
}

public function isAdmin()
{
    // Charge la relation role si nécessaire
    if (!$this->relationLoaded('role')) {
        $this->load('role');
    }
    
    // Vérifie si l'utilisateur a un rôle et si c'est admin
    return $this->role && strtolower($this->role->name) === 'admin';
}

public function favoris()
{
    return $this->hasMany(Favoris::class, 'user_id');
}
    /**
     * Get the albums for the user.
     */
    public function albums()
    {
        return $this->hasMany(Album::class);
    }

    /**
     * Get the center album for the user (if they're a host).
     */
    public function centerAlbum()
    {
        return $this->hasOne(Album::class)->where('type', 'center');
    }

        /**
     * Vérifie si tous les documents obligatoires sont fournis
     */
    public function hasRequiredDocuments(): bool
    {
        if (!$this->role) {
            return false;
        }

        switch (strtolower($this->role->name)) {
            case 'campeur':
                return !empty($this->cin);
            
            case 'guide':
                return !empty($this->cin) && !empty($this->certificat);
            
            case 'centre':
            case 'center':
                return !empty($this->licence) && 
                       !empty($this->registre_commerce) && 
                       !empty($this->company_name) &&
                       !empty($this->legal_representative) &&
                       !empty($this->representative_cin);
            
            case 'groupe':
            case 'group':
                return !empty($this->patente) && 
                       !empty($this->company_name) &&
                       !empty($this->legal_representative) &&
                       !empty($this->representative_cin);
            
            case 'fournisseur':
            case 'supplier':
                return !empty($this->registre_commerce) && 
                       !empty($this->siret) &&
                       !empty($this->company_name);
            
            default:
                return false;
        }
    }

    /**
     * Récupère la liste des documents manquants
     */
    public function getMissingDocuments(): array
    {
        $missing = [];
        
        if (!$this->role) {
            return ['Rôle non défini'];
        }

        switch (strtolower($this->role->name)) {
            case 'campeur':
                if (empty($this->cin)) $missing[] = 'CIN';
                break;
            
            case 'guide':
                if (empty($this->cin)) $missing[] = 'CIN';
                if (empty($this->certificat)) $missing[] = 'Certificat';
                break;
            
            case 'centre':
            case 'center':
                if (empty($this->licence)) $missing[] = 'Licence';
                if (empty($this->registre_commerce)) $missing[] = 'Registre de commerce';
                if (empty($this->company_name)) $missing[] = 'Nom de la société';
                if (empty($this->legal_representative)) $missing[] = 'Représentant légal';
                if (empty($this->representative_cin)) $missing[] = 'CIN du représentant';
                break;
            
            case 'groupe':
            case 'group':
                if (empty($this->patente)) $missing[] = 'Patente';
                if (empty($this->company_name)) $missing[] = 'Nom du groupe';
                if (empty($this->legal_representative)) $missing[] = 'Responsable';
                if (empty($this->representative_cin)) $missing[] = 'CIN du responsable';
                break;
            
            case 'fournisseur':
            case 'supplier':
                if (empty($this->registre_commerce)) $missing[] = 'Registre de commerce';
                if (empty($this->siret)) $missing[] = 'Numéro SIRET';
                if (empty($this->company_name)) $missing[] = 'Nom de l\'entreprise';
                break;
        }
        
        return $missing;
    }
}



