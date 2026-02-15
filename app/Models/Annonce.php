<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Annonce extends Model
{
    use HasFactory;
    
    protected $fillable = [
        "user_id",
        "title",
        "description",
        "start_date",
        "end_date",
        "auto_archive",
        "is_archived",
        "type",
        "activities",
        "latitude",
        "longitude",
        "address",
        "status",
        "views_count",
        "likes_count",
        "comments_count"
    ];

    protected $casts = [
        'activities' => 'array',
        'auto_archive' => 'boolean',
        'is_archived' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $attributes = [
        'auto_archive' => true,
        'is_archived' => false,
        'status' => 'pending',
        'views_count' => 0,
        'likes_count' => 0,
        'comments_count' => 0,
        'type' => 'Summer Camp'
    ];

    public function photos()
    {
        return $this->hasMany(Photo::class, 'annonce_id');
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'approved')
                     ->where('is_archived', false)
                     ->where(function($q) {
                         $q->whereNull('end_date')
                           ->orWhere('end_date', '>=', now());
                     });
    }

    public function scopeArchived($query)
    {
        return $query->where('is_archived', true)
                     ->orWhere(function($q) {
                         $q->where('auto_archive', true)
                           ->whereNotNull('end_date')
                           ->where('end_date', '<', now());
                     });
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Methods
    public function archive()
    {
        $this->update([
            'is_archived' => true, 
            'status' => 'archived'
        ]);
    }

    public function unarchive()
    {
        $this->update([
            'is_archived' => false, 
            'status' => 'approved'
        ]);
    }

    public function incrementViews()
    {
        $this->increment('views_count');
    }

    public function incrementLikes()
    {
        $this->increment('likes_count');
    }

    public function decrementLikes()
    {
        $this->decrement('likes_count');
    }

    public function incrementComments()
    {
        $this->increment('comments_count');
    }

    public function getDurationAttribute()
    {
        if ($this->start_date && $this->end_date) {
            return $this->start_date->diffInDays($this->end_date);
        }
        return null;
    }

    public function getFormattedDurationAttribute()
    {
        $days = $this->duration;
        if ($days === null) return null;
        return $days . ' ' . ($days === 1 ? 'day' : 'days');
    }

    public function getLocationAttribute()
    {
        if ($this->latitude && $this->longitude) {
            return [
                'lat' => $this->latitude,
                'lng' => $this->longitude,
                'address' => $this->address
            ];
        }
        return null;
    }

    public function isActive()
    {
        return $this->status === 'approved' && 
               !$this->is_archived && 
               (!$this->end_date || $this->end_date >= now());
    }

    public function isExpired()
    {
        return $this->end_date && $this->end_date < now();
    }

    public function shouldBeArchived()
    {
        return $this->auto_archive && $this->isExpired() && !$this->is_archived;
    }

    protected static function booted()
    {
        static::boot();
        static::creating(function ($annonce) {
            if (auth()->check()) {
                $annonce->user_id = auth()->id();
            }
        });

        // Auto-archive expired posts
        static::saving(function ($annonce) {
            if ($annonce->shouldBeArchived()) {
                $annonce->is_archived = true;
                $annonce->status = 'archived';
            }
        });
    }
    public function likes()
    {
        return $this->hasMany(AnnonceLike::class);
    }

    public function likedBy()
    {
        return $this->belongsToMany(User::class, 'annonce_likes')
                    ->withTimestamps();
    }

    // Helper method to check if user liked
    public function isLikedBy(User $user)
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }



}