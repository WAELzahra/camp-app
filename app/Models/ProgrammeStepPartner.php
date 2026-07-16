<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgrammeStepPartner extends Model
{
    protected $fillable = [
        'programme_step_id',
        'partner_id',
        'price',
        'commission_rate',
    ];

    protected $casts = [
        'price' => 'float',
        'commission_rate' => 'float',
    ];

    public function step()
    {
        return $this->belongsTo(ProgrammeStep::class, 'programme_step_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
