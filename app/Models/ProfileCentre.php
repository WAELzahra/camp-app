<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfileCentre extends Model
{
    protected $table = 'profile_centres';

    protected $fillable = [
        'profile_id',
        'name',
        'capacite',
        'price_per_night',
        'category',
        'legal_document',
        'document_legal_type',
        'document_legal_expiration',
        'disponibilite',
        'latitude',
        'longitude',
        'contact_email',
        'contact_phone',
        'manager_name',
        'established_date',
    ];

    protected $casts = [
        'disponibilite' => 'boolean',
        'price_per_night' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'established_date' => 'date',
        'capacite' => 'integer',
        'document_legal_expiration' => 'date',
    ];

    /**
     * Get the profile
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Get the user through profile
     */
    public function user()
    {
        return $this->profile->user();
    }

    /**
     * Get services offered by this center
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(ServiceCategory::class, 'profile_center_services', 'profile_center_id', 'service_category_id')
                    ->using(ProfileCenterService::class)
                    ->withPivot('price', 'unit', 'description', 'is_available', 'min_quantity', 'max_quantity', 'is_standard')
                    ->withTimestamps();
    }
    /**
     * Get center services pivot records
     */
    public function centerServices(): HasMany
    {
        return $this->hasMany(ProfileCenterService::class, 'profile_center_id');
    }

    /**
     * Get equipment available at this center
     */
    public function equipment(): HasMany
    {
        return $this->hasMany(ProfileCenterEquipment::class, 'profile_center_id');
    }

    /**
     * Get the standard (basic camping) service
     */
    public function standardService()
    {
        return $this->services()
                    ->wherePivot('is_standard', true)
                    ->wherePivot('is_available', true)
                    ->first();
    }

    /**
     * Get available services (excluding unavailable ones)
     */
    public function availableServices(): BelongsToMany
    {
        return $this->belongsToMany(ServiceCategory::class, 'profile_center_services', 'profile_center_id', 'service_category_id')
                    ->using(ProfileCenterService::class)
                    ->withPivot('price', 'unit', 'description', 'is_available', 'min_quantity', 'max_quantity', 'is_standard')
                    ->withTimestamps()
                    ->wherePivot('is_available', true);
    }

    /**
     * Get additional (paid) services
     */
    public function additionalServices(): BelongsToMany
    {
        return $this->belongsToMany(ServiceCategory::class, 'profile_center_services', 'profile_center_id', 'service_category_id')
                    ->using(ProfileCenterService::class)
                    ->withPivot('price', 'unit', 'description', 'is_available', 'min_quantity', 'max_quantity', 'is_standard')
                    ->withTimestamps()
                    ->wherePivot('is_available', true)
                    ->wherePivot('is_standard', false);
    }

    /**
     * Check if center has specific equipment
     */
    public function hasEquipment(string $type): bool
    {
        return $this->equipment()
                    ->where('type', $type)
                    ->where('is_available', true)
                    ->exists();
    }

    /**
     * Get available equipment
     */
    public function availableEquipment()
    {
        return $this->equipment()->available();
    }

    /**
     * Get formatted price per night
     */
    public function getFormattedPricePerNightAttribute(): string
    {
        return number_format($this->price_per_night, 2) . ' TND/night';
    }

    /**
     * Get capacity with label
     */
    public function getCapacityLabelAttribute(): string
    {
        return $this->capacite . ' people';
    }

    /**
     * Check if center is available
     */
    public function isAvailable(): bool
    {
        return $this->disponibilite;
    }

    /**
     * Get coordinates as array
     */
    public function getCoordinatesAttribute(): array
    {
        return [
            'lat' => (float) $this->latitude,
            'lng' => (float) $this->longitude,
        ];
    }

    /**
     * Get the legal document URL
     */
    public function getLegalDocumentUrlAttribute(): ?string
    {
        return $this->legal_document ? asset('storage/' . $this->legal_document) : null;
    }

    /**
     * Get the legal document type label
     */
    public function getDocumentLegalTypeLabelAttribute(): ?string
    {
        $types = [
            'registre_commerce' => 'Registre de Commerce',
            'licence' => "Licence d'exploitation",
            'agrement' => 'Agrément',
            'carte_identite_fiscale' => "Carte d'Identité Fiscale",
            'patente' => 'Patente',
            'autre' => 'Autre document'
        ];

        return $types[$this->document_legal_type] ?? $this->document_legal_type ?? 'Document légal';
    }

    /**
     * Check if document is valid (not expired)
     */
    public function isDocumentLegalValid(): bool
    {
        if (!$this->document_legal_expiration) {
            return true;
        }
        
        return $this->document_legal_expiration->isFuture();
    }

    /**
     * Check if document is expiring soon (within 30 days)
     */
    public function isDocumentLegalExpiringSoon(): bool
    {
        if (!$this->document_legal_expiration) {
            return false;
        }
        
        return $this->document_legal_expiration->isFuture() && 
               $this->document_legal_expiration->diffInDays(now()) <= 30;
    }

    /**
     * Check if document is expired
     */
    public function isDocumentLegalExpired(): bool
    {
        if (!$this->document_legal_expiration) {
            return false;
        }
        
        return $this->document_legal_expiration->isPast();
    }

    /**
     * Check if profile has legal document
     */
    public function hasLegalDocument(): bool
    {
        return !is_null($this->legal_document);
    }

    /**
     * Get document status with color for UI
     */
    public function getDocumentLegalStatusAttribute(): array
    {
        if (!$this->hasLegalDocument()) {
            return [
                'label' => 'Document manquant',
                'color' => 'red',
                'icon' => 'fa-times-circle'
            ];
        }

        if ($this->isDocumentLegalExpired()) {
            return [
                'label' => 'Document expiré',
                'color' => 'red',
                'icon' => 'fa-exclamation-circle'
            ];
        }

        if ($this->isDocumentLegalExpiringSoon()) {
            return [
                'label' => 'Expire bientôt',
                'color' => 'orange',
                'icon' => 'fa-exclamation-triangle'
            ];
        }

        return [
            'label' => 'Document valide',
            'color' => 'green',
            'icon' => 'fa-check-circle'
        ];
    }

    /**
     * Scope available centers
     */
    public function scopeAvailable($query)
    {
        return $query->where('disponibilite', true);
    }

    /**
     * Scope by category
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope with specific equipment
     */
    public function scopeWithEquipment($query, $type)
    {
        return $query->whereHas('equipment', function ($q) use ($type) {
            $q->where('type', $type)->where('is_available', true);
        });
    }

    /**
     * Scope with specific service
     */
    public function scopeWithService($query, $serviceName)
    {
        return $query->whereHas('services', function ($q) use ($serviceName) {
            $q->where('name', $serviceName)
              ->where('profile_center_services.is_available', true);
        });
    }

    /**
     * Scope centers with valid documents
     */
    public function scopeWithValidDocuments($query)
    {
        return $query->whereNotNull('legal_document')
                     ->where(function($q) {
                         $q->whereNull('document_legal_expiration')
                           ->orWhere('document_legal_expiration', '>', now());
                     });
    }

    /**
     * Scope centers with expiring documents
     */
    public function scopeWithExpiringDocuments($query, int $days = 30)
    {
        return $query->whereNotNull('document_legal_expiration')
                     ->where('document_legal_expiration', '<=', now()->addDays($days))
                     ->where('document_legal_expiration', '>', now());
    }

    /**
     * Scope centers with expired documents
     */
    public function scopeWithExpiredDocuments($query)
    {
        return $query->whereNotNull('document_legal_expiration')
                     ->where('document_legal_expiration', '<', now());
    }

    /**
     * Scope centers without documents
     */
    public function scopeWithoutDocuments($query)
    {
        return $query->whereNull('legal_document');
    }
}