<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CommentLike extends Pivot
{
    protected $table = 'comment_likes';
    
    protected $fillable = [
        'user_id',
        'comment_id'
    ];

    public $timestamps = true;

    /**
     * Get the user who liked the comment
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the comment that was liked
     */
    public function comment()
    {
        return $this->belongsTo(Comment::class);
    }
}