<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Favoris;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

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

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'first_login'       => 'boolean',
        'password'          => 'hashed',
        'preferences'       => 'array',
    ];

    // -----------------------------------------------------------------------
    // Existing relations (unchanged)
    // -----------------------------------------------------------------------

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

    public function favoris()
    {
        return $this->hasMany(Favoris::class, 'user_id');
    }

    public function albums()
    {
        return $this->hasMany(Album::class);
    }

    public function centerAlbum()
    {
        return $this->hasOne(Album::class)->where('type', 'center');
    }

    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot('role', 'last_read_at', 'joined_at', 'left_at', 'is_muted')
            ->withTimestamps();
    }

    public function conversationParticipants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function messageStatuses()
    {
        return $this->hasMany(MessageStatus::class);
    }

    public function messageReactions()
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function notificationPreferences()
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function notificationLogs()
    {
        return $this->hasMany(NotificationLog::class);
    }

    // -----------------------------------------------------------------------
    // Shop (Fournisseur) relations
    // -----------------------------------------------------------------------

    /**
     * The boutique owned by this supplier.
     * One supplier = one boutique.
     */
    public function boutique()
    {
        return $this->hasOne(Boutiques::class, 'fournisseur_id');
    }

    /**
     * All materiels listed by this supplier.
     */
    public function materielles()
    {
        return $this->hasMany(Materielles::class, 'fournisseur_id');
    }

    /**
     * Reservations where this user is the CAMPER (buyer/renter).
     */
    public function reservationsCamper()
    {
        return $this->hasMany(Reservations_materielles::class, 'user_id');
    }

    /**
     * Reservations where this user is the SUPPLIER (receiving the request).
     */
    public function reservationsFournisseur()
    {
        return $this->hasMany(Reservations_materielles::class, 'fournisseur_id');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    public function isAdmin(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        return $this->role && strtolower($this->role->name) === 'admin';
    }

    public function isFournisseur(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        return $this->role && strtolower($this->role->name) === 'fournisseur';
    }

    /**
     * Whether this user has a CIN document uploaded.
     * CIN lives on the Profile model (cin_path).
     * Used to gate rental reservations.
     */
    public function hasCin(): bool
    {
        return $this->profile?->hasCin() ?? false;
    }

    public function isOnline(): bool
    {
        if (!$this->last_login_at) return false;
        return $this->is_active && $this->last_login_at->gt(now()->subMinutes(5));
    }

    public function getOnlineStatusAttribute(): array
    {
        if ($this->isOnline()) {
            return ['status' => 'online', 'text' => 'Online', 'color' => 'green'];
        }

        if ($this->last_login_at) {
            $minutes = now()->diffInMinutes($this->last_login_at);

            if ($minutes < 60) {
                return ['status' => 'offline', 'text' => "Last seen {$minutes} minutes ago", 'color' => 'gray'];
            } elseif ($minutes < 1440) {
                $hours = floor($minutes / 60);
                return ['status' => 'offline', 'text' => "Last seen {$hours} hours ago", 'color' => 'gray'];
            } else {
                $days = floor($minutes / 1440);
                return ['status' => 'offline', 'text' => "Last seen {$days} days ago", 'color' => 'gray'];
            }
        }

        return ['status' => 'offline', 'text' => 'Never logged in', 'color' => 'gray'];
    }
    public function likedAnnonces()
    {
        return $this->belongsToMany(Annonce::class, 'annonce_likes', 'user_id', 'annonce_id')
                    ->withTimestamps();
    }
    public function getUnreadMessagesCountAttribute(): int
    {
        $count = 0;
        foreach ($this->conversations as $conversation) {
            $count += $conversation->getUnreadCountForUser($this->id);
        }
        return $count;
    }

    public function getUnreadNotificationsCountAttribute(): int
    {
        return $this->notifications()->unread()->count();
    }
}