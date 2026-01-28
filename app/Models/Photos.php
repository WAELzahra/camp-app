<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Photos extends Model // Changed from "Photos" to "Photo" (singular)
{
    use HasFactory;

    protected $table = 'photos';

    protected $fillable = [
        'path_to_img',
        'user_id',
        'annonce_id',
        'materielle_id',
        'event_id',
        'album_id',
        'is_cover', // Added
        'order'     // Added
    ];

    protected $casts = [
        'is_cover' => 'boolean',
    ];

    /**
     * Get the annonce that owns the photo.
     */
    public function annonce()
    {
        return $this->belongsTo(Annonce::class);
    }

    /**
     * Get the user that owns the photo.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the event that owns the photo.
     */
    public function event()
    {
        return $this->belongsTo(Events::class, 'event_id');
    }

    /**
     * Get the album that owns the photo.
     */
    public function album()
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * Scope a query to only include cover photos.
     */
    public function scopeCover($query)
    {
        return $query->where('is_cover', true);
    }

    /**
     * Scope a query to only include non-cover photos.
     */
    public function scopeNonCover($query)
    {
        return $query->where('is_cover', false);
    }

    /**
     * Scope a query to order photos by their order field.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Check if this photo is a cover photo.
     */
    public function isCover()
    {
        return $this->is_cover;
    }

    /**
     * Set this photo as cover.
     */
    public function setAsCover()
    {
        // First, unset any existing cover in the same album
        if ($this->album_id) {
            Photo::where('album_id', $this->album_id)
                 ->where('is_cover', true)
                 ->update(['is_cover' => false]);
        }
        
        // Set this photo as cover
        $this->is_cover = true;
        $this->save();
        
        // Update album's path_to_img if this is in an album
        if ($this->album_id && $this->album) {
            $this->album->update(['path_to_img' => $this->path_to_img]);
        }
        
        return $this;
    }

    /**
     * Unset this photo as cover.
     */
    public function unsetAsCover()
    {
        $this->is_cover = false;
        $this->save();
        return $this;
    }

    /**
     * Get the URL of the photo.
     */
    public function getUrlAttribute()
    {
        return $this->path_to_img;
    }
}