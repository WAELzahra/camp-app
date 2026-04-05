<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CampingZone;
use App\Models\CampingCentre;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class CampingZoneController extends Controller
{
    // ─── isAdmin ──────────────────────────────────────────────────────────────

    private function isAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if (!$user->relationLoaded('role')) $user->load('role');
        return strtolower($user->role->name ?? '') === 'admin';
    }

    // ─── format ───────────────────────────────────────────────────────────────
    /**
     * Sérialise une zone en tableau pur correspondant à l'interface
     * AdminZone côté TypeScript. Tous les champs sont toujours présents
     * (jamais de clé manquante) pour éviter les erreurs de rendu React.
     */
    private function format(CampingZone $zone): array
    {
        return [
            // ── Identité ──────────────────────────────────────────────────────
            'id'                => (int) $zone->id,
            'nom'               => (string) ($zone->nom ?? ''),
            'type_activitee'    => $zone->type_activitee ?? null,
            'description'       => $zone->description ?? null,
            'full_description'  => null,
            'adresse'           => $zone->adresse ?? null,
            'region'            => $zone->region ?? null,
            'commune'           => $zone->commune ?? null,

            // ── Niveau de risque / difficulté ─────────────────────────────────
            'danger_level'      => $zone->danger_level ?? 'low',
            'difficulty'        => null,

            // ── Booléens — toujours castés ────────────────────────────────────
            'status'            => (bool) $zone->status,
            'is_public'         => (bool) $zone->is_public,
            'is_closed'         => (bool) ($zone->is_closed ?? false),
            'is_protected_area' => (bool) ($zone->is_protected_area ?? false),

            // ── Fermeture ─────────────────────────────────────────────────────
            'closure_reason'    => $zone->closure_reason ?? null,
            'closure_start'     => $zone->closure_start
                                    ? (is_string($zone->closure_start)
                                        ? $zone->closure_start
                                        : $zone->closure_start->toDateString())
                                    : null,
            'closure_end'       => $zone->closure_end
                                    ? (is_string($zone->closure_end)
                                        ? $zone->closure_end
                                        : $zone->closure_end->toDateString())
                                    : null,

            // ── Géo ───────────────────────────────────────────────────────────
            'lat'               => $zone->lat !== null ? (float) $zone->lat : null,
            'lng'               => $zone->lng !== null ? (float) $zone->lng : null,

            // ── Capacité / terrain ────────────────────────────────────────────
            'max_capacity'      => $zone->max_capacity ? (int) $zone->max_capacity : null,
            'altitude'          => null,
            'access_type'       => $zone->access_type ?? null,

            // ── Tableaux JSON ─────────────────────────────────────────────────
            'activities'        => is_array($zone->activities)   ? $zone->activities   : [],
            'facilities'        => is_array($zone->facilities)   ? $zone->facilities   : [],
            'rules'             => [],
            'best_season'       => is_array($zone->opening_season) ? $zone->opening_season : [],

            // ── Contact ───────────────────────────────────────────────────────
            'contact_phone'     => null,
            'contact_email'     => null,
            'contact_website'   => null,

            // ── Meta ──────────────────────────────────────────────────────────
            'centre_id'         => $zone->centre_id ? (int) $zone->centre_id : null,
            'added_by'          => $zone->added_by ? (int) $zone->added_by : null,
            'source'            => $zone->source ?? null,
            'image'             => $zone->image ? asset('storage/' . $zone->image) : null,
            'created_at'        => $zone->created_at ? $zone->created_at->toISOString() : null,
            'updated_at'        => $zone->updated_at ? $zone->updated_at->toISOString() : null,

            // ── Relations ─────────────────────────────────────────────────────
            'centre' => $zone->relationLoaded('centre') && $zone->centre
                ? ['id' => (int) $zone->centre->id, 'nom' => (string) $zone->centre->nom]
                : null,

            'photos' => $zone->relationLoaded('photos')
                ? collect($zone->photos)->map(fn($p) => [
                    'id'          => (int) $p->id,
                    'url'         => asset('storage/' . $p->path_to_img),
                    'path_to_img' => $p->path_to_img,
                    'is_cover'    => (bool) $p->is_cover,
                    'order'       => (int) ($p->order ?? 0),
                  ])->values()->toArray()
                : [],
        ];
    }

    /**
     * Relations à eager-loader selon les tables disponibles.
     */
    private function relations(): array
    {
        $r = ['centre'];
        if (Schema::hasTable('photos')) $r[] = 'photos';
        return $r;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // GET /admin/zones
    // ═══════════════════════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        $query = CampingZone::with($this->relations());

        // ── Filtres ───────────────────────────────────────────────────────────
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('nom',         'like', "%{$s}%")
                  ->orWhere('adresse',   'like', "%{$s}%")
                  ->orWhere('region',    'like', "%{$s}%")
                  ->orWhere('commune',   'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status === 'active');
        }

        if ($request->filled('danger_level') && $request->danger_level !== 'all') {
            $query->where('danger_level', $request->danger_level);
        }

        if ($request->filled('is_public') && $request->is_public !== '') {
            $val = filter_var($request->is_public, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($val !== null) {
                $query->where('is_public', $val);
            }
        }

        $perPage   = max(1, min(100, (int) $request->get('per_page', 10)));
        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // ── FIX : construction manuelle de la réponse paginée ─────────────────
        // Laravel's setCollection() peut casser la sérialisation JSON quand
        // la collection contient des tableaux au lieu d'objets Eloquent.
        // On sérialise manuellement pour garantir la structure attendue par
        // le frontend : { success, data: { data: [], current_page, last_page, per_page, total } }
        return response()->json([
            'success' => true,
            'data'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => (int) $paginated->perPage(),
                'total'        => (int) $paginated->total(),
                'data'         => $paginated->getCollection()
                                    ->map(fn($zone) => $this->format($zone))
                                    ->values()
                                    ->all(),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // GET /admin/zones/stats
    // ═══════════════════════════════════════════════════════════════════════════

    public function stats()
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'total_zones'       => (int) CampingZone::count(),
                'zones_publiques'   => (int) CampingZone::where('is_public', true)->count(),
                'zones_privees'     => (int) CampingZone::where('is_public', false)->count(),
                'zones_danger_haut' => (int) CampingZone::whereIn('danger_level', ['high', 'extreme'])->count(),
                'zones_par_centre'  => (int) CampingZone::whereNotNull('centre_id')->count(),
                'zones_sans_centre' => (int) CampingZone::whereNull('centre_id')->count(),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // GET /admin/zones/{id}
    // ═══════════════════════════════════════════════════════════════════════════

    public function show($id)
    {
        $zone = CampingZone::with($this->relations())->find($id);

        if (!$zone) {
            return response()->json([
                'success' => false,
                'message' => "Zone #{$id} introuvable",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->format($zone),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // POST /admin/zones
    // ═══════════════════════════════════════════════════════════════════════════

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom'               => 'required|string|max:255',
            'type_activitee'    => 'nullable|string|max:100',
            'description'       => 'nullable|string',
            'adresse'           => 'nullable|string|max:255',
            'region'            => 'nullable|string|max:100',
            'commune'           => 'nullable|string|max:100',
            'danger_level'      => 'nullable|in:low,moderate,high,extreme',
            'lat'               => 'required|numeric|between:-90,90',
            'lng'               => 'required|numeric|between:-180,180',
            'max_capacity'      => 'nullable|integer|min:1',
            'access_type'       => 'nullable|string|max:100',
            'centre_id'         => 'nullable|exists:camping_centres,id',
            'activities'        => 'nullable|array',
            'activities.*'      => 'string',
            'facilities'        => 'nullable|array',
            'facilities.*'      => 'string',
            'is_protected_area' => 'nullable|boolean',
            'is_closed'         => 'nullable|boolean',
            'closure_reason'    => 'nullable|string',
            'closure_start'     => 'nullable|date',
            'closure_end'       => 'nullable|date|after_or_equal:closure_start',
        ]);

        if (empty($data['centre_id'])) {
            $centre = CampingCentre::create([
                'nom'    => ($data['nom']) . ' Centre',
                'adresse'=> $data['adresse'] ?? null,
                'lat'    => $data['lat'],
                'lng'    => $data['lng'],
                'type'   => 'hors_centre',
            ]);
            $data['centre_id'] = $centre->id;
        }

        $data['status']    = true;
        $data['is_public'] = false;
        $data['source']    = 'interne';
        $data['added_by']  = auth()->id();

        $zone = CampingZone::create($data);
        $zone->load('centre');

        return response()->json([
            'success' => true,
            'message' => 'Zone créée avec succès',
            'data'    => $this->format($zone),
        ], 201);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PUT /admin/zones/{id}
    // ═══════════════════════════════════════════════════════════════════════════

    public function update(Request $request, $id)
    {
        $zone = CampingZone::find($id);
        if (!$zone) {
            return response()->json(['success' => false, 'message' => 'Zone introuvable'], 404);
        }

        $user    = auth()->user();
        $user->load('role');
        $isAdmin = strtolower($user->role->name ?? '') === 'admin';

        if (!$isAdmin && ($zone->added_by !== $user->id || $zone->status === true)) {
            return response()->json(['success' => false, 'message' => 'Modification non autorisée'], 403);
        }

        $data = $request->validate([
            'nom'               => 'sometimes|string|max:255',
            'type_activitee'    => 'nullable|string|max:100',
            'description'       => 'nullable|string',
            'adresse'           => 'nullable|string|max:255',
            'region'            => 'nullable|string|max:100',
            'commune'           => 'nullable|string|max:100',
            'danger_level'      => 'nullable|in:low,moderate,high,extreme',
            'lat'               => 'nullable|numeric|between:-90,90',
            'lng'               => 'nullable|numeric|between:-180,180',
            'max_capacity'      => 'nullable|integer|min:1',
            'access_type'       => 'nullable|string|max:100',
            'centre_id'         => 'nullable|exists:camping_centres,id',
            'activities'        => 'nullable|array',
            'activities.*'      => 'string',
            'facilities'        => 'nullable|array',
            'facilities.*'      => 'string',
            'is_public'         => 'nullable|boolean',
            'status'            => 'nullable|boolean',
            'is_protected_area' => 'nullable|boolean',
            'is_closed'         => 'nullable|boolean',
            'closure_reason'    => 'nullable|string',
            'closure_start'     => 'nullable|date',
            'closure_end'       => 'nullable|date|after_or_equal:closure_start',
            'image'             => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($zone->image) Storage::disk('public')->delete($zone->image);
            $data['image'] = $request->file('image')->store('zones', 'public');
        }

        if (!$isAdmin) {
            $data['status']    = false;
            $data['is_public'] = false;
        }

        $zone->update($data);
        $zone->load('centre');

        return response()->json([
            'success' => true,
            'message' => 'Zone mise à jour avec succès',
            'data'    => $this->format($zone),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DELETE /admin/zones/{id}
    // ═══════════════════════════════════════════════════════════════════════════

    public function destroy($id)
    {
        $zone = CampingZone::find($id);
        if (!$zone) {
            return response()->json(['success' => false, 'message' => 'Zone introuvable'], 404);
        }

        $user    = auth()->user();
        $user->load('role');
        $isAdmin = strtolower($user->role->name ?? '') === 'admin';

        if (!$isAdmin) {
            if ($zone->added_by !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }
            $zone->update(['status' => false]);
            return response()->json(['success' => true, 'message' => 'Zone désactivée']);
        }

        if ($zone->image) Storage::disk('public')->delete($zone->image);
        $zone->delete();

        return response()->json(['success' => true, 'message' => 'Zone supprimée définitivement']);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PATCH /admin/zones/{id}/validate
    // ═══════════════════════════════════════════════════════════════════════════

    public function validateZone($id)
    {
        if (!$this->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Accès refusé'], 403);
        }

        $zone = CampingZone::find($id);
        if (!$zone) {
            return response()->json(['success' => false, 'message' => 'Zone introuvable'], 404);
        }

        if ($zone->is_public) {
            return response()->json(['success' => false, 'message' => 'Zone déjà publique'], 400);
        }

        $zone->update(['is_public' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Zone validée et rendue publique',
            'data'    => $this->format($zone->fresh()),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PATCH /admin/zones/{id}/toggle-status
    // ═══════════════════════════════════════════════════════════════════════════

    public function toggleZoneStatus(Request $request, $id)
    {
        if (!$this->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Accès refusé'], 403);
        }

        $request->validate(['status' => 'required|boolean']);

        $zone = CampingZone::find($id);
        if (!$zone) {
            return response()->json(['success' => false, 'message' => 'Zone introuvable'], 404);
        }

        $zone->update(['status' => $request->boolean('status')]);
        $zone->refresh();

        return response()->json([
            'success' => true,
            'message' => $zone->status ? 'Zone activée' : 'Zone désactivée',
            'data'    => $this->format($zone),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // POST /admin/zones/merge
    // ═══════════════════════════════════════════════════════════════════════════

    public function merge(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Accès refusé'], 403);
        }

        $data = $request->validate([
            'primary_zone_id'   => 'required|exists:CampingZone,id',
            'secondary_zone_id' => 'required|exists:CampingZone,id|different:primary_zone_id',
        ]);

        $primary   = CampingZone::findOrFail($data['primary_zone_id']);
        $secondary = CampingZone::findOrFail($data['secondary_zone_id']);

        foreach ((new CampingZone())->getFillable() as $key) {
            if (in_array($key, ['id', 'created_at', 'updated_at'])) continue;
            if (empty($primary->$key) && !empty($secondary->$key)) {
                $primary->$key = $secondary->$key;
            }
        }

        $primary->save();
        $secondary->delete();
        $primary->load('centre');

        return response()->json([
            'success' => true,
            'message' => 'Zones fusionnées avec succès',
            'data'    => $this->format($primary),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // POST /admin/zones/bulk-assign
    // ═══════════════════════════════════════════════════════════════════════════

    public function bulkAssignToCentre(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Accès refusé'], 403);
        }

        $validated = $request->validate([
            'zone_ids'   => 'required|array|min:1',
            'zone_ids.*' => 'integer|exists:CampingZone,id',
            'centre_id'  => 'required|exists:camping_centres,id',
        ]);

        $count = CampingZone::whereIn('id', $validated['zone_ids'])
            ->update(['centre_id' => $validated['centre_id']]);

        return response()->json([
            'success' => true,
            'message' => "{$count} zone(s) associée(s) au centre",
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // POST /admin/zones/import-geojson  (non implémenté)
    // ═══════════════════════════════════════════════════════════════════════════

    public function importGeoJson(Request $request)
    {
        if (!$this->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Accès refusé'], 403);
        }

        return response()->json(['success' => false, 'message' => 'Non implémenté'], 501);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // POST /admin/zones/{id}/adjust-polygon  (non implémenté)
    // ═══════════════════════════════════════════════════════════════════════════

    public function adjustPolygonWithRoutes(Request $request, $id)
    {
        if (!$this->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Accès refusé'], 403);
        }

        $zone = CampingZone::find($id);
        if (!$zone) {
            return response()->json(['success' => false, 'message' => 'Zone introuvable'], 404);
        }

        return response()->json([
            'success' => false,
            'message' => 'Non implémenté',
            'data'    => $this->format($zone),
        ], 501);
    }
}