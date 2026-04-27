<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Feedback;
use App\Models\User;
use App\Models\Camping_Zones;
use App\Models\Events;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AdminFeedbackController extends Controller
{
    /**
     * Liste tous les feedbacks avec filtres
     */
    public function index(Request $request)
    {
        try {
            $query = Feedback::with([
                'user',
                'zone',
                'event',
                'userTarget'
            ]);

            // Filtre par statut
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filtre par type
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            // Filtre par note
            if ($request->filled('note')) {
                $query->where('note', $request->note);
            }

            // Filtre par type de cible (pour les onglets)
            if ($request->filled('target_type') && $request->target_type !== 'all') {
                switch ($request->target_type) {
                    case 'guide':
                        $query->whereIn('type', ['groupe', 'guide', 'user']);
                        break;
                    case 'fournisseur':
                        $query->where('type', 'fournisseur');
                        break;
                    case 'zone':
                        $query->where('type', 'zone');
                        break;
                    case 'centre':
                        $query->whereIn('type', ['centre_user', 'centre_camping']);
                        break;
                    case 'event':
                        $query->where('type', 'event');
                        break;
                }
            }

            // Recherche
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('contenu', 'LIKE', "%{$search}%")
                      ->orWhereHas('user', function($u) use ($search) {
                          $u->where('first_name', 'LIKE', "%{$search}%")
                            ->orWhere('last_name', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%");
                      })
                      ->orWhereHas('userTarget', function($u) use ($search) {
                          $u->where('first_name', 'LIKE', "%{$search}%")
                            ->orWhere('last_name', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%");
                      })
                      ->orWhereHas('zone', function($z) use ($search) {
                          $z->where('nom', 'LIKE', "%{$search}%");
                      })
                      ->orWhereHas('event', function($e) use ($search) {
                          $e->where('title', 'LIKE', "%{$search}%")
                            ->orWhere('nom', 'LIKE', "%{$search}%");
                      });
                });
            }

            // Tri
            $sortBy = $request->get('sort_by', 'date');
            $sortOrder = $request->get('sort_order', 'desc');

            switch ($sortBy) {
                case 'date':
                    $query->orderBy('created_at', $sortOrder);
                    break;
                case 'user':
                    $query->orderByRaw("CONCAT(users.first_name, ' ', users.last_name) $sortOrder");
                    break;
                case 'rating':
                    $query->orderBy('note', $sortOrder);
                    break;
                case 'status':
                    $query->orderBy('status', $sortOrder);
                    break;
                default:
                    $query->latest();
            }

            // Pagination
            $perPage = $request->get('per_page', 20);
            $feedbacks = $query->paginate($perPage);

            // Transformer les données
            $transformedFeedbacks = $feedbacks->getCollection()->map(function ($feedback) {
                return $this->transformFeedback($feedback);
            });

            $feedbacks->setCollection($transformedFeedbacks);

            // Statistiques
            $stats = Feedback::getStats();

            return response()->json([
                'success' => true,
                'data' => $feedbacks,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur feedbacks index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des feedbacks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transforme un feedback pour le frontend
     */
    private function transformFeedback($feedback)
    {
        return [
            'id' => $feedback->id,
            'user_id' => $feedback->user_id,
            'user_name' => $feedback->author_name,
            'user_email' => $feedback->user->email ?? '',
            'user_avatar' => $feedback->author_avatar,
            'target_id' => $feedback->target_id ?? $feedback->zone_id ?? $feedback->event_id,
            'target_name' => $feedback->target_name,
            'target_type' => $feedback->target_type,
            'target_details' => $feedback->target_details,
            'event_id' => $feedback->event_id,
            'event_name' => $feedback->event->title ?? $feedback->event->nom ?? null,
            'zone_id' => $feedback->zone_id,
            'zone_name' => $feedback->zone->nom ?? null,
            'content' => $feedback->contenu,
            'response' => $feedback->response,
            'rating' => (int) $feedback->note,
            'type' => $feedback->type,
            'status' => $feedback->status ?? 'pending',
            'rejection_reason' => $feedback->rejection_reason,
            'created_at' => $feedback->created_at ? $feedback->created_at->toIso8601String() : now()->toIso8601String(),
            'updated_at' => $feedback->updated_at ? $feedback->updated_at->toIso8601String() : now()->toIso8601String(),
            'period' => $feedback->period,
            'is_fournisseur' => $feedback->is_fournisseur,
            'is_guide' => $feedback->is_guide,
            'target_exists' => $feedback->target_exists,
        ];
    }

    /**
     * Récupère les feedbacks par type pour les onglets
     */
    public function getByType($type, Request $request)
    {
        try {
            $query = Feedback::with(['user', 'zone', 'event', 'userTarget']);
            
            switch ($type) {
                case 'guide':
                    $query->guides();
                    break;
                case 'fournisseur':
                    $query->fournisseurs();
                    break;
                case 'zone':
                    $query->zones();
                    break;
                case 'centre':
                    $query->centres();
                    break;
                case 'event':
                    $query->events();
                    break;
                default:
                    return response()->json(['success' => false, 'message' => 'Type invalide'], 400);
            }

            // Appliquer les autres filtres
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('contenu', 'LIKE', "%{$search}%")
                      ->orWhereHas('user', function($u) use ($search) {
                          $u->where('first_name', 'LIKE', "%{$search}%")
                            ->orWhere('last_name', 'LIKE', "%{$search}%");
                      })
                      ->orWhereHas('userTarget', function($u) use ($search) {
                          $u->where('first_name', 'LIKE', "%{$search}%")
                            ->orWhere('last_name', 'LIKE', "%{$search}%");
                      });
                });
            }

            $perPage = $request->get('per_page', 20);
            $feedbacks = $query->latest()->paginate($perPage);
            
            $transformedFeedbacks = $feedbacks->getCollection()->map(function ($feedback) {
                return $this->transformFeedback($feedback);
            });

            $feedbacks->setCollection($transformedFeedbacks);

            return response()->json([
                'success' => true,
                'data' => $feedbacks
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur getByType: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération'
            ], 500);
        }
    }

    /**
     * Affiche un feedback spécifique
     */
    public function show($id)
    {
        try {
            $feedback = Feedback::with(['user', 'zone', 'event', 'userTarget'])->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $this->transformFeedback($feedback)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Feedback non trouvé'
            ], 404);
        }
    }

    /**
     * Met à jour un feedback
     */
    public function update(Request $request, $id)
    {
        try {
            Log::info('Tentative de mise à jour feedback ID: ' . $id);
            Log::info('Données reçues: ', $request->all());

            // Vérifier que le modèle existe
            if (!class_exists('App\\Models\\Feedback')) {
                Log::error('Le modèle Feedback n\'existe pas');
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de configuration du serveur'
                ], 500);
            }

            // Chercher le feedback
            $feedback = Feedback::find($id);
            
            if (!$feedback) {
                Log::warning('Feedback non trouvé avec ID: ' . $id);
                return response()->json([
                    'success' => false,
                    'message' => 'Feedback non trouvé'
                ], 404);
            }

            Log::info('Feedback trouvé:', ['id' => $feedback->id, 'type' => $feedback->type]);

            // Validation
            $validator = Validator::make($request->all(), [
                'contenu' => 'sometimes|string|min:3',
                'response' => 'nullable|string',
                'note' => 'sometimes|integer|min:1|max:5',
                'status' => 'sometimes|in:pending,approved,rejected'
            ]);

            if ($validator->fails()) {
                Log::warning('Validation échouée:', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();
            Log::info('Données validées:', $validated);

            // Mise à jour
            $feedback->update($validated);
            Log::info('Feedback mis à jour avec succès');

            // Rafraîchir le feedback avec ses relations
            $feedback->load(['user', 'zone', 'event', 'userTarget']);

            return response()->json([
                'success' => true,
                'message' => 'Feedback mis à jour avec succès',
                'data' => $this->transformFeedback($feedback)
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('ModelNotFoundException: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Feedback non trouvé'
            ], 404);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('ValidationException: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Erreur update feedback: ' . $e->getMessage());
            Log::error('Fichier: ' . $e->getFile() . ' Ligne: ' . $e->getLine());
            Log::error('Trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }



    
    /**
     * Approuve un feedback
     */
   public function approve($id)
{
    try {
        $feedback = Feedback::findOrFail($id);
        
        // Un feedback approuvé peut être rejeté plus tard
        // Un feedback rejeté peut être approuvé plus tard
        $feedback->approve();

        return response()->json([
            'success' => true,
            'message' => 'Feedback approuvé avec succès'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de l\'approbation'
        ], 500);
    }
}

/**
 * Rejette un feedback
 */
public function reject(Request $request, $id)
{
    try {
        $feedback = Feedback::findOrFail($id);
        
        $validated = $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $feedback->reject($validated['reason']);

        return response()->json([
            'success' => true,
            'message' => 'Feedback rejeté avec succès'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors du rejet'
        ], 500);
    }
}

    /**
     * Supprime un feedback
     */
    public function destroy($id)
    {
        try {
            $feedback = Feedback::findOrFail($id);
            $feedback->delete();

            return response()->json([
                'success' => true,
                'message' => 'Feedback supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    /**
     * Récupère les statistiques des feedbacks
     */
    public function stats()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => Feedback::getStats()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques'
            ], 500);
        }
    }

    /**
     * Récupère tous les feedbacks des fournisseurs
     */
    public function fournisseurs(Request $request)
    {
        try {
            $query = Feedback::with(['user', 'userTarget'])
                ->fournisseurs();

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $perPage = $request->get('per_page', 20);
            $feedbacks = $query->latest()->paginate($perPage);
            
            $transformedFeedbacks = $feedbacks->getCollection()->map(function ($feedback) {
                return $this->transformFeedback($feedback);
            });

            $feedbacks->setCollection($transformedFeedbacks);

            return response()->json([
                'success' => true,
                'data' => $feedbacks
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération'
            ], 500);
        }
    }
}