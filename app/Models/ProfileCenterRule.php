<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileCenterRule extends Model
{
    protected $table = 'profile_center_rules';

    protected $fillable = [
        'profile_center_id',
        'type',
        'is_allowed',
        'notes'
    ];

    protected $casts = [
        'is_allowed' => 'boolean',
    ];

    /**
     * Rule type translations
     */
    const TYPE_TRANSLATIONS = [
        'pets' => 'Animaux acceptés',
        'alcohol' => 'Alcool autorisé',
        'smoking' => 'Fumer autorisé',
        'loud_music' => 'Musique forte / bruit autorisé',
        'unmarried_couples' => 'Couples non mariés acceptés',
        'campfires' => 'Feux de camp autorisés',
        'generators' => 'Groupes électrogènes autorisés',
        'outside_visitors' => 'Visiteurs extérieurs autorisés',
    ];

    /**
     * Rule type icons
     */
    const TYPE_ICONS = [
        'pets' => 'fas fa-paw',
        'alcohol' => 'fas fa-wine-glass',
        'smoking' => 'fas fa-smoking',
        'loud_music' => 'fas fa-volume-up',
        'unmarried_couples' => 'fas fa-user-friends',
        'campfires' => 'fas fa-fire',
        'generators' => 'fas fa-plug',
        'outside_visitors' => 'fas fa-door-open',
    ];

    /**
     * Get the profile center
     */
    public function profileCenter(): BelongsTo
    {
        return $this->belongsTo(ProfileCentre::class, 'profile_center_id');
    }

    /**
     * Get translated type name
     */
    public function getTranslatedTypeAttribute(): string
    {
        return self::TYPE_TRANSLATIONS[$this->type] ?? $this->type;
    }

    /**
     * Get icon for rule type
     */
    public function getIconAttribute(): string
    {
        return self::TYPE_ICONS[$this->type] ?? 'fas fa-check-circle';
    }

    /**
     * Check if allowed
     */
    public function isAllowed(): bool
    {
        return $this->is_allowed;
    }

    /**
     * Scope by type
     */
    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }
}
