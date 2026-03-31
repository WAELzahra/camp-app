<?php

namespace App\Http\Controllers\zonecamping;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CampingZone;
use App\Models\CampingCentre;
use App\Models\Photo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CampingZonesController extends Controller
{
    // =========================================================================
    // READ
    // =========================================================================

    /**
     * Paginated list of zones with optional filters.
     * Admins see all zones; public sees only open/public ones.
     */
    public function index(Request $request)
    {
        $user  = Auth::user();
        $isAdmin = $user && $user->role && $user->role->name === 'admin';

        $query = CampingZone::with(['coverPhoto', 'centre']);

        // Non-admins only see open, public, non-closed zones
        if (!$isAdmin) {
            $query->where('status', true)
                  ->where('is_public', true)
                  ->where('is_closed', false);
        }

        // Filters
        if ($request->filled('region'))     $query->where('region', $request->region);
        if ($request->filled('city'))       $query->where('city', $request->city);
        if ($request->filled('terrain'))    $query->where('terrain', 'LIKE', "%{$request->terrain}%");
        if ($request->filled('difficulty')) $query->where('difficulty', $request->difficulty);
        if ($request->filled('danger_level')) $query->where('danger_level', $request->danger_level);
        if ($request->filled('access_type'))  $query->where('access_type', $request->access_type);
        if ($request->filled('is_protected_area')) {
            $query->where('is_protected_area', (bool) $request->is_protected_area);
        }
        if ($request->filled('activity')) {
            $query->whereJsonContains('activities', $request->activity);
        }
        if ($request->filled('season')) {
            $query->whereJsonContains('best_season', $request->season);
        }

        // Sorting
        $sortBy  = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $allowed = ['created_at', 'rating', 'reviews_count', 'nom', 'difficulty'];
        if (in_array($sortBy, $allowed)) {
            $query->orderBy($sortBy, $sortDir);
        }

        $zones = $query->paginate($request->get('per_page', 10));

        return response()->json($zones);
    }

    /**
     * Full details of a single zone.
     */
    public function show($id)
    {
        $zone = CampingZone::with([
            'photos',
            'coverPhoto',
            'centre.profileCentre',
            'feedbacks.user',
        ])->findOrFail($id);

        $user    = Auth::user();
        $isAdmin = $user && $user->role && $user->role->name === 'admin';

        if (!$isAdmin && (!$zone->status || $zone->is_closed)) {
            return response()->json(['message' => 'This zone is not available.'], 403);
        }

        return response()->json($this->formatZone($zone, detailed: true));
    }

    // =========================================================================
    // CREATE / UPDATE
    // =========================================================================

    /**
     * Suggest a new zone (any authenticated user).
     * Admins get it approved immediately; others go to pending.
     */
    public function suggestZone(Request $request)
    {
        $validated = $request->validate([
            'nom'              => 'required|string|max:255',
            'city'             => 'nullable|string|max:255',
            'region'           => 'nullable|string|max:255',
            'commune'          => 'nullable|string|max:255',
            'description'      => 'nullable|string',
            'full_description' => 'nullable|string',
            'terrain'          => 'nullable|string|max:255',
            'difficulty'       => 'nullable|in:easy,medium,hard',
            'lat'              => 'required|numeric|between:-90,90',
            'lng'              => 'required|numeric|between:-180,180',
            'adresse'          => 'nullable|string|max:500',
            'distance'         => 'nullable|string|max:100',
            'altitude'         => 'nullable|string|max:100',
            'access_type'      => 'nullable|in:road,trail,boat,mixed',
            'accessibility'    => 'nullable|string|max:255',
            'best_season'      => 'nullable|array',
            'activities'       => 'nullable|array',
            'facilities'       => 'nullable|array',
            'rules'            => 'nullable|array',
            'contact_phone'    => 'nullable|string|max:50',
            'contact_email'    => 'nullable|email|max:255',
            'contact_website'  => 'nullable|url|max:255',
            'max_capacity'     => 'nullable|integer|min:1',
            'centre_id'        => 'nullable|exists:camping_centres,id',
            'is_protected_area'=> 'nullable|boolean',
            'photos'           => 'nullable|array', 
            'photos.*'         => 'string|max:255', 
        ]);

        $user = Auth::user();
        $isAdmin = $user && $user->role && $user->role->name === 'admin';

        $validated['added_by'] = $user->id;
        // Set status as 0 (pending) for all users, regardless of admin status
        $validated['status']   = 0; // 0 = pending, 1 = approved
        $validated['source']   = $isAdmin ? 'admin' : 'utilisateur';

        // Create the camping zone first
        $zone = CampingZone::create($validated);

        // Handle photos if provided
        if ($request->has('photos') && is_array($request->photos)) {
            foreach ($request->photos as $index => $photoPath) {
                // Determine if this should be the cover photo (first photo)
                $isCover = ($index === 0);
                
                Photo::create([
                    'path_to_img'      => $photoPath,
                    'user_id'          => $user->id,
                    'camping_zone_id'  => $zone->id, // Store the camping zone ID
                    'is_cover'         => $isCover,
                    'order'            => $index,
                ]);
            }
        }

        return response()->json([
            'message' => 'Zone submitted successfully and is pending admin approval.',
            'zone'    => $zone,
            'status'  => 'pending', // Add status info in response
        ], 201);
    }
    /**
     * Admin: update any field on a zone.
     */
    public function update(Request $request, $id)
    {
        $zone = CampingZone::findOrFail($id);

        $validated = $request->validate([
            'nom'              => 'sometimes|string|max:255',
            'city'             => 'sometimes|nullable|string|max:255',
            'region'           => 'sometimes|nullable|string|max:255',
            'commune'          => 'sometimes|nullable|string|max:255',
            'description'      => 'sometimes|nullable|string',
            'full_description' => 'sometimes|nullable|string',
            'terrain'          => 'sometimes|nullable|string|max:255',
            'difficulty'       => 'sometimes|in:easy,medium,hard',
            'lat'              => 'sometimes|numeric|between:-90,90',
            'lng'              => 'sometimes|numeric|between:-180,180',
            'adresse'          => 'sometimes|nullable|string|max:500',
            'distance'         => 'sometimes|nullable|string|max:100',
            'altitude'         => 'sometimes|nullable|string|max:100',
            'access_type'      => 'sometimes|nullable|in:road,trail,boat,mixed',
            'accessibility'    => 'sometimes|nullable|string|max:255',
            'best_season'      => 'sometimes|nullable|array',
            'activities'       => 'sometimes|nullable|array',
            'facilities'       => 'sometimes|nullable|array',
            'rules'            => 'sometimes|nullable|array',
            'contact_phone'    => 'sometimes|nullable|string|max:50',
            'contact_email'    => 'sometimes|nullable|email|max:255',
            'contact_website'  => 'sometimes|nullable|url|max:255',
            'max_capacity'     => 'sometimes|nullable|integer|min:1',
            'danger_level'     => 'sometimes|in:low,moderate,high,extreme',
            'is_public'        => 'sometimes|boolean',
            'status'           => 'sometimes|boolean',
            'is_protected_area'=> 'sometimes|boolean',
            'centre_id'        => 'sometimes|nullable|exists:camping_centres,id',
        ]);

        $zone->update($validated);

        return response()->json(['message' => 'Zone updated.', 'zone' => $zone]);
    }

    /**
     * Admin: delete a zone.
     */
    public function destroy($id)
    {
        $zone = CampingZone::findOrFail($id);
        $zone->delete();
        return response()->json(['message' => 'Zone deleted.']);
    }

    // =========================================================================
    // ADMIN ACTIONS
    // =========================================================================

    /**
     * Admin: approve or reject a pending zone.
     */
    public function validateZone(Request $request, $id)
    {
        $zone = CampingZone::findOrFail($id);
        $request->validate(['status' => 'required|boolean']);
        $zone->update(['status' => $request->status]);
        $msg = $request->status ? 'Zone approved.' : 'Zone rejected.';
        return response()->json(['message' => $msg, 'zone' => $zone]);
    }

    /**
     * Admin: toggle open/closed status.
     */
    public function toggleZoneStatus($id)
    {
        $zone = CampingZone::findOrFail($id);
        $zone->update(['status' => !$zone->status]);
        return response()->json([
            'message' => $zone->status ? 'Zone opened.' : 'Zone closed.',
            'zone'    => $zone,
        ]);
    }

    /**
     * Mark a zone for review (temporarily close it).
     */
    public function markForReview($id)
    {
        $zone = CampingZone::findOrFail($id);
        $zone->update(['status' => false]);
        return response()->json(['message' => 'Zone marked for review.']);
    }

    // =========================================================================
    // GALLERY
    // =========================================================================

    /**
     * Upload images to a zone's gallery (via photos table).
     */
    public function addGallery(Request $request, $id)
    {
        $zone = CampingZone::findOrFail($id);
        $request->validate([
            'images'   => 'required|array',
            'images.*' => 'required|image|max:4096',
        ]);

        $uploaded = [];
        $order    = $zone->photos()->max('order') ?? 0;
        $hasCover = $zone->photos()->where('is_cover', true)->exists();

        foreach ($request->file('images') as $img) {
            $path = $img->store('zones/gallery', 'public');
            $order++;

            $photo = Photo::create([
                'path_to_img'     => $path,
                'camping_zone_id' => $zone->id,
                'is_cover'        => !$hasCover, // first upload becomes cover
                'order'           => $order,
            ]);

            $hasCover = true;
            $uploaded[] = $photo;
        }

        return response()->json(['message' => count($uploaded) . ' image(s) uploaded.', 'photos' => $uploaded]);
    }

    // =========================================================================
    // SEARCH & DISCOVERY
    // =========================================================================

    /**
     * Full-text search across name, city, region, description.
     */
    public function search(Request $request)
    {
        $query = CampingZone::with(['coverPhoto'])
            ->where('status', true)
            ->where('is_public', true)
            ->where('is_closed', false);

        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($q) use ($term) {
                $q->where('nom',         'LIKE', "%{$term}%")
                  ->orWhere('city',       'LIKE', "%{$term}%")
                  ->orWhere('region',     'LIKE', "%{$term}%")
                  ->orWhere('description','LIKE', "%{$term}%");
            });
        }

        if ($request->filled('difficulty')) $query->where('difficulty', $request->difficulty);
        if ($request->filled('terrain'))    $query->where('terrain', 'LIKE', "%{$request->terrain}%");
        if ($request->filled('region'))     $query->where('region', $request->region);
        if ($request->filled('danger_level')) $query->where('danger_level', $request->danger_level);

        return response()->json($query->paginate(10));
    }

    /**
     * Zones grouped by region.
     */
    public function zonesByRegion(Request $request)
    {
        $request->validate(['region' => 'required|string']);

        $zones = CampingZone::with('coverPhoto')
            ->where('region', $request->region)
            ->where('status', true)
            ->where('is_closed', false)
            ->get();

        return response()->json(['region' => $request->region, 'count' => $zones->count(), 'zones' => $zones]);
    }

    /**
     * Zones near a GPS point within a given radius (km).
     */
    public function nearby(Request $request)
    {
        $request->validate([
            'lat'    => 'required|numeric',
            'lng'    => 'required|numeric',
            'radius' => 'nullable|numeric|min:1|max:500',
        ]);

        $radius = $request->radius ?? 10;

        $zones = CampingZone::with('coverPhoto')
            ->where('status', true)
            ->where('is_public', true)
            ->where('is_closed', false)
            ->selectRaw('*, (6371 * acos(
                cos(radians(?)) * cos(radians(lat)) *
                cos(radians(lng) - radians(?)) +
                sin(radians(?)) * sin(radians(lat))
            )) AS distance_km', [$request->lat, $request->lng, $request->lat])
            ->having('distance_km', '<=', $radius)
            ->orderBy('distance_km')
            ->get();

        return response()->json(['radius_km' => $radius, 'count' => $zones->count(), 'zones' => $zones]);
    }

    /**
     * Top-rated zones.
     */
    public function topZones(Request $request)
    {
        $zones = CampingZone::with('coverPhoto')
            ->where('status', true)
            ->where('is_public', true)
            ->where('is_closed', false)
            ->orderByDesc('rating')
            ->orderByDesc('reviews_count')
            ->limit($request->get('limit', 10))
            ->get();

        return response()->json($zones);
    }

    /**
     * Top zones by current or given season.
     */
    public function topZonesBySeason(Request $request)
    {
        $season = $request->get('season', $this->getCurrentSeason());

        $zones = CampingZone::with('coverPhoto')
            ->where('status', true)
            ->where('is_public', true)
            ->where('is_closed', false)
            ->whereJsonContains('best_season', ucfirst($season))
            ->orderByDesc('rating')
            ->limit(10)
            ->get();

        return response()->json(['season' => $season, 'zones' => $zones]);
    }

    /**
     * Exclude zones that are closed, dangerous, or not public.
     */
    public function excludeNonRelevantZones()
    {
        $zones = CampingZone::with('coverPhoto')
            ->where('status', true)
            ->where('is_public', true)
            ->where('is_closed', false)
            ->whereNotIn('danger_level', ['high', 'extreme'])
            ->orderByDesc('rating')
            ->get();

        return response()->json($zones);
    }

    /**
     * Personalized recommendations for a user (based on activities).
     */
    public function personalizedRecommendations($userId)
    {
        // Fall back to top-rated if no user preferences available
        $zones = CampingZone::with('coverPhoto')
            ->where('status', true)
            ->where('is_public', true)
            ->where('is_closed', false)
            ->orderByDesc('rating')
            ->limit(10)
            ->get();

        return response()->json($zones);
    }

    /**
     * Recommended zones (alias for public consumption).
     */
    public function recommendedZones($userId)
    {
        return $this->personalizedRecommendations($userId);
    }

    /**
     * General recommend endpoint.
     */
    public function recommendZones(Request $request)
    {
        $season = $this->getCurrentSeason();

        $zones = CampingZone::with('coverPhoto')
            ->where('status', true)
            ->where('is_public', true)
            ->where('is_closed', false)
            ->whereJsonContains('best_season', ucfirst($season))
            ->whereNotIn('danger_level', ['high', 'extreme'])
            ->orderByDesc('rating')
            ->limit(6)
            ->get();

        return response()->json(['season' => $season, 'zones' => $zones]);
    }

    // =========================================================================
    // STATS
    // =========================================================================

    /**
     * Stats for a single zone.
     */
    public function zoneStats($id)
    {
        $zone = CampingZone::with(['feedbacks', 'photos'])->findOrFail($id);

        return response()->json([
            'zone_id'        => $zone->id,
            'name'           => $zone->nom,
            'rating'         => $zone->rating,
            'reviews_count'  => $zone->reviews_count,
            'photos_count'   => $zone->photos->count(),
            'activities'     => $zone->activities ?? [],
            'facilities'     => $zone->facilities ?? [],
            'is_open'        => $zone->status && !$zone->is_closed,
            'difficulty'     => $zone->difficulty,
            'danger_level'   => $zone->danger_level,
        ]);
    }

    // =========================================================================
    // GEO / MAP
    // =========================================================================

    /**
     * Export all open zones as GeoJSON FeatureCollection.
     */
    public function exportGeoJson()
    {
        $zones    = CampingZone::where('status', true)->where('is_public', true)->get();
        $features = [];

        foreach ($zones as $zone) {
            if ($zone->lat && $zone->lng) {
                $features[] = [
                    'type'       => 'Feature',
                    'geometry'   => [
                        'type'        => 'Point',
                        'coordinates' => [(float) $zone->lng, (float) $zone->lat],
                    ],
                    'properties' => [
                        'id'         => $zone->id,
                        'name'       => $zone->nom,
                        'city'       => $zone->city,
                        'region'     => $zone->region,
                        'difficulty' => $zone->difficulty,
                        'rating'     => $zone->rating,
                        'terrain'    => $zone->terrain,
                        'centre_id'  => $zone->centre_id,
                    ],
                ];
            }

            // Polygon if stored
            if ($zone->polygon_coordinates) {
                $features[] = [
                    'type'       => 'Feature',
                    'geometry'   => [
                        'type'        => 'Polygon',
                        'coordinates' => $zone->polygon_coordinates,
                    ],
                    'properties' => ['id' => $zone->id, 'name' => $zone->nom],
                ];
            }
        }

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }

    /**
     * Validate that a zone's coordinates are within valid GPS bounds.
     */
    public function validateCoordinates($id)
    {
        $zone    = CampingZone::findOrFail($id);
        $isValid = $zone->lat >= -90 && $zone->lat <= 90
                && $zone->lng >= -180 && $zone->lng <= 180;

        return response()->json([
            'zone'             => $zone->nom,
            'lat'              => $zone->lat,
            'lng'              => $zone->lng,
            'valid_coordinates'=> $isValid,
        ]);
    }

    /**
     * Cluster nearby zones by proximity radius.
     */
    public function clusterZones(Request $request)
    {
        $radius  = $request->get('radius', 5);
        $zones   = CampingZone::where('status', true)->whereNotNull('lat')->whereNotNull('lng')->get();
        $clusters = [];

        foreach ($zones as $zone) {
            $added = false;
            foreach ($clusters as &$cluster) {
                foreach ($cluster['zones'] as $z) {
                    if ($this->haversine($zone->lat, $zone->lng, $z->lat, $z->lng) <= $radius) {
                        $cluster['zones'][]  = $zone;
                        $cluster['count']++;
                        $added = true;
                        break 2;
                    }
                }
            }
            unset($cluster);
            if (!$added) {
                $clusters[] = [
                    'center' => ['lat' => $zone->lat, 'lng' => $zone->lng],
                    'count'  => 1,
                    'zones'  => [$zone],
                ];
            }
        }

        return response()->json(['radius_km' => $radius, 'clusters' => $clusters]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function haversine($lat1, $lon1, $lat2, $lon2): float
    {
        $r    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function getCurrentSeason(): string
    {
        $m = (int) date('n');
        if ($m >= 3 && $m <= 5)  return 'Spring';
        if ($m >= 6 && $m <= 8)  return 'Summer';
        if ($m >= 9 && $m <= 11) return 'Autumn';
        return 'Winter';
    }

    /**
     * Shape a zone for API responses.
     */
    private function formatZone(CampingZone $zone, bool $detailed = false): array
    {
        $data = [
            'id'             => $zone->id,
            'name'           => $zone->nom,
            'city'           => $zone->city,
            'region'         => $zone->region,
            'description'    => $zone->description,
            'terrain'        => $zone->terrain,
            'difficulty'     => $zone->difficulty,
            'rating'         => $zone->rating,
            'reviews'        => $zone->reviews_count,
            'accessibility'  => $zone->accessibility,
            'best_season'    => $zone->best_season ?? [],
            'activities'     => $zone->activities  ?? [],
            'facilities'     => $zone->facilities  ?? [],
            'distance'       => $zone->distance,
            'altitude'       => $zone->altitude,
            'coordinates'    => ['lat' => (float) $zone->lat, 'lng' => (float) $zone->lng],
            'contact'        => array_filter([
                'phone'   => $zone->contact_phone,
                'email'   => $zone->contact_email,
                'website' => $zone->contact_website,
            ]),
            'cover_image'    => $zone->coverPhoto?->path_to_img,
            'images'         => $zone->photos?->pluck('path_to_img') ?? [],
            'status'         => $zone->status,
            'is_closed'      => $zone->is_closed,
        ];

        if ($detailed) {
            $data['full_description'] = $zone->full_description;
            $data['rules']            = $zone->rules ?? [];
            $data['danger_level']     = $zone->danger_level;
            $data['max_capacity']     = $zone->max_capacity;
            $data['is_protected']     = $zone->is_protected_area;
            $data['emergency_contacts'] = $zone->emergency_contacts ?? [];
            $data['feedbacks']        = $zone->feedbacks?->map(fn($f) => [
                'id'         => $f->id,
                'user_name'  => $f->user->first_name . ' ' . $f->user->last_name,
                'comment'    => $f->comment,
                'rating'     => $f->rating,
                'created_at' => $f->created_at,
            ]);

            if ($zone->centre) {
                $profil = $zone->centre->profileCentre;
                $data['centre'] = [
                    'id'               => $zone->centre->id,
                    'nom'              => $zone->centre->nom,
                    'description'      => $zone->centre->description,
                    'adresse'          => $zone->centre->adresse,
                    'lat'              => $zone->centre->lat ?? $zone->lat,
                    'lng'              => $zone->centre->lng ?? $zone->lng,
                    'capacite'         => $profil->capacite ?? null,
                    'disponibilite'    => $profil->disponibilite ?? null,
                    'is_registered'    => method_exists($zone->centre, 'isRegistered')
                                         ? $zone->centre->isRegistered()
                                         : true,
                ];
            }
        }

        return $data;
    }
}