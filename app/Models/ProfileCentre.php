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
        'established_date'
    ];

    protected $casts = [
        'disponibilite' => 'boolean',
        'price_per_night' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'established_date' => 'date',
        'capacite' => 'integer',
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