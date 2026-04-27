<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\CampingCentre;
use App\Models\Feedbacks;
use App\Models\Photo;
use App\Models\ProfileCentre;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;

class CenterServiceApiController extends Controller
{
    /* ──────────────────────────────────────────────────────────────────
     * Helpers
     * ────────────────────────────────────────────────────────────────── */

    private function photoUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
        return url('storage/' . $path);
    }

    /**
     * Build a flat, frontend-ready representation of a center.
     *
     * @param  ProfileCentre  $center
     * @param  array|null     $albumMap   pre-loaded map of userId → Album (with coverPhoto/photos)
     * @param  array|null     $ratingMap  pre-loaded map of userId → [avg, count]
     * @param  bool           $withPhotos include full photos array (detail page only)
     */
    private function formatCenter(
        ProfileCentre $center,
        ?array $albumMap  = null,
        ?array $ratingMap = null,
        bool   $withPhotos = false
    ): array {
        $profile = $center->profile;
        $user    = $profile?->user;
        $userId  = $user?->id;

        /* ── Resolve user's linked CampingCentre (for photo fallbacks) ─── */
        $campingCentreId = null;
        if ($userId) {
            $campingCentreId = CampingCentre::where('user_id', $userId)->value('id');
        }

        /* ── Cover image ─────────────────────────────────────────────── */
        $coverImage = null;

        if ($profile?->cover_image) {
            $coverImage = $this->photoUrl($profile->cover_image);
        }

        if (!$coverImage && $userId) {
            $album = $albumMap[$userId] ?? Album::where('user_id', $userId)
                ->where('titre', 'Profile Gallery')
                ->with(['coverPhoto'])
                ->first();

            if ($album) {
                $cp = $album->coverPhoto;
                $coverImage = $this->photoUrl($cp?->path_to_img ?? $album->path_to_img);
            }
        }

        // Fallback: cover photo from the camping_centre photos (handles centers
        // approved before the album-migration fix, or where profile.cover_image
        // is still empty).
        if (!$coverImage && $campingCentreId) {
            $ccCover = Photo::where('camping_centre_id', $campingCentreId)
                ->where('is_cover', true)
                ->first()
                ?? Photo::where('camping_centre_id', $campingCentreId)->first();
            if ($ccCover) {
                $coverImage = $this->photoUrl($ccCover->path_to_img);
            }
        }

        /* ── All gallery photos ───────────────────────────────────────── */
        $photos = [];
        if ($withPhotos && $userId) {
            $album = $albumMap[$userId] ?? Album::where('user_id', $userId)
                ->where('titre', 'Profile Gallery')
                ->with(['photos'])
                ->first();

            $albumId = $album?->id;

            // Load photos from the Profile Gallery album AND any camping_centre
            // photos not yet migrated into the album (union via OR condition).
            $photoQuery = Photo::where('user_id', $userId)
                ->where(function ($q) use ($albumId, $campingCentreId) {
                    if ($albumId) {
                        $q->where('album_id', $albumId);
                    }
                    if ($campingCentreId) {
                        $q->orWhere('camping_centre_id', $campingCentreId);
                    }
                });

            $photos = $photoQuery->get()
                ->unique('id')
                ->sortByDesc('is_cover')
                ->values()
                ->map(fn($p) => [
                    'id'       => $p->id,
                    'url'      => $this->photoUrl($p->path_to_img),
                    'is_cover' => (bool) $p->is_cover,
                    'order'    => $p->order ?? 0,
                ])->toArray();
        }

        /* ── Ratings ─────────────────────────────────────────────────── */
        $avgRating   = null;
        $reviewCount = 0;

        if ($userId) {
            if ($ratingMap && isset($ratingMap[$userId])) {
                $avgRating   = $ratingMap[$userId]['avg'];
                $reviewCount = $ratingMap[$userId]['count'];
            } else {
                $q = Feedbacks::where('type', 'centre')
                    ->where('target_id', $userId)
                    ->where('status', 'approved');
                $avgRating   = $q->avg('note');
                $reviewCount = $q->count();
            }
        }

        /* ── Services ─────────────────────────────────────────────────── */
        $allServices = $center->centerServices()
            ->where('is_available', true)
            ->orderBy('is_standard', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        $services = $allServices->map(function($service) {
            // Custom service (no category)
            if (is_null($service->service_category_id)) {
                return [
                    'id'           => $service->id,
                    'name'         => $service->name,
                    'description'  => $service->description ?? '',
                    'price'        => (float) $service->price,
                    'unit'         => $service->unit ?? '',
                    'is_standard'  => (bool) $service->is_standard,
                    'is_available' => (bool) $service->is_available,
                    'nbr_place'    => $service->nbr_place, // ✅ Add this
                ];
            }
            
            // Category-based service
            return [
                'id'           => $service->id,
                'name'         => $service->name,
                'description'  => $service->description ?? '',
                'price'        => (float) $service->price,
                'unit'         => $service->unit ?? '',
                'is_standard'  => (bool) $service->is_standard,
                'is_available' => (bool) $service->is_available,
                'nbr_place'    => $service->nbr_place, // ✅ Add this
            ];
        })->values()->toArray();

        /* ── Equipment ───────────────────────────────────────────────── */
        $equipment = $center->availableEquipment->map(fn($eq) => [
            'id'           => $eq->id,
            'type'         => $eq->type,
            'is_available' => (bool) $eq->is_available,
            'notes'        => $eq->notes ?? null,
        ])->values()->toArray();

        /* ── Assemble ─────────────────────────────────────────────────── */
        $data = [
            'id'               => $center->id,
            'name'             => $center->name,
            'capacite'         => $center->capacite,
            'price_per_night'  => (float) $center->price_per_night,
            'category'         => $center->category,
            'disponibilite'    => (bool) $center->disponibilite,
            'latitude'         => $center->latitude  ? (string) $center->latitude  : null,
            'longitude'        => $center->longitude ? (string) $center->longitude : null,
            'contact_email'    => $center->contact_email,
            'contact_phone'    => $center->contact_phone,
            'manager_name'     => $center->manager_name,
            'established_date' => $center->established_date?->format('Y-m-d'),
            'average_rating'   => $avgRating  ? round((float) $avgRating, 2) : null,
            'review_count'     => $reviewCount,
            'profile' => [
                'bio'         => $profile?->bio,
                'city'        => $profile?->city,
                'address'     => $profile?->address,
                'cover_image' => $coverImage,
                'user'        => $user ? [
                    'id'         => $user->id,
                    'first_name' => $user->first_name,
                    'last_name'  => $user->last_name,
                    'avatar'     => $this->photoUrl($user->avatar),
                    'ville'      => $user->ville,
                ] : null,
            ],
            'available_services'  => $services,
            'available_equipment' => $equipment,
        ];

        if ($withPhotos) {
            $data['photos'] = $photos;
        }

        return $data;
    }

    /* ──────────────────────────────────────────────────────────────────
     * GET /centers/services  — list all available centers
     *
     * Returns both partner centres (profile_centres) and non-partner
     * centres (camping_centres with no user_id) that have admin status=true.
     * Partner centres whose linked camping_centre has status=false are hidden.
     * ────────────────────────────────────────────────────────────────── */
    public function centersWithServices(Request $request)
    {
        // Partner centres that admin has explicitly disabled (status=false)
        $disabledProfileIds = \App\Models\CampingCentre::whereNotNull('profile_centre_id')
            ->where('status', false)
            ->pluck('profile_centre_id')
            ->toArray();

        $query = ProfileCentre::with([
            'profile.user',
            'availableEquipment',  
        ])->available()
        ->whereNotIn('id', $disabledProfileIds)
        ->whereHas('profile.user', fn($q) => $q->where('is_active', 1));

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        if ($request->has('equipment')) {
            $query->withEquipment($request->equipment);
        }
        if ($request->has('service')) {
            $query->withService($request->service);
        }
        if ($request->has('min_price')) {
            $query->where('price_per_night', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price_per_night', '<=', $request->max_price);
        }

        $centers = $query->get();

        /* ── Bulk-load albums to avoid N+1 ────────────────────────────── */
        $userIds = $centers->map(fn($c) => $c->profile?->user?->id)->filter()->unique()->values()->toArray();

        $albumMap = [];
        if (!empty($userIds)) {
            Album::whereIn('user_id', $userIds)
                ->where('titre', 'Profile Gallery')
                ->with(['coverPhoto'])
                ->get()
                ->each(fn($album) => $albumMap[$album->user_id] = $album);
        }

        /* ── Bulk-load ratings ─────────────────────────────────────────── */
        $ratingMap = [];
        if (!empty($userIds)) {
            Feedbacks::where('type', 'centre')
                ->where('status', 'approved')
                ->whereIn('target_id', $userIds)
                ->selectRaw('target_id, AVG(note) as avg_note, COUNT(*) as review_count')
                ->groupBy('target_id')
                ->get()
                ->each(function ($row) use (&$ratingMap) {
                    $ratingMap[$row->target_id] = [
                        'avg'   => round((float) $row->avg_note, 2),
                        'count' => (int) $row->review_count,
                    ];
                });
        }

        // Partner centres with is_partner flag
        $partnerResult = $centers->map(function ($c) use ($albumMap, $ratingMap) {
            return array_merge(
                $this->formatCenter($c, $albumMap, $ratingMap, false),
                ['is_partner' => true, '_source' => 'partner']
            );
        })->values();

        // Non-partner centres: camping_centres with no user AND not linked to a profile_centre
        $nonPartnerResult = \App\Models\CampingCentre::whereNull('user_id')
            ->whereNull('profile_centre_id')
            ->where('status', true)
            ->with('coverPhoto')
            ->get()
            ->map(fn($c) => [
                'id'               => $c->id,
                'name'             => $c->nom,
                'capacite'         => 0,
                'price_per_night'  => 0,
                'category'         => 'Camping',
                'disponibilite'    => true,
                'latitude'         => $c->lat  ? (string) $c->lat  : null,
                'longitude'        => $c->lng  ? (string) $c->lng  : null,
                'contact_email'    => null,
                'contact_phone'    => null,
                'manager_name'     => null,
                'average_rating'   => null,
                'review_count'     => 0,
                'profile' => [
                    'bio'         => $c->description,
                    'city'        => null,
                    'address'     => $c->adresse,
                    'cover_image' => $c->image
                        ? url('storage/' . $c->image)
                        : ($c->coverPhoto ? url('storage/' . $c->coverPhoto->path_to_img) : null),
                    'user'        => null,
                ],
                'available_services'  => [],
                'available_equipment' => [],
                'is_partner'       => false,
                '_source'          => 'camping',
            ]);

        return response()->json($partnerResult->concat($nonPartnerResult)->values());
    }

    /* ──────────────────────────────────────────────────────────────────
     * GET /centers/{center}/services  — single center detail
     * ────────────────────────────────────────────────────────────────── */
    public function centerServices($centerId)
    {
        $center = ProfileCentre::with([
            'profile.user',
            'availableServices',
            'availableEquipment',
        ])->findOrFail($centerId);

        return response()->json(array_merge(
            $this->formatCenter($center, null, null, true),
            ['is_partner' => true, '_source' => 'partner']
        ));
    }

    /* ──────────────────────────────────────────────────────────────────
     * GET /centers/service-categories
     * ────────────────────────────────────────────────────────────────── */
    public function serviceCategories()
    {
        $categories = ServiceCategory::active()
            ->ordered()
            ->get()
            ->map(fn($cat) => [
                'id'              => $cat->id,
                'name'            => $cat->name,
                'description'     => $cat->description,
                'is_standard'     => $cat->is_standard,
                'suggested_price' => (float) $cat->suggested_price,
                'min_price'       => (float) $cat->min_price,
                'unit'            => $cat->unit,
                'icon'            => $cat->icon,
                'sort_order'      => $cat->sort_order,
                'is_active'       => $cat->is_active,
            ]);

        return response()->json($categories);
    }

    /* ──────────────────────────────────────────────────────────────────
     * POST /centers/calculate-price
     * ────────────────────────────────────────────────────────────────── */
    public function calculatePrice(Request $request)
    {
        $validated = $request->validate([
            'center_id'                    => 'required|exists:profile_centres,id',
            'nights'                       => 'required|integer|min:1',
            'people'                       => 'required|integer|min:1',
            'services'                     => 'array',
            'services.*.service_id'        => 'required|exists:service_categories,id',
            'services.*.quantity'          => 'required|integer|min:1',
        ]);

        $center = ProfileCentre::with('services')->findOrFail($validated['center_id']);

        $total     = 0;
        $breakdown = [];

        $standardService = $center->standardService();
        if ($standardService) {
            $sub = $standardService->pivot->price * $validated['nights'] * $validated['people'];
            $total += $sub;
            $breakdown[] = [
                'name'     => $standardService->name,
                'price'    => $standardService->pivot->price,
                'nights'   => $validated['nights'],
                'people'   => $validated['people'],
                'subtotal' => $sub,
            ];
        }

        foreach ($validated['services'] ?? [] as $req) {
            $svc = $center->services()
                ->where('service_categories.id', $req['service_id'])
                ->where('profile_center_services.is_available', true)
                ->first();

            if ($svc) {
                $sub = $svc->pivot->price * $req['quantity'];
                $total += $sub;
                $breakdown[] = [
                    'name'     => $svc->name,
                    'price'    => $svc->pivot->price,
                    'quantity' => $req['quantity'],
                    'unit'     => $svc->pivot->unit,
                    'subtotal' => $sub,
                ];
            }
        }

        return response()->json([
            'total'     => round($total, 2),
            'breakdown' => $breakdown,
            'currency'  => 'TND',
        ]);
    }
}
