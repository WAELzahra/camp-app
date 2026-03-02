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
       'first_name',      
        'last_name',      
        'email',
        'phone_number',
        'password',
        'addresse',
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
     * Get all conversations the user participates in
     */
    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot('role', 'last_read_at', 'joined_at', 'left_at', 'is_muted')
            ->withTimestamps();
    }

    /**
     * Get conversation participants
     */
    public function conversationParticipants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * Get messages sent by user
     */
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Get message statuses
     */
    public function messageStatuses()
    {
        return $this->hasMany(MessageStatus::class);
    }

    /**
     * Get message reactions
     */
    public function messageReactions()
    {
        return $this->hasMany(MessageReaction::class);
    }

    /**
     * Get notification preferences
     */
    public function notificationPreferences()
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * Get notification logs
     */
    public function notificationLogs()
    {
        return $this->hasMany(NotificationLog::class);
    }

    /**
     * Get unread messages count across all conversations
     */
    public function getUnreadMessagesCountAttribute()
    {
        $count = 0;
        
        foreach ($this->conversations as $conversation) {
            $count += $conversation->getUnreadCountForUser($this->id);
        }
        
        return $count;
    }

    /**
     * Get unread notifications count
     */
    public function getUnreadNotificationsCountAttribute()
    {
        return $this->notifications()->unread()->count();
    }
    /**
     * Check if user is currently online
     * Users are considered online if they were active in the last 5 minutes
     */
    public function isOnline()
    {
        if (!$this->last_login_at) return false;
        
        // Consider user online if last activity was within 5 minutes AND is_active is true
        return $this->is_active && $this->last_login_at->gt(now()->subMinutes(5));
    }

    /**
     * Get online status with last seen text
     */
    public function getOnlineStatusAttribute()
    {
        if ($this->isOnline()) {
            return [
                'status' => 'online',
                'text' => 'Online',
                'color' => 'green'
            ];
        }
        
        if ($this->last_login_at) {
            $minutes = now()->diffInMinutes($this->last_login_at);
            
            if ($minutes < 60) {
                return [
                    'status' => 'offline',
                    'text' => "Last seen {$minutes} minutes ago",
                    'color' => 'gray'
                ];
            } elseif ($minutes < 1440) {
                $hours = floor($minutes / 60);
                return [
                    'status' => 'offline',
                    'text' => "Last seen {$hours} hours ago",
                    'color' => 'gray'
                ];
            } else {
                $days = floor($minutes / 1440);
                return [
                    'status' => 'offline',
                    'text' => "Last seen {$days} days ago",
                    'color' => 'gray'
                ];
            }
        }
        
        return [
            'status' => 'offline',
            'text' => 'Never logged in',
            'color' => 'gray'
        ];
    }


}
