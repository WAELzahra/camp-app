<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'reporter_user_id',
        'reported_user_id',
        'report_type',
        'target_type',
        'target_id',
        'location_lat',
        'location_lng',
        'subject',
        'description',
        'page_url',
        'screenshot_path',
        'status',
        'priority',
        'admin_note',
    ];

    protected $casts = [
        'location_lat' => 'float',
        'location_lng' => 'float',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }
}
