<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Signales extends Model
{
    use HasFactory;

    protected $fillable = [
        "user_id",
        "target_id",
        "zone_id",
        "type",
        "contenu",
        "status",
        "admin_id",
        "validated_at",
        "rejected_at",
        "rejection_reason"
    ];

    // Relations
    public function user() {
        return $this->belongsTo(User::class);
    }

    public function target_user() {
        return $this->belongsTo(User::class, 'target_id');
    }

    public function zone() {
        return $this->belongsTo(Camping_zones::class, 'zone_id');
    }

    public function admin() {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
