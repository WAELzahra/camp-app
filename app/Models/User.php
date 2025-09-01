<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Favoris;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
    'name',
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
    'first_login',         
    'nombre_signalement',  
      'avatar',
];

    
    
    
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
    'email_verified_at' => 'datetime',
    'last_login_at' => 'datetime',   
    'first_login' => 'boolean',      
    'password' => 'hashed',
    'preferences' => 'array',
];

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


}

