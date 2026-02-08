<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Album extends Model
{
    use HasFactory;

    // The table associated with the model
    protected $table = 'albums';

    // The primary key for the model
    protected $primaryKey = 'id';

    // Indicates if the IDs are auto-incrementing
    public $incrementing = true;

    // The attributes that are mass assignable
    protected $fillable = [
        'path_to_img',
        'user_id',
        'annonce_id',
        'titre',
        'description', 
        'materielle_id',
        'event_id',
        'album_id'
    ];

    /**
     * Get the user that owns the album.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the photos for the album.
     */
    public function photos()
    {
        return $this->hasMany(Photo::class, 'album_id')->orderBy('order');
    }

    /**
     * Get the cover photo for the album.
     */
    public function coverPhoto()
    {
        return $this->hasOne(Photo::class, 'album_id')->where('is_cover', true);
    }

    /**
     * Get non-cover photos for the album.
     */
    public function nonCoverPhotos()
    {
        return $this->hasMany(Photo::class, 'album_id')
                    ->where('is_cover', false)
                    ->orderBy('order');
    }

    /**
     * Get the parent album if this is a nested album.
     */
    public function parentAlbum()
    {
        return $this->belongsTo(Album::class, 'album_id');
    }

    /**
     * Get child albums if this album has sub-albums.
     */
    public function childAlbums()
    {
        return $this->hasMany(Album::class, 'album_id');
    }

    /**
     * Check if album has a cover photo.
     */
    public function hasCover()
    {
        return $this->coverPhoto()->exists();
    }

    /**
     * Get the cover image URL.
     */
    public function getCoverImageUrlAttribute()
    {
        if ($this->path_to_img) {
            return $this->path_to_img;
        }
        
        $coverPhoto = $this->coverPhoto()->first();
        return $coverPhoto ? $coverPhoto->path_to_img : null;
    }

    /**
     * Count photos in album.
     */
    public function getPhotoCountAttribute()
    {
        return $this->photos()->count();
    }
}