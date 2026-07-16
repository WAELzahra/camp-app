<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgrammeRule extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'programme_id',
        'type',
        'content',
        'sort_order',
    ];

    public function programme()
    {
        return $this->belongsTo(Programme::class);
    }
}
