<?php
// app/Models/NotificationTemplate.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $table = 'notification_templates';

    protected $fillable = [
        'key',
        'name',
        'subject',
        'content',
        'variables',
        'channels',
        'priority',
    ];

    protected $casts = [
        'variables' => 'array',
        'channels' => 'array',
    ];

    /**
     * Render template with variables
     */
    public function render(array $data = [])
    {
        $content = $this->content;
        
        foreach ($data as $key => $value) {
            $content = str_replace("{{$key}}", $value, $content);
        }
        
        return $content;
    }

    /**
     * Get rendered subject
     */
    public function renderSubject(array $data = [])
    {
        if (!$this->subject) {
            return null;
        }
        
        $subject = $this->subject;
        
        foreach ($data as $key => $value) {
            $subject = str_replace("{{$key}}", $value, $subject);
        }
        
        return $subject;
    }

    /**
     * Scope by key
     */
    public function scopeByKey($query, $key)
    {
        return $query->where('key', $key);
    }
}