<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Annonce;
use App\Models\AnnonceLike;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminAnnonceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin,moderator');
    }

    /**
     * Liste paginée avec filtres
     */
    public function index(Request $request)
    {
        $query = Annonce::with(['user', 'photos']);

        // ── FIX 1 : le filtre "published" côté frontend correspond à status='approved'
        //            + le filtre "archived" est encapsulé pour ne pas polluer les autres clauses
        if ($request->filled('status') && $request->status !== 'all') {
            switch ($request->status) {
                case 'published':
                    $query->where('status', 'approved')
                          ->where('is_archived', false);
                    break;
                case 'pending':
                    $query->where('status', 'pending');
                    break;
                case 'rejected':
                    $query->where('status', 'rejected');
                    break;
                case 'archived':
                    // FIX 2 : envelopper le orWhere pour ne pas contaminer les autres filtres
                    $query->where(function ($q) {
                        $q->where('is_archived', true)
                          ->orWhere(function ($q2) {
                              $q2->where('auto_archive', true)
                                 ->whereNotNull('end_date')
                                 ->where('end_date', '<', now());
                          });
                    });
                    break;
            }
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->filled('role') && $request->role !== 'all') {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('role', $request->role);
            });
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('start_date')) {
            $query->where('start_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('end_date', '<=', $request->end_date);
        }

        $perPage  = $request->get('per_page', 15);
        $annonces = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $formattedAnnonces = $annonces->getCollection()->map(fn ($a) => $this->formatForFrontend($a));
        $annonces->setCollection($formattedAnnonces);

        return response()->json([
            'success' => true,
            'data'    => $annonces,
            'stats'   => $this->getAdminStats(),
            'filters' => $request->all(),
        ]);
    }

    /**
     * Créer une annonce
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'type'        => 'required|in:School Camp,Summer Camp',
            'start_date'  => 'required|date|after_or_equal:today',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'auto_archive'=> 'boolean',
            'activities'  => 'nullable|array',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
            'address'     => 'nullable|string',
            'status'      => 'nullable|in:pending,approved,rejected',
            'user_id'     => 'nullable|exists:users,id',
            'photos'      => 'nullable|array',
            'photos.*'    => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $userId = $request->user_id ?? Auth::id();
            $status = $request->status ?? 'pending';

            if ($status === 'approved' && Auth::user()->role === 'admin') {
                $status = 'approved';
            }

            $annonce = Annonce::create([
                'user_id'     => $userId,
                'title'       => $request->title,
                'description' => $request->description,
                'type'        => $request->type,
                'start_date'  => $request->start_date,
                'end_date'    => $request->end_date,
                'auto_archive'=> $request->auto_archive ?? true,
                'activities'  => $request->activities,
                'latitude'    => $request->latitude,
                'longitude'   => $request->longitude,
                'address'     => $request->address,
                'status'      => $status,
                'approved_by' => $status === 'approved' ? Auth::id() : null,
                'approved_at' => $status === 'approved' ? now()       : null,
                'is_archived' => false,
            ]);

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $index => $photo) {
                    $path = $photo->store('annonces', 'public');
                    $annonce->photos()->create([
                        'path_to_img' => $path,
                        'user_id'     => Auth::id(),
                        'annonce_id'  => $annonce->id,
                        'is_cover'    => $index === 0,
                        'order'       => $index,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Annonce créée avec succès',
                'data'    => $this->formatForFrontend($annonce->load('photos', 'user')),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Erreur lors de la création', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Détail d'une annonce
     */
    public function show($id)
    {
        $annonce = Annonce::with(['user', 'photos', 'likes.user'])->findOrFail($id);

        if (request()->has('increment_view')) {
            $annonce->incrementViews();
        }

        $formattedAnnonce = $this->formatForFrontend($annonce);
        $formattedAnnonce['admin_details'] = [
            'user_email'         => $annonce->user->email,
            'user_phone'         => $annonce->user->phone ?? null,
            'moderation_history' => $this->getModerationHistory($annonce),
        ];

        return response()->json(['success' => true, 'data' => $formattedAnnonce]);
    }

    /**
     * Mettre à jour une annonce
     */
    public function update(Request $request, $id)
    {
        $annonce = Annonce::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title'       => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'type'        => 'sometimes|in:School Camp,Summer Camp',
            'start_date'  => 'sometimes|date',
            'end_date'    => 'sometimes|date|after_or_equal:start_date',
            'auto_archive'=> 'boolean',
            'activities'  => 'nullable|array',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
            'address'     => 'nullable|string',
            'status'      => 'sometimes|in:pending,approved,rejected,archived',
            'user_id'     => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $updateData = $request->only([
                'title', 'description', 'type', 'start_date', 'end_date',
                'auto_archive', 'activities', 'latitude', 'longitude', 'address',
            ]);

            if ($request->has('status')) {
                $updateData['status'] = $request->status;
                if ($request->status === 'approved') {
                    $updateData['approved_by'] = Auth::id();
                    $updateData['approved_at'] = now();
                    $updateData['is_archived'] = false;
                } elseif ($request->status === 'rejected') {
                    $updateData['rejected_by'] = Auth::id();
                    $updateData['rejected_at'] = now();
                }
            }

            if ($request->has('user_id')) {
                $updateData['user_id'] = $request->user_id;
            }

            $annonce->update($updateData);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Annonce mise à jour avec succès',
                'data'    => $this->formatForFrontend($annonce->fresh()->load('photos', 'user')),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Erreur lors de la mise à jour', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Approuver
     */
    public function approve($id)
    {
        $annonce = Annonce::findOrFail($id);
        $annonce->update([
            'status'      => 'approved',
            'is_archived' => false,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Annonce approuvée avec succès',
            'data'    => $this->formatForFrontend($annonce->fresh()),
        ]);
    }

    /**
     * Rejeter
     */
    public function reject(Request $request, $id)
    {
        $annonce    = Annonce::findOrFail($id);
        $updateData = [
            'status'      => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => Auth::id(),
        ];

        if ($request->has('reason')) {
            $updateData['rejection_reason'] = $request->reason;
        }

        $annonce->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Annonce rejetée',
            'data'    => $this->formatForFrontend($annonce->fresh()),
        ]);
    }

    /**
     * Archiver
     */
    public function archive($id)
    {
        $annonce = Annonce::findOrFail($id);
        $annonce->archive();

        return response()->json([
            'success' => true,
            'message' => 'Annonce archivée avec succès',
            'data'    => $this->formatForFrontend($annonce->fresh()),
        ]);
    }

    /**
     * Désarchiver
     */
    public function unarchive($id)
    {
        $annonce = Annonce::findOrFail($id);
        $annonce->unarchive();

        return response()->json([
            'success' => true,
            'message' => 'Annonce désarchivée avec succès',
            'data'    => $this->formatForFrontend($annonce->fresh()),
        ]);
    }

    /**
     * Soft delete
     */
    public function destroy($id)
    {
        $annonce = Annonce::findOrFail($id);

        foreach ($annonce->photos as $photo) {
            if ($photo->path_to_img && Storage::disk('public')->exists($photo->path_to_img)) {
                Storage::disk('public')->delete($photo->path_to_img);
            }
            $photo->delete();
        }

        $annonce->delete();

        return response()->json(['success' => true, 'message' => 'Annonce supprimée avec succès']);
    }

    /**
     * Force delete
     */
    public function forceDelete($id)
    {
        $annonce = Annonce::withTrashed()->findOrFail($id);

        foreach ($annonce->photos as $photo) {
            if ($photo->path_to_img && Storage::disk('public')->exists($photo->path_to_img)) {
                Storage::disk('public')->delete($photo->path_to_img);
            }
            $photo->forceDelete();
        }

        $annonce->likes()->delete();
        $annonce->forceDelete();

        return response()->json(['success' => true, 'message' => 'Annonce supprimée définitivement']);
    }

    /**
     * Bulk action
     */
    public function bulkAction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids'    => 'required|array',
            'ids.*'  => 'exists:annonces,id',
            'action' => 'required|in:approve,reject,archive,delete',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $results = [];
        DB::beginTransaction();

        try {
            foreach ($request->ids as $id) {
                $annonce = Annonce::find($id);

                switch ($request->action) {
                    case 'approve':
                        $annonce->update(['status' => 'approved', 'is_archived' => false, 'approved_at' => now(), 'approved_by' => Auth::id()]);
                        $results[$id] = 'approved';
                        break;
                    case 'reject':
                        $annonce->update(['status' => 'rejected', 'rejected_at' => now(), 'rejected_by' => Auth::id()]);
                        $results[$id] = 'rejected';
                        break;
                    case 'archive':
                        $annonce->archive();
                        $results[$id] = 'archived';
                        break;
                    case 'delete':
                        $annonce->delete();
                        $results[$id] = 'deleted';
                        break;
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => count($request->ids) . ' annonce(s) traitées avec succès', 'results' => $results]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Erreur lors du traitement', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Statistiques avancées
     */
    public function statistics(Request $request)
    {
        $stats = [
            'overview' => [
                'total'     => Annonce::count(),
                'published' => Annonce::where('status', 'approved')->where('is_archived', false)->count(),
                'pending'   => Annonce::where('status', 'pending')->count(),
                'rejected'  => Annonce::where('status', 'rejected')->count(),
                'archived'  => Annonce::where('is_archived', true)->count(),
            ],
            'engagement' => [
                'total_views'        => Annonce::sum('views_count'),
                'total_likes'        => Annonce::sum('likes_count'),
                'total_comments'     => Annonce::sum('comments_count'),
                'avg_views_per_post' => round(Annonce::avg('views_count'), 2),
                'avg_likes_per_post' => round(Annonce::avg('likes_count'), 2),
            ],
            'by_type' => [
                'school_camp' => Annonce::where('type', 'School Camp')->count(),
                'summer_camp' => Annonce::where('type', 'Summer Camp')->count(),
            ],
            'by_user_role' => [
                'admin'     => Annonce::whereHas('user', fn ($q) => $q->where('role', 'admin'))->count(),
                'moderator' => Annonce::whereHas('user', fn ($q) => $q->where('role', 'moderator'))->count(),
                'user'      => Annonce::whereHas('user', fn ($q) => $q->where('role', 'user'))->count(),
            ],
            'by_month' => Annonce::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('count(*) as total'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved'),
                DB::raw('SUM(CASE WHEN status = "pending"  THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected')
            )->groupBy('month')->orderBy('month', 'desc')->limit(12)->get(),
            'recent_activity' => [
                'last_7_days'  => Annonce::where('created_at', '>=', now()->subDays(7))->count(),
                'last_24_hours'=> Annonce::where('created_at', '>=', now()->subHours(24))->count(),
            ],
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * Export CSV
     */
    public function export(Request $request)
    {
        $query = Annonce::with(['user']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $annonces = $query->get();

        $csvData   = [];
        $csvData[] = ['ID', 'Titre', 'Type', 'Statut', 'Auteur', 'Rôle', 'Date création', 'Début', 'Fin', 'Vues', 'Likes', 'Commentaires'];

        foreach ($annonces as $annonce) {
            $csvData[] = [
                $annonce->id,
                $annonce->title,
                $annonce->type,
                $annonce->status,
                $annonce->user->name,
                $annonce->user->role,
                $annonce->created_at->format('Y-m-d H:i'),
                $annonce->start_date ? $annonce->start_date->format('Y-m-d') : '',
                $annonce->end_date   ? $annonce->end_date->format('Y-m-d')   : '',
                $annonce->views_count,
                $annonce->likes_count,
                $annonce->comments_count,
            ];
        }

        return response()->json([
            'success'  => true,
            'data'     => $csvData,
            'filename' => 'annonces_export_' . now()->format('Y-m-d_His') . '.csv',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers privés
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Formater une annonce pour le frontend.
     *
     * Corrections appliquées :
     *  - FIX 3 : objet `location` construit explicitement à partir des colonnes plates
     *  - FIX 4 : champ `role` ajouté dans le sous-objet `user`
     *  - FIX 5 : `url` toujours présent dans chaque photo (fallback asset())
     */
    private function formatForFrontend($annonce): array
    {
        // Le champ `type` peut être une relation Eloquent (objet) ou une string selon le modèle.
        // On extrait toujours une string pour éviter l'erreur React "Objects are not valid as a React child".
        $type = $annonce->type;
        if (is_object($type)) {
            // Relation : on essaie `name`, sinon `label`, sinon cast en string
            $type = $type->name ?? $type->label ?? (string) $type;
        }

        return [
            'id'          => $annonce->id,
            'title'       => $annonce->title,
            'type'        => $type,
            'description' => $annonce->description,
            'dateRange'   => ($annonce->start_date && $annonce->end_date)
                ? $annonce->start_date->format('M d') . ' - ' . $annonce->end_date->format('M d, Y')
                : 'Date non définie',
            'status'      => $this->mapStatusForFrontend($annonce),
            'role'        => $annonce->user->role ?? 'user',
            'dateCreated' => $annonce->created_at->format('Y-m-d'),
            'stats'       => [
                'views'    => $annonce->views_count    ?? 0,
                'likes'    => $annonce->likes_count    ?? 0,
                'comments' => $annonce->comments_count ?? 0,
            ],

            // FIX 4 : role inclus dans l'objet user imbriqué
            'user' => $annonce->user ? [
                'id'    => $annonce->user_id,
                'name'  => $annonce->user->name  ?? 'Inconnu',
                'email' => $annonce->user->email ?? null,
                'role'  => $annonce->user->role  ?? 'user',   // ← ajouté
            ] : null,

            // FIX 5 : url toujours présente (compatibilité adaptPostForModal)
            'photos' => $annonce->photos->map(function ($photo) {
                return [
                    'id'       => $photo->id,
                    'url'      => asset('storage/' . $photo->path_to_img),  // ← toujours défini
                    'is_cover' => (bool) ($photo->is_cover ?? false),
                ];
            })->values()->toArray(),

            // FIX 3 : objet location construit explicitement
            'location' => [
                'lat'     => $annonce->latitude  ?? null,
                'lng'     => $annonce->longitude ?? null,
                'address' => $annonce->address   ?? null,
            ],

            'activities' => $annonce->activities ?? [],
            'start_date' => $annonce->start_date ? $annonce->start_date->toDateString() : null,
            'end_date'   => $annonce->end_date   ? $annonce->end_date->toDateString()   : null,
        ];
    }

    /**
     * Mapper le statut DB → statut frontend.
     * La BDD stocke 'approved' ; le frontend affiche 'published'.
     */
    private function mapStatusForFrontend($annonce): string
    {
        if ($annonce->is_archived) {
            return 'archived';
        }
        return match ($annonce->status) {
            'approved' => 'published',
            'pending'  => 'pending',
            'rejected' => 'rejected',
            default    => 'archived',
        };
    }

    /**
     * Statistiques pour les cards du dashboard.
     */
    private function getAdminStats(): array
    {
        return [
            'total'     => Annonce::count(),
            'published' => Annonce::where('status', 'approved')->where('is_archived', false)->count(),
            'pending'   => Annonce::where('status', 'pending')->count(),
            'rejected'  => Annonce::where('status', 'rejected')->count(),
            'archived'  => Annonce::where('is_archived', true)->count(),
        ];
    }

    /**
     * Historique de modération d'une annonce.
     */
    private function getModerationHistory($annonce): array
    {
        $history = [];

        if ($annonce->approved_at) {
            $history[] = [
                'action' => 'approved',
                'date'   => $annonce->approved_at,
                'by'     => $annonce->approved_by
                    ? optional(User::find($annonce->approved_by))->name ?? 'Système'
                    : 'Système',
            ];
        }

        if ($annonce->rejected_at) {
            $history[] = [
                'action' => 'rejected',
                'date'   => $annonce->rejected_at,
                'by'     => $annonce->rejected_by
                    ? optional(User::find($annonce->rejected_by))->name ?? 'Système'
                    : 'Système',
                'reason' => $annonce->rejection_reason ?? null,
            ];
        }

        return $history;
    }
}