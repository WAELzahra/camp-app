<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;

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
];

    public function role()
{
    return $this->belongsTo(Role::class);
}

public function profile()
{
    return $this->hasOne(Profile::class);
}




}

