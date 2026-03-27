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
    /**
     * Constructeur avec middleware
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin,moderator');
    }

    /**
     * Liste complète des annonces avec tous les filtres (admin view)
     */
    public function index(Request $request)
    {
        $query = Annonce::with(['user', 'photos']);

        // Filtre par statut (comme dans le frontend)
        if ($request->has('status') && $request->status !== 'all') {
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
                    $query->where('is_archived', true)
                          ->orWhere(function($q) {
                              $q->where('auto_archive', true)
                                ->whereNotNull('end_date')
                                ->where('end_date', '<', now());
                          });
                    break;
            }
        }

        // Filtre par type (School Camp / Summer Camp)
        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Filtre par rôle de l'utilisateur
        if ($request->has('role') && $request->role !== 'all') {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('role', $request->role);
            });
        }

        // Recherche par titre
        if ($request->has('search') && $request->search) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Filtre par date de création
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filtre par période de l'événement
        if ($request->has('start_date') && $request->start_date) {
            $query->where('start_date', '>=', $request->start_date);
        }
        if ($request->has('end_date') && $request->end_date) {
            $query->where('end_date', '<=', $request->end_date);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $annonces = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Transformer les données pour correspondre au format frontend
        $formattedAnnonces = $annonces->getCollection()->map(function($annonce) {
            return $this->formatForFrontend($annonce);
        });

        $annonces->setCollection($formattedAnnonces);

        // Statistiques pour les cards
        $stats = $this->getAdminStats();

        return response()->json([
            'success' => true,
            'data' => $annonces,
            'stats' => $stats,
            'filters' => $request->all()
        ]);
    }

    /**
     * Créer une nouvelle annonce (admin)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:School Camp,Summer Camp',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'auto_archive' => 'boolean',
            'activities' => 'nullable|array',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'address' => 'nullable|string',
            'status' => 'nullable|in:pending,approved,rejected',
            'user_id' => 'nullable|exists:users,id', // Pour créer une annonce au nom d'un autre utilisateur
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Déterminer l'utilisateur (admin ou autre)
            $userId = $request->user_id ?? Auth::id();
            
            // Déterminer le statut
            $status = $request->status ?? 'pending';
            
            // Si l'admin crée directement une annonce approuvée
           // PAR
            if ($status === 'approved' && Auth::user()->role === 'admin') {
                $status = 'approved';
            }

            $annonce = Annonce::create([
                'user_id' => $userId,
                'title' => $request->title,
                'description' => $request->description,
                'type' => $request->type,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'auto_archive' => $request->auto_archive ?? true,
                'activities' => $request->activities,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'address' => $request->address,
                'status' => $status,
                'approved_by' => $status === 'approved' ? Auth::id() : null,
                'approved_at' => $status === 'approved' ? now() : null,
                'is_archived' => false
            ]);

            // Gérer les photos si présentes
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $index => $photo) {
                    $path = $photo->store('annonces', 'public');
                    $annonce->photos()->create([
                        'path_to_img' => $path,
                        'user_id' => Auth::id(),
                        'annonce_id' => $annonce->id,
                        'is_cover' => $index === 0, // La première photo est la couverture
                        'order' => $index
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Annonce créée avec succès',
                'data' => $this->formatForFrontend($annonce->load('photos', 'user'))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'annonce',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Détail d'une annonce (admin view)
     */
    public function show($id)
    {
        $annonce = Annonce::with(['user', 'photos', 'likes.user'])
            ->findOrFail($id);

        // Incrémenter les vues si demandé
        if (request()->has('increment_view')) {
            $annonce->incrementViews();
        }

        $formattedAnnonce = $this->formatForFrontend($annonce);
        
        // Ajouter des informations supplémentaires pour l'admin
        $formattedAnnonce['admin_details'] = [
            'user_email' => $annonce->user->email,
            'user_phone' => $annonce->user->phone ?? null,
            'moderation_history' => $this->getModerationHistory($annonce)
        ];

        return response()->json([
            'success' => true,
            'data' => $formattedAnnonce
        ]);
    }

    /**
     * Mettre à jour une annonce (admin)
     */
    public function update(Request $request, $id)
    {
        $annonce = Annonce::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'type' => 'sometimes|in:School Camp,Summer Camp',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'auto_archive' => 'boolean',
            'activities' => 'nullable|array',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'address' => 'nullable|string',
            'status' => 'sometimes|in:pending,approved,rejected,archived',
            'user_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $updateData = $request->only([
                'title', 'description', 'type', 'start_date', 'end_date',
                'auto_archive', 'activities', 'latitude', 'longitude', 'address'
            ]);

            // Gestion du statut
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

            // Changement d'utilisateur propriétaire
            if ($request->has('user_id')) {
                $updateData['user_id'] = $request->user_id;
            }

            $annonce->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Annonce mise à jour avec succès',
                'data' => $this->formatForFrontend($annonce->fresh()->load('photos', 'user'))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approuver une annonce
     */
    public function approve($id)
    {
        $annonce = Annonce::findOrFail($id);
        
        $annonce->update([
            'status' => 'approved',
            'is_archived' => false,
            'approved_at' => now(),
            'approved_by' => Auth::id()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Annonce approuvée avec succès',
            'data' => $this->formatForFrontend($annonce->fresh())
        ]);
    }

    /**
     * Rejeter une annonce
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500'
        ]);

        $annonce = Annonce::findOrFail($id);
        
        $updateData = [
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => Auth::id()
        ];

        if ($request->has('reason')) {
            $updateData['rejection_reason'] = $request->reason;
        }

        $annonce->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Annonce rejetée',
            'data' => $this->formatForFrontend($annonce->fresh())
        ]);
    }

    /**
     * Archiver une annonce
     */
    public function archive($id)
    {
        $annonce = Annonce::findOrFail($id);
        $annonce->archive();

        return response()->json([
            'success' => true,
            'message' => 'Annonce archivée avec succès',
            'data' => $this->formatForFrontend($annonce->fresh())
        ]);
    }

    /**
     * Désarchiver une annonce
     */
    public function unarchive($id)
    {
        $annonce = Annonce::findOrFail($id);
        $annonce->unarchive();

        return response()->json([
            'success' => true,
            'message' => 'Annonce désarchivée avec succès',
            'data' => $this->formatForFrontend($annonce->fresh())
        ]);
    }

    /**
     * Supprimer une annonce (soft delete)
     */
    public function destroy($id)
    {
        $annonce = Annonce::findOrFail($id);
        
        // Supprimer les photos associées
        foreach ($annonce->photos as $photo) {
            if ($photo->path_to_img && Storage::disk('public')->exists($photo->path_to_img)) {
                Storage::disk('public')->delete($photo->path_to_img);
            }
            $photo->delete();
        }
        
        $annonce->delete();

        return response()->json([
            'success' => true,
            'message' => 'Annonce supprimée avec succès'
        ]);
    }

    /**
     * Supprimer définitivement une annonce
     */
    public function forceDelete($id)
    {
        $annonce = Annonce::withTrashed()->findOrFail($id);
        
        // Supprimer les photos
        foreach ($annonce->photos as $photo) {
            if ($photo->path_to_img && Storage::disk('public')->exists($photo->path_to_img)) {
                Storage::disk('public')->delete($photo->path_to_img);
            }
            $photo->forceDelete();
        }
        
        // Supprimer les likes
        $annonce->likes()->delete();
        
        $annonce->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Annonce supprimée définitivement'
        ]);
    }

    /**
     * Actions en masse
     */
    public function bulkAction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'exists:annonces,id',
            'action' => 'required|in:approve,reject,archive,delete'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $ids = $request->ids;
        $action = $request->action;
        $results = [];

        DB::beginTransaction();

        try {
            foreach ($ids as $id) {
                $annonce = Annonce::find($id);
                
                switch ($action) {
                    case 'approve':
                        $annonce->update([
                            'status' => 'approved',
                            'is_archived' => false,
                            'approved_at' => now(),
                            'approved_by' => Auth::id()
                        ]);
                        $results[$id] = 'approved';
                        break;
                    case 'reject':
                        $annonce->update([
                            'status' => 'rejected',
                            'rejected_at' => now(),
                            'rejected_by' => Auth::id()
                        ]);
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
            
            return response()->json([
                'success' => true,
                'message' => count($ids) . ' annonce(s) traitées avec succès',
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du traitement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques avancées
     */
    public function statistics(Request $request)
    {
        $stats = [
            'overview' => [
                'total' => Annonce::count(),
                'published' => Annonce::where('status', 'approved')->where('is_archived', false)->count(),
                'pending' => Annonce::where('status', 'pending')->count(),
                'rejected' => Annonce::where('status', 'rejected')->count(),
                'archived' => Annonce::where('is_archived', true)->count(),
            ],
            'engagement' => [
                'total_views' => Annonce::sum('views_count'),
                'total_likes' => Annonce::sum('likes_count'),
                'total_comments' => Annonce::sum('comments_count'),
                'avg_views_per_post' => round(Annonce::avg('views_count'), 2),
                'avg_likes_per_post' => round(Annonce::avg('likes_count'), 2),
            ],
            'by_type' => [
                'school_camp' => Annonce::where('type', 'School Camp')->count(),
                'summer_camp' => Annonce::where('type', 'Summer Camp')->count(),
            ],
            'by_user_role' => [
                'admin' => Annonce::whereHas('user', function($q) {
                    $q->where('role', 'admin');
                })->count(),
                'moderator' => Annonce::whereHas('user', function($q) {
                    $q->where('role', 'moderator');
                })->count(),
                'user' => Annonce::whereHas('user', function($q) {
                    $q->where('role', 'user');
                })->count(),
            ],
            'by_month' => Annonce::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('count(*) as total'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected')
            )
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get(),
            'recent_activity' => [
                'last_7_days' => Annonce::where('created_at', '>=', now()->subDays(7))->count(),
                'last_24_hours' => Annonce::where('created_at', '>=', now()->subHours(24))->count(),
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Exporter les annonces
     */
    public function export(Request $request)
    {
        $query = Annonce::with(['user']);
        
        // Appliquer les filtres
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }
        
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        $annonces = $query->get();
        
        $csvData = [];
        $csvData[] = [
            'ID', 'Titre', 'Type', 'Statut', 'Auteur', 'Rôle', 
            'Date création', 'Début', 'Fin', 'Vues', 'Likes', 'Commentaires'
        ];
        
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
                $annonce->end_date ? $annonce->end_date->format('Y-m-d') : '',
                $annonce->views_count,
                $annonce->likes_count,
                $annonce->comments_count
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => $csvData,
            'filename' => 'annonces_export_' . now()->format('Y-m-d_His') . '.csv'
        ]);
    }

    /**
     * Formater une annonce pour le frontend
     */
    private function formatForFrontend($annonce)
    {
        return [
            'id' => $annonce->id,
            'title' => $annonce->title,
            'type' => $annonce->type,
            'description' => $annonce->description,
            'dateRange' => $annonce->start_date && $annonce->end_date 
                ? $annonce->start_date->format('M d') . ' - ' . $annonce->end_date->format('M d, Y')
                : 'Date non définie',
            'status' => $this->mapStatusForFrontend($annonce),
            'role' => $annonce->user->role ?? 'User',
            'dateCreated' => $annonce->created_at->format('Y-m-d'),
            'stats' => [
                'views' => $annonce->views_count,
                'likes' => $annonce->likes_count,
                'comments' => $annonce->comments_count
            ],
            'user' => [
                'id' => $annonce->user_id,
                'name' => $annonce->user->name ?? 'Inconnu',
                'email' => $annonce->user->email ?? null
            ],
            'photos' => $annonce->photos->map(function($photo) {
                return [
                    'id' => $photo->id,
                    'url' => $photo->url ?? asset('storage/' . $photo->path_to_img),
                    'is_cover' => $photo->is_cover ?? false
                ];
            }),
            'location' => $annonce->location,
            'activities' => $annonce->activities,
            'start_date' => $annonce->start_date,
            'end_date' => $annonce->end_date
        ];
    }

    /**
     * Mapper le statut pour le frontend
     */
    private function mapStatusForFrontend($annonce)
    {
        if ($annonce->status === 'approved' && !$annonce->is_archived) {
            return 'published';
        } elseif ($annonce->status === 'pending') {
            return 'pending';
        } elseif ($annonce->status === 'rejected') {
            return 'rejected';
        } else {
            return 'archived';
        }
    }

    /**
     * Statistiques pour les cards
     */
    private function getAdminStats()
    {
        return [
            'total' => Annonce::count(),
            'published' => Annonce::where('status', 'approved')->where('is_archived', false)->count(),
            'pending' => Annonce::where('status', 'pending')->count(),
            'rejected' => Annonce::where('status', 'rejected')->count(),
            'archived' => Annonce::where('is_archived', true)->count()
        ];
    }

    /**
     * Récupérer l'historique de modération
     */
    private function getModerationHistory($annonce)
    {
        $history = [];
        
        if ($annonce->approved_at) {
            $history[] = [
                'action' => 'approved',
                'date' => $annonce->approved_at,
                'by' => $annonce->approved_by ? User::find($annonce->approved_by)->name ?? 'Système' : 'Système'
            ];
        }
        
        if ($annonce->rejected_at) {
            $history[] = [
                'action' => 'rejected',
                'date' => $annonce->rejected_at,
                'by' => $annonce->rejected_by ? User::find($annonce->rejected_by)->name ?? 'Système' : 'Système',
                'reason' => $annonce->rejection_reason ?? null
            ];
        }
        
        return $history;
    }
}