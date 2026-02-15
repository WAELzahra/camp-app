<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'annonce_id',
        'parent_id',
        'content',
        'likes_count',
        'is_edited',
        'is_pinned',
        'is_hidden'
    ];

    protected $casts = [
        'is_edited' => 'boolean',
        'is_pinned' => 'boolean',
        'is_hidden' => 'boolean',
    ];

    /**
     * Get the user who wrote the comment
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the annonce this comment belongs to
     */
    public function annonce()
    {
        return $this->belongsTo(Annonce::class);
    }

    /**
     * Get the parent comment (if this is a reply)
     */
    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Get replies to this comment
     */
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id')->latest();
    }
    /**
     * Get the likes for this comment
     */
    public function likes()
    {
        return $this->hasMany(CommentLike::class);
    }

    /**
     * Check if comment is a reply
     */
    public function isReply()
    {
        return !is_null($this->parent_id);
    }

    /**
     * Get comment depth (0 for top-level, 1 for reply, etc.)
     */
    public function getDepthAttribute()
    {
        $depth = 0;
        $parent = $this->parent;
        
        while ($parent) {
            $depth++;
            $parent = $parent->parent;
        }
        
        return $depth;
    }

    /**
     * Scope to get only top-level comments (not replies)
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get visible comments
     */
    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false);
    }

    /**
     * Scope to get pinned comments first
     */
    public function scopePinnedFirst($query)
    {
        return $query->orderBy('is_pinned', 'desc')->latest();
    }
    /**
     * Get users who liked this comment (many-to-many)
     */
    public function likedBy()
    {
        return $this->belongsToMany(User::class, 'comment_likes')
                    ->withTimestamps();
    }
    /**
     * Check if comment is liked by a user
     */
    public function isLikedBy(User $user)
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }
}