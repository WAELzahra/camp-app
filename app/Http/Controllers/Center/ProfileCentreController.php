<?php

namespace App\Http\Controllers;

use App\Models\ProfileCentre;
use App\Models\Profile;
use App\Models\ServiceCategory;
use App\Models\ProfileCenterEquipment;
use App\Models\ProfileCenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileCentreController extends Controller
{
    /**
     * Display a listing of centers.
     */
    public function index()
    {
        $centers = ProfileCentre::with(['profile.user', 'services'])
            ->where('disponibilite', true)
            ->orderBy('created_at', 'desc')
            ->paginate(12);
            
        return view('centers.index', compact('centers'));
    }

    /**
     * Show the form for creating a new center.
     */
    public function create()
    {
        // Check if user already has a center
        $user = Auth::user();
        $existingCenter = ProfileCentre::whereHas('profile', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->first();

        if ($existingCenter) {
            return redirect()->route('centers.edit', $existingCenter->id)
                ->with('info', 'You already have a center. You can edit it here.');
        }

        $serviceCategories = ServiceCategory::active()->ordered()->get();
        $equipmentTypes = ProfileCenterEquipment::TYPE_TRANSLATIONS;
        
        return view('centers.create', compact('serviceCategories', 'equipmentTypes'));
    }

    /**
     * Store a newly created center.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Check if user already has a center
        $existingCenter = ProfileCentre::whereHas('profile', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->first();

        if ($existingCenter) {
            return redirect()->route('centers.edit', $existingCenter->id)
                ->with('error', 'You already have a center.');
        }

        $validated = $request->validate([
            // Center basic info
            'name' => 'required|string|max:255',
            'adresse' => 'required|string|max:500',
            'capacite' => 'required|integer|min:1',
            'price_per_night' => 'required|numeric|min:0',
            'category' => 'required|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'contact_email' => 'required|email',
            'contact_phone' => 'required|string|max:20',
            'manager_name' => 'nullable|string|max:255',
            'established_date' => 'nullable|date',
            'additional_services_description' => 'nullable|string',
            
            // Services
            'services' => 'nullable|array',
            'services.*.service_category_id' => 'required|exists:service_categories,id',
            'services.*.price' => 'required|numeric|min:0',
            'services.*.is_available' => 'boolean',
            'services.*.is_standard' => 'boolean',
            
            // Equipment
            'equipment' => 'nullable|array',
            'equipment.*.type' => 'required|string|in:' . implode(',', array_keys(ProfileCenterEquipment::TYPE_TRANSLATIONS)),
            'equipment.*.is_available' => 'boolean',
        ]);

        // Create profile for center
        $profile = Profile::create([
            'user_id' => $user->id,
            'bio' => $request->input('bio', ''),
            'type' => 'centre',
        ]);

        // Create center
        $profileCentre = ProfileCentre::create([
            'profile_id' => $profile->id,
            'name' => $validated['name'],
            'adresse' => $validated['adresse'],
            'capacite' => $validated['capacite'],
            'price_per_night' => $validated['price_per_night'],
            'category' => $validated['category'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'contact_email' => $validated['contact_email'],
            'contact_phone' => $validated['contact_phone'],
            'manager_name' => $validated['manager_name'] ?? null,
            'established_date' => $validated['established_date'] ?? null,
            'additional_services_description' => $validated['additional_services_description'] ?? null,
            'disponibilite' => true,
        ]);

        // Add services if provided
        if (!empty($validated['services'])) {
            $profileCentre->syncServices($validated['services']);
        }

        // Add equipment if provided
        if (!empty($validated['equipment'])) {
            foreach ($validated['equipment'] as $eq) {
                $profileCentre->addEquipment(
                    $eq['type'],
                    $eq['is_available'] ?? true
                );
            }
        }

        return redirect()->route('centers.show', $profileCentre->id)
            ->with('success', 'Center created successfully!');
    }

    /**
     * Display the specified center.
     */
    public function show(ProfileCentre $profileCentre)
    {
        $profileCentre->load([
            'profile.user',
            'availableServices',
            'availableEquipment'
        ]);
        
        // Get similar centers
        $similarCenters = ProfileCentre::where('category', $profileCentre->category)
            ->where('id', '!=', $profileCentre->id)
            ->where('disponibilite', true)
            ->with('availableServices')
            ->take(3)
            ->get();
            
        return view('centers.show', compact('profileCentre', 'similarCenters'));
    }

    /**
     * Show the form for editing the center.
     */
    public function edit(ProfileCentre $profileCentre)
    {
        // Authorization check - user must own this center
        $this->authorize('update', $profileCentre);
        
        $profileCentre->load(['services', 'equipment']);
        $serviceCategories = ServiceCategory::active()->ordered()->get();
        $equipmentTypes = ProfileCenterEquipment::TYPE_TRANSLATIONS;
        
        return view('centers.edit', compact('profileCentre', 'serviceCategories', 'equipmentTypes'));
    }

    /**
     * Update the specified center.
     */
    public function update(Request $request, ProfileCentre $profileCentre)
    {
        // Authorization check
        $this->authorize('update', $profileCentre);

        $validated = $request->validate([
            // Center basic info
            'name' => 'required|string|max:255',
            'adresse' => 'required|string|max:500',
            'capacite' => 'required|integer|min:1',
            'price_per_night' => 'required|numeric|min:0',
            'category' => 'required|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'contact_email' => 'required|email',
            'contact_phone' => 'required|string|max:20',
            'manager_name' => 'nullable|string|max:255',
            'established_date' => 'nullable|date',
            'additional_services_description' => 'nullable|string',
            'disponibilite' => 'boolean',
            
            // Profile bio
            'bio' => 'nullable|string',
        ]);

        // Update profile bio
        $profileCentre->profile->update([
            'bio' => $validated['bio'] ?? $profileCentre->profile->bio,
        ]);

        // Update center
        $profileCentre->update([
            'name' => $validated['name'],
            'adresse' => $validated['adresse'],
            'capacite' => $validated['capacite'],
            'price_per_night' => $validated['price_per_night'],
            'category' => $validated['category'],
            'latitude' => $validated['latitude'] ?? $profileCentre->latitude,
            'longitude' => $validated['longitude'] ?? $profileCentre->longitude,
            'contact_email' => $validated['contact_email'],
            'contact_phone' => $validated['contact_phone'],
            'manager_name' => $validated['manager_name'] ?? $profileCentre->manager_name,
            'established_date' => $validated['established_date'] ?? $profileCentre->established_date,
            'additional_services_description' => $validated['additional_services_description'] ?? $profileCentre->additional_services_description,
            'disponibilite' => $validated['disponibilite'] ?? $profileCentre->disponibilite,
        ]);

        return redirect()->route('centers.show', $profileCentre->id)
            ->with('success', 'Center updated successfully!');
    }

    /**
     * Remove the specified center.
     */
    public function destroy(ProfileCentre $profileCentre)
    {
        // Authorization check
        $this->authorize('delete', $profileCentre);

        $profileCentre->delete();
        
        // Also delete the associated profile
        $profileCentre->profile->delete();

        return redirect()->route('centers.index')
            ->with('success', 'Center deleted successfully!');
    }

    /**
     * Show center services management page
     */
    public function showServices(ProfileCentre $profileCentre)
    {
        $this->authorize('update', $profileCentre);
        
        $profileCentre->load(['services', 'equipment']);
        $serviceCategories = ServiceCategory::active()->ordered()->get();
        $equipmentTypes = ProfileCenterEquipment::TYPE_TRANSLATIONS;
        
        return view('centers.services', compact('profileCentre', 'serviceCategories', 'equipmentTypes'));
    }

    /**
     * Update center services
     */
    public function updateServices(Request $request, ProfileCentre $profileCentre)
    {
        $this->authorize('update', $profileCentre);

        $validated = $request->validate([
            'services' => 'nullable|array',
            'services.*.service_category_id' => 'required|exists:service_categories,id',
            'services.*.price' => 'required|numeric|min:0',
            'services.*.is_available' => 'boolean',
            'services.*.is_standard' => 'boolean',
            'services.*.min_quantity' => 'nullable|integer|min:1',
            'services.*.max_quantity' => 'nullable|integer|min:1',
            
            'equipment' => 'nullable|array',
            'equipment.*.type' => 'required|string|in:' . implode(',', array_keys(ProfileCenterEquipment::TYPE_TRANSLATIONS)),
            'equipment.*.is_available' => 'boolean',
            'equipment.*.notes' => 'nullable|string',
        ]);

        // Update services
        if (isset($validated['services'])) {
            $profileCentre->syncServices($validated['services']);
        }

        // Update equipment
        if (isset($validated['equipment'])) {
            // Delete existing equipment
            $profileCentre->equipment()->delete();
            
            // Create new equipment
            foreach ($validated['equipment'] as $eq) {
                $profileCentre->addEquipment(
                    $eq['type'],
                    $eq['is_available'] ?? true,
                    $eq['notes'] ?? null
                );
            }
        }

        return redirect()->route('centers.services', $profileCentre->id)
            ->with('success', 'Services and equipment updated successfully!');
    }

    /**
     * Toggle center availability
     */
    public function toggleAvailability(ProfileCentre $profileCentre)
    {
        $this->authorize('update', $profileCentre);

        $profileCentre->update([
            'disponibilite' => !$profileCentre->disponibilite
        ]);

        $status = $profileCentre->disponibilite ? 'available' : 'unavailable';
        
        return back()->with('success', "Center is now {$status} for bookings.");
    }

    /**
     * Search centers
     */
    public function search(Request $request)
    {
        $query = ProfileCentre::with(['availableServices', 'availableEquipment'])
            ->where('disponibilite', true);

        // Search by name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('adresse', 'LIKE', "%{$search}%")
                  ->orWhere('category', 'LIKE', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price_per_night', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price_per_night', '<=', $request->max_price);
        }

        // Filter by capacity
        if ($request->has('min_capacity')) {
            $query->where('capacite', '>=', $request->min_capacity);
        }

        // Filter by equipment
        if ($request->has('equipment')) {
            $query->whereHas('equipment', function ($q) use ($request) {
                $q->where('type', $request->equipment)
                  ->where('is_available', true);
            });
        }

        // Filter by service
        if ($request->has('service')) {
            $query->whereHas('services', function ($q) use ($request) {
                $q->where('name', 'LIKE', "%{$request->service}%")
                  ->where('profile_center_services.is_available', true);
            });
        }

        $centers = $query->orderBy('created_at', 'desc')->paginate(12);
        
        return view('centers.search', compact('centers'));
    }

    /**
     * Get user's center (dashboard)
     */
    public function myCenter()
    {
        $user = Auth::user();
        
        $center = ProfileCentre::whereHas('profile', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->first();

        if (!$center) {
            return redirect()->route('centers.create')
                ->with('info', 'You need to create a center first.');
        }

        $center->load(['services', 'equipment']);
        
        return view('centers.dashboard', compact('center'));
    }

    /**
     * Get centers by category
     */
    public function byCategory($category)
    {
        $centers = ProfileCentre::with(['availableServices', 'availableEquipment'])
            ->where('category', $category)
            ->where('disponibilite', true)
            ->orderBy('created_at', 'desc')
            ->paginate(12);
            
        return view('centers.category', compact('centers', 'category'));
    }
}