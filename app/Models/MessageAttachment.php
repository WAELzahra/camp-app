<?php
// app/Models/MessageAttachment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MessageAttachment extends Model
{
    use HasFactory;

    protected $table = 'message_attachments';

    protected $fillable = [
        'message_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'mime_type',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer',
    ];

    /**
     * Get the message
     */
    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get full URL for the attachment
     */
    public function getUrlAttribute()
    {
        return Storage::url($this->file_path);
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSizeAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if attachment is an image
     */
    public function isImage()
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if attachment is a document
     */
    public function isDocument()
    {
        return in_array($this->mime_type, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Get icon based on file type
     */
    public function getIconAttribute()
    {
        if ($this->isImage()) {
            return 'image';
        }
        
        if ($this->isDocument()) {
            return 'document';
        }
        
        return 'file';
    }
}