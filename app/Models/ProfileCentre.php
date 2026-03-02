<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProfileCentre extends Model
{
    protected $table = 'profile_centres';
    
    protected $fillable = [
        'profile_id',
        'name',
        'adresse',
        'capacite',
        'price_per_night',
        'category',
        'services_offerts',
        'additional_services_description',
        'legal_document',
        'disponibilite',
        'ad_id',
        'photo_album_id',
        'latitude',
        'longitude',
        'contact_email',
        'contact_phone',
        'manager_name',
        'established_date',
        // Nouveaux champs documents
        'document_legal_path',
        'document_legal_filename',
        'document_legal_type',
        'document_legal_expiration',
        'adresse',
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
        return $this->belongsToMany(ServiceCategory::class, 'profile_center_services')
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
    public function availableServices()
    {
        return $this->services()
                    ->wherePivot('is_available', true);
    }

    /**
     * Get additional (paid) services
     */
    public function additionalServices()
    {
        return $this->services()
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
     * Sync services for this center
     */
    public function syncServices(array $services): void
    {
        $syncData = [];
        foreach ($services as $service) {
            $syncData[$service['service_category_id']] = [
                'price' => $service['price'],
                'unit' => $service['unit'] ?? null,
                'description' => $service['description'] ?? null,
                'is_available' => $service['is_available'] ?? true,
                'is_standard' => $service['is_standard'] ?? false,
                'min_quantity' => $service['min_quantity'] ?? 1,
                'max_quantity' => $service['max_quantity'] ?? null,
            ];
        }
        
        $this->services()->sync($syncData);
        
        // Update services_offerts for backward compatibility
        $this->updateServicesOfferts();
    }

    /**
     * Update services_offerts field from services and equipment
     */
    public function updateServicesOfferts(): void
    {
        $equipmentList = $this->availableEquipment()
            ->pluck('type')
            ->map(function ($type) {
                return ProfileCenterEquipment::TYPE_TRANSLATIONS[$type] ?? $type;
            })
            ->toArray();

        $servicesList = $this->availableServices()
            ->get()
            ->map(function ($service) {
                return "{$service->name} ({$service->pivot->price} TND/{$service->pivot->unit})";
            })
            ->toArray();

        $additional = $this->additional_services_description ? [$this->additional_services_description] : [];

        $this->update([
            'services_offerts' => implode(', ', array_merge($equipmentList, $servicesList, $additional))
        ]);
    }

    /**
     * Add equipment to center
     */
    public function addEquipment(string $type, bool $isAvailable = true, string $notes = null): ProfileCenterEquipment
    {
        return ProfileCenterEquipment::create([
            'profile_center_id' => $this->id,
            'type' => $type,
            'is_available' => $isAvailable,
            'notes' => $notes,
        ]);
    }

    /**
     * Add service to center
     */
    public function addService(ServiceCategory $service, float $price, array $options = []): ProfileCenterService
    {
        return ProfileCenterService::create([
            'profile_center_id' => $this->id,
            'service_category_id' => $service->id,
            'price' => $price,
            'unit' => $options['unit'] ?? $service->unit,
            'description' => $options['description'] ?? $service->description,
            'is_available' => $options['is_available'] ?? true,
            'is_standard' => $options['is_standard'] ?? $service->is_standard,
            'min_quantity' => $options['min_quantity'] ?? 1,
            'max_quantity' => $options['max_quantity'] ?? null,
        ]);
    }

    // ========== NOUVELLES MÉTHODES POUR LES DOCUMENTS ==========

    /**
     * Get the document legal URL
     */
    public function getDocumentLegalUrlAttribute(): ?string
    {
        // Priorité au nouveau champ, fallback sur l'ancien
        if ($this->document_legal_path) {
            return asset('storage/' . $this->document_legal_path);
        }
        
        if ($this->legal_document) {
            return asset('storage/' . $this->legal_document);
        }
        
        return null;
    }

    /**
     * Get the document legal filename
     */
    public function getDocumentLegalDisplayNameAttribute(): ?string
    {
        if ($this->document_legal_filename) {
            return $this->document_legal_filename;
        }
        
        if ($this->document_legal_path) {
            return basename($this->document_legal_path);
        }
        
        if ($this->legal_document) {
            return basename($this->legal_document);
        }
        
        return null;
    }

    /**
     * Get the document legal type label
     */
    public function getDocumentLegalTypeLabelAttribute(): ?string
    {
        $types = [
            'registre_commerce' => 'Registre de Commerce',
            'licence' => 'Licence d\'exploitation',
            'agrement' => 'Agrément',
            'carte_identite_fiscale' => 'Carte d\'Identité Fiscale',
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
            return true; // Pas de date d'expiration = toujours valide
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
        return !is_null($this->document_legal_path) || !is_null($this->legal_document);
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
     * Upload legal document
     */
    public function uploadLegalDocument($file, string $type = null, string $filename = null): ?string
    {
        // Delete old document if exists
        $this->deleteLegalDocument();
        
        // Store new document
        $originalName = $filename ?? $file->getClientOriginalName();
        $path = $file->store('documents/centres/' . $this->id, 'public');
        
        $this->update([
            'document_legal_path' => $path,
            'document_legal_filename' => $originalName,
            'document_legal_type' => $type,
        ]);
        
        return $path;
    }

    /**
     * Delete legal document
     */
    public function deleteLegalDocument(): bool
    {
        $deleted = false;
        
        if ($this->document_legal_path && \Storage::disk('public')->exists($this->document_legal_path)) {
            $deleted = \Storage::disk('public')->delete($this->document_legal_path);
        }
        
        // Also delete old legal_document if exists
        if ($this->legal_document && \Storage::disk('public')->exists($this->legal_document)) {
            \Storage::disk('public')->delete($this->legal_document);
        }
        
        $this->update([
            'document_legal_path' => null,
            'document_legal_filename' => null,
            'document_legal_type' => null,
            'document_legal_expiration' => null,
        ]);
        
        return $deleted;
    }

    /**
     * Set document expiration date
     */
    public function setDocumentLegalExpiration(string $date): void
    {
        $this->update([
            'document_legal_expiration' => $date
        ]);
    }

    /**
     * Scope centers with valid documents
     */
    public function scopeWithValidDocuments($query)
    {
        return $query->where(function($q) {
            $q->whereNotNull('document_legal_path')
              ->orWhereNotNull('legal_document');
        })->where(function($q) {
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
        return $query->whereNull('document_legal_path')
                     ->whereNull('legal_document');
    }

    // ========== FIN DES NOUVELLES MÉTHODES ==========

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
}