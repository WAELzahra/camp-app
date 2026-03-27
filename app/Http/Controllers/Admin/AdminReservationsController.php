<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Reservations_centre;
use App\Models\Reservations_events;
use App\Models\Reservations_materielles;
use App\Models\Reservation_guide;
use App\Models\User;
use App\Models\Events;
use App\Models\Materielles;
use App\Models\Circuit;
use App\Models\Payments;
use App\Mail\ReservationStatusUpdated;
use Illuminate\Support\Facades\Mail;

class AdminReservationsController extends Controller
{
    const RESERVATION_TYPES = [
        'center' => Reservations_centre::class,
        'events' => Reservations_events::class,
        'materielle' => Reservations_materielles::class,
        'guides' => Reservation_guide::class,
    ];

    const STATUS_MAPPING = [
        'center' => [
            'pending' => 'En attente',
            'approved' => 'Approuvée',
            'rejected' => 'Rejetée',
            'modified' => 'Modifiée',
            'canceled' => 'Annulée',
        ],
        'events' => [
            'en_attente_paiement' => 'En attente de paiement',
            'confirmée' => 'Confirmée',
            'en_attente_validation' => 'En attente de validation',
            'refusée' => 'Refusée',
            'annulée_par_utilisateur' => 'Annulée par utilisateur',
            'annulée_par_organisateur' => 'Annulée par organisateur',
            'remboursement_en_attente' => 'Remboursement en attente',
            'remboursée_partielle' => 'Remboursée partielle',
            'remboursée_totale' => 'Remboursée totale',
        ],
        'materielle' => [
            'pending' => 'En attente',
            'approved' => 'Approuvée',
            'rejected' => 'Rejetée',
            'canceled' => 'Annulée',
        ],
        'guides' => [
            'pending' => 'En attente',
            'approved' => 'Approuvée',
            'rejected' => 'Rejetée',
            'canceled' => 'Annulée',
        ],
    ];

    /**
     * Liste toutes les réservations (tous types confondus)
     */
    public function index(Request $request)
    {
        $request->validate([
            'type' => 'nullable|in:center,events,materielle,guides,all',
            'status' => 'nullable|string',
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'user_id' => 'nullable|exists:users,id',
            'sort_by' => 'nullable|in:date_debut,date_fin,created_at,status,id',
            'sort_dir' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $type = $request->get('type', 'all');
        $reservations = collect();

        if ($type === 'all' || $type === 'center') {
            $centerReservations = $this->getCenterReservations($request);
            $reservations = $reservations->concat($centerReservations);
        }

        if ($type === 'all' || $type === 'events') {
            $eventReservations = $this->getEventReservations($request);
            $reservations = $reservations->concat($eventReservations);
        }

        if ($type === 'all' || $type === 'materielle') {
            $materialReservations = $this->getMaterialReservations($request);
            $reservations = $reservations->concat($materialReservations);
        }

        if ($type === 'all' || $type === 'guides') {
            $guideReservations = $this->getGuideReservations($request);
            $reservations = $reservations->concat($guideReservations);
        }

        // Tri global
        $sortBy = $request->get('sort_by', 'date_debut');
        $sortDir = $request->get('sort_dir', 'desc');
        
        $reservations = $reservations->sortBy([
            [$sortBy, $sortDir]
        ])->values();

        // Pagination manuelle
        $perPage = $request->get('per_page', 20);
        $page = $request->get('page', 1);
        $paginated = $this->paginateCollection($reservations, $perPage, $page);

        return response()->json([
            'success' => true,
            'data' => $paginated->values(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $reservations->count(),
                'last_page' => ceil($reservations->count() / $perPage),
                'types' => $this->getReservationCounts(),
            ]
        ]);
    }

    /**
     * Récupère les réservations de centres
     */
    private function getCenterReservations(Request $request)
    {
        $query = Reservations_centre::with(['user', 'centre', 'payment', 'serviceItems.service'])
            ->select(
                'id',
                'user_id',
                'centre_id',
                'date_debut',
                'date_fin',
                'nbr_place',
                'note',
                'status',
                'payments_id',
                'total_price',
                'service_count',
                'created_at',
                'updated_at',
                DB::raw("'center' as reservation_type")
            );

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('note', 'like', "%{$search}%")
                  ->orWhereHas('user', function($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('date_debut', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('date_fin', '<=', $request->date_to);
        }

        return $query->get()->map(function($item) {
            $item->display_name = "Réservation Centre #{$item->id}";
            $item->subtitle = "Centre: " . ($item->centre->name ?? 'N/A');
            $item->capacity = $item->nbr_place;
            $item->price = $item->total_price ?? 0;
            $item->code = "CR" . str_pad($item->id, 3, '0', STR_PAD_LEFT);
            return $item;
        });
    }

    /**
     * Récupère les réservations d'événements
     */
    private function getEventReservations(Request $request)
    {
        $query = Reservations_events::with(['user', 'event', 'payment', 'group'])
            ->select(
                'id',
                'user_id',
                'group_id',
                'event_id',
                'name',
                'email',
                'phone',
                'nbr_place',
                'status',
                'payment_id',
                'created_at',
                'updated_at',
                DB::raw("'events' as reservation_type"),
                DB::raw("created_at as date_debut"),
                DB::raw("created_at as date_fin")
            );

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhereHas('event', function($eq) use ($search) {
                      $eq->where('title', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query->get()->map(function($item) {
            $item->display_name = $item->name ?? "Réservation Événement #{$item->id}";
            $item->subtitle = "Événement: " . ($item->event->title ?? 'N/A');
            $item->capacity = $item->nbr_place;
            $item->price = 0;
            $item->code = "ER" . str_pad($item->id, 3, '0', STR_PAD_LEFT);
            return $item;
        });
    }

    /**
     * Récupère les réservations de matériel (CORRIGÉ)
     */
    private function getMaterialReservations(Request $request)
    {
        $query = Reservations_materielles::with(['user', 'fournisseur', 'materielle', 'payment'])
            ->select(
                'id',
                'user_id',
                'fournisseur_id',
                'materielle_id',
                'date_debut',
                'date_fin',
                'quantite',
                'montant_payer',
                'status',
                'created_at',
                'updated_at',
                DB::raw("'materielle' as reservation_type")
            );

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('note', 'like', "%{$search}%")
                  ->orWhereHas('user', function($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('materielle', function($mq) use ($search) {
                      $mq->where('nom', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('date_debut', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('date_fin', '<=', $request->date_to);
        }

        return $query->get()->map(function($item) {
            $item->display_name = "Réservation Matériel #{$item->id}";
            // CORRECTION: Utilisation correcte de la relation 'materielle'
            $item->subtitle = "Matériel: " . ($item->materielle->nom ?? 'N/A');
            $item->capacity = $item->quantite;
            $item->price = $item->montant_payer ?? 0;
            $item->code = "MR" . str_pad($item->id, 3, '0', STR_PAD_LEFT);
            return $item;
        });
    }

    /**
     * Récupère les réservations de guides (CORRIGÉ)
     */
    private function getGuideReservations(Request $request)
{
    $query = Reservation_guide::with(['user', 'circuit', 'guide'])
        ->select(
            'id',
            'reserver_id as user_id',
            'guide_id',
            'circuit_id',
            'creation_date as date_debut',
            'creation_date as date_fin',
            'type',
            'discription as note',
            'created_at',
            'updated_at',
            DB::raw("'guides' as reservation_type"),
            DB::raw("'pending' as status")
        );

    // Filtres
    if ($request->has('status') && $request->status !== 'all') {
        $query->where('status', $request->status);
    }

    if ($request->has('user_id')) {
        $query->where('reserver_id', $request->user_id);
    }

    if ($request->has('search')) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('discription', 'like', "%{$search}%")
              ->orWhereHas('user', function($uq) use ($search) {
                  $uq->where('name', 'like', "%{$search}%")
                     ->orWhere('email', 'like', "%{$search}%");
              })
              ->orWhereHas('circuit', function($cq) use ($search) {
                  $cq->where('name', 'like', "%{$search}%");
              })
              ->orWhereHas('guide', function($gq) use ($search) {
                  $gq->where('name', 'like', "%{$search}%");
              });
        });
    }

    if ($request->has('date_from')) {
        $query->whereDate('creation_date', '>=', $request->date_from);
    }

    if ($request->has('date_to')) {
        $query->whereDate('creation_date', '<=', $request->date_to);
    }

    return $query->get()->map(function($item) {
        $item->display_name = "Réservation Guide #{$item->id}";
        $item->subtitle = "Circuit: " . ($item->circuit->name ?? 'N/A');
        $item->capacity = 1;
        $item->price = 0;
        $item->code = "GR" . str_pad($item->id, 3, '0', STR_PAD_LEFT);
        return $item;
    });
}

    /**
     * Récupère les détails d'une réservation spécifique
     */
    public function show($type, $id)
    {
        if (!array_key_exists($type, self::RESERVATION_TYPES)) {
            return response()->json([
                'success' => false,
                'message' => 'Type de réservation invalide'
            ], 400);
        }

        $modelClass = self::RESERVATION_TYPES[$type];
        $relations = $this->getRelationsForType($type);

        try {
            $reservation = $modelClass::with($relations)->findOrFail($id);
            $reservation = $this->enrichReservation($reservation, $type);

            return response()->json([
                'success' => true,
                'data' => $reservation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Réservation non trouvée'
            ], 404);
        }
    }

    /**
     * Met à jour une réservation
     */
    public function update(Request $request, $type, $id)
    {
        if (!array_key_exists($type, self::RESERVATION_TYPES)) {
            return response()->json([
                'success' => false,
                'message' => 'Type de réservation invalide'
            ], 400);
        }

        $modelClass = self::RESERVATION_TYPES[$type];
        
        try {
            $reservation = $modelClass::findOrFail($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Réservation non trouvée'
            ], 404);
        }

        if (!$this->canModifyReservation($reservation, $type)) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé à modifier cette réservation'
            ], 403);
        }

        $validator = $this->getValidatorForType($request, $type, $reservation);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            $oldStatus = $reservation->status;
            $data = $validator->validated();

            if ($type === 'events' && isset($data['nbr_place']) && $data['nbr_place'] != $reservation->nbr_place) {
                $this->handleEventPlaceChange($reservation, $data['nbr_place']);
            }

            $reservation->update($data);

            if ($oldStatus !== $reservation->status && $this->shouldSendEmail($reservation, $type)) {
                $this->sendStatusUpdateEmail($reservation, $type, $oldStatus);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Réservation mise à jour avec succès',
                'data' => $reservation->fresh($this->getRelationsForType($type))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actions groupées sur les réservations
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'type' => 'required|in:center,events,materielle,guides',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'action' => 'required|in:approve,reject,pending,cancel,delete',
            'reason' => 'nullable|string|max:500'
        ]);

        $modelClass = self::RESERVATION_TYPES[$request->type];
        $results = [
            'success' => [],
            'failed' => []
        ];

        DB::beginTransaction();

        try {
            foreach ($request->ids as $id) {
                try {
                    $reservation = $modelClass::find($id);
                    
                    if (!$reservation) {
                        $results['failed'][] = ['id' => $id, 'reason' => 'Réservation non trouvée'];
                        continue;
                    }

                    if (!$this->canModifyReservation($reservation, $request->type)) {
                        $results['failed'][] = ['id' => $id, 'reason' => 'Non autorisé'];
                        continue;
                    }

                    $oldStatus = $reservation->status;
                    
                    switch ($request->action) {
                        case 'approve':
                            $reservation->status = $this->getApprovedStatus($request->type);
                            break;
                        case 'reject':
                            $reservation->status = $this->getRejectedStatus($request->type);
                            break;
                        case 'pending':
                            $reservation->status = $this->getPendingStatus($request->type);
                            break;
                        case 'cancel':
                            $reservation->status = $this->getCanceledStatus($request->type);
                            break;
                        case 'delete':
                            $reservation->delete();
                            $results['success'][] = ['id' => $id, 'action' => 'deleted'];
                            continue 2;
                    }

                    $reservation->save();

                    if ($request->action === 'cancel' && $request->type === 'events') {
                        $this->restoreEventPlaces($reservation);
                    }

                    if ($this->shouldSendEmail($reservation, $request->type)) {
                        $this->sendStatusUpdateEmail($reservation, $request->type, $oldStatus);
                    }

                    $results['success'][] = ['id' => $id, 'status' => $reservation->status];

                } catch (\Exception $e) {
                    $results['failed'][] = ['id' => $id, 'reason' => $e->getMessage()];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Actions groupées effectuées',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors des actions groupées: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprime une réservation
     */
    public function destroy($type, $id)
    {
        if (!array_key_exists($type, self::RESERVATION_TYPES)) {
            return response()->json([
                'success' => false,
                'message' => 'Type de réservation invalide'
            ], 400);
        }

        $modelClass = self::RESERVATION_TYPES[$type];
        
        try {
            $reservation = $modelClass::findOrFail($id);

            if (!$this->canModifyReservation($reservation, $type)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé à supprimer cette réservation'
                ], 403);
            }

            if ($type === 'events' && in_array($reservation->status, ['confirmée', 'en_attente_validation'])) {
                $this->restoreEventPlaces($reservation);
            }

            $reservation->delete();

            return response()->json([
                'success' => true,
                'message' => 'Réservation supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Réservation non trouvée'
            ], 404);
        }
    }

    /**
     * Statistiques des réservations
     */
    public function stats(Request $request)
    {
        $stats = [];

        foreach (self::RESERVATION_TYPES as $type => $modelClass) {
            $query = $modelClass::query();

            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $stats[$type] = [
                'total' => $query->count(),
                'by_status' => $query->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->get()
                    ->pluck('count', 'status'),
                'recent' => $query->whereDate('created_at', '>=', now()->subDays(7))->count(),
            ];
        }

        $stats['total_all'] = array_sum(array_column($stats, 'total'));
        $stats['recent_all'] = array_sum(array_column($stats, 'recent'));

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Export des réservations
     */
    public function export(Request $request)
    {
        $request->validate([
            'type' => 'required|in:center,events,materielle,guides,all',
            'format' => 'required|in:csv,excel',
            'status' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $reservations = $this->getReservationsForExport($request);

        if ($request->format === 'csv') {
            return $this->exportToCsv($reservations);
        } else {
            return $this->exportToExcel($reservations);
        }
    }

    /**
     * Vérifier la disponibilité du matériel
     */
    public function checkMaterialAvailability(Request $request, $materialId)
    {
        $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after:date_debut',
            'quantite' => 'required|integer|min:1'
        ]);

        $materiel = Materielles::findOrFail($materialId);
        
        $reservationsExistantes = Reservations_materielles::where('materielle_id', $materialId)
            ->where('status', 'approved')
            ->where(function($q) use ($request) {
                $q->whereBetween('date_debut', [$request->date_debut, $request->date_fin])
                  ->orWhereBetween('date_fin', [$request->date_debut, $request->date_fin]);
            })
            ->sum('quantite');
        
        $disponible = ($materiel->quantite_dispo - $reservationsExistantes) >= $request->quantite;

        return response()->json([
            'success' => true,
            'material' => $materiel->nom,
            'disponible' => $disponible,
            'quantite_disponible' => $materiel->quantite_dispo - $reservationsExistantes,
            'quantite_demandee' => $request->quantite
        ]);
    }

    /**
     * Méthodes utilitaires privées
     */

    private function getRelationsForType($type)
    {
        $relations = [
            'center' => ['user', 'centre', 'payment', 'serviceItems.service.category'],
            'events' => ['user', 'event', 'payment', 'group'],
            'materielle' => ['user', 'fournisseur', 'materielle', 'payment'],
            'guides' => ['user', 'circuit', 'guide'],
        ];

        return $relations[$type] ?? [];
    }

    private function enrichReservation($reservation, $type)
    {
        switch ($type) {
            case 'center':
                $reservation->total_price = $reservation->total_price ?? ($reservation->calculateTotal() ?? 0);
                $reservation->service_types = $reservation->serviceTypes ?? null;
                $reservation->services_by_category = $reservation->servicesByCategory ?? null;
                break;
            case 'events':
                $reservation->event_title = $reservation->event->title ?? null;
                $reservation->organizer = $reservation->group->name ?? null;
                break;
            case 'materielle':
                $reservation->material_name = $reservation->materielle->nom ?? null;
                $reservation->supplier_name = $reservation->fournisseur->name ?? null;
                $reservation->material_type = $reservation->materielle->type ?? null;
                $reservation->tarif_nuit = $reservation->materielle->tarif_nuit ?? null;
                break;
            case 'guides':
                $reservation->circuit_name = $reservation->circuit->name ?? null;
                $reservation->guide_name = User::find($reservation->guide_id)->name ?? null;
                break;
        }

        $reservation->status_label = $this->getStatusLabel($type, $reservation->status);
        
        return $reservation;
    }

    private function getStatusLabel($type, $status)
    {
        return self::STATUS_MAPPING[$type][$status] ?? $status;
    }

    private function getValidatorForType(Request $request, $type, $reservation)
    {
        $rules = [
            'center' => [
                'status' => 'sometimes|in:pending,approved,rejected,modified,canceled',
                'nbr_place' => 'sometimes|integer|min:1',
                'date_debut' => 'sometimes|date',
                'date_fin' => 'sometimes|date|after_or_equal:date_debut',
                'note' => 'nullable|string',
            ],
            'events' => [
                'status' => 'sometimes|in:en_attente_paiement,confirmée,en_attente_validation,refusée,annulée_par_utilisateur,annulée_par_organisateur,remboursement_en_attente,remboursée_partielle,remboursée_totale',
                'nbr_place' => 'sometimes|integer|min:1',
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email',
                'phone' => 'sometimes|string',
            ],
            'materielle' => [
                'status' => 'sometimes|in:pending,approved,rejected,canceled',
                'quantite' => 'sometimes|integer|min:1',
                'date_debut' => 'sometimes|date',
                'date_fin' => 'sometimes|date|after_or_equal:date_debut',
                'montant_payer' => 'sometimes|numeric|min:0',
            ],
            'guides' => [
                'type' => 'sometimes|string',
                'discription' => 'nullable|string',
            ],
        ];

        return Validator::make($request->all(), $rules[$type]);
    }

    private function canModifyReservation($reservation, $type)
    {
        $user = Auth::user();
        
        if ($user && ($user->role->name === 'admin' || $user->role === 'admin')) {
            return true;
        }

        switch ($type) {
            case 'center':
                return isset($reservation->user_id) && $reservation->user_id === ($user->id ?? null);
            case 'events':
                return isset($reservation->user_id) && $reservation->user_id === ($user->id ?? null);
            case 'materielle':
                return isset($reservation->user_id) && $reservation->user_id === ($user->id ?? null);
            case 'guides':
                return isset($reservation->reserver_id) && $reservation->reserver_id === ($user->id ?? null);
            default:
                return false;
        }
    }

    private function handleEventPlaceChange($reservation, $newPlaces)
    {
        $event = Events::findOrFail($reservation->event_id);
        $diff = $newPlaces - $reservation->nbr_place;

        if ($diff > 0 && $event->nbr_place_restante < $diff) {
            throw new \Exception('Pas assez de places disponibles');
        }

        $event->nbr_place_restante -= $diff;
        $event->save();
    }

    private function restoreEventPlaces($reservation)
    {
        $event = Events::find($reservation->event_id);
        if ($event) {
            $event->nbr_place_restante += $reservation->nbr_place;
            $event->save();
        }
    }


        /**
     * Envoie un email de confirmation
     */
    public function sendConfirmationEmail(Request $request, $type, $id)
    {
        if (!array_key_exists($type, self::RESERVATION_TYPES)) {
            return response()->json([
                'success' => false,
                'message' => 'Type de réservation invalide'
            ], 400);
        }

        $modelClass = self::RESERVATION_TYPES[$type];
        
        try {
            $reservation = $modelClass::with($this->getRelationsForType($type))->findOrFail($id);
            
            $email = null;
            if ($type === 'events' && isset($reservation->email)) {
                $email = $reservation->email;
            } elseif (isset($reservation->user) && isset($reservation->user->email)) {
                $email = $reservation->user->email;
            }
            
            if (!$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun email associé à cette réservation'
                ], 400);
            }
            
            // Envoyer l'email de confirmation
            Mail::to($email)->send(new \App\Mail\ReservationConfirmation($reservation, $type));
            
            return response()->json([
                'success' => true,
                'message' => 'Email de confirmation envoyé avec succès'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
    * Crée une nouvelle réservation
     */
    public function store(Request $request, $type)
    {
        if (!array_key_exists($type, self::RESERVATION_TYPES)) {
            return response()->json([
                'success' => false,
                'message' => 'Type de réservation invalide'
            ], 400);
        }

        $rules = [
            'center' => [
                'user_id'     => 'required|exists:users,id',
                'centre_id'   => 'required|exists:centres,id',
                'date_debut'  => 'required|date',
                'date_fin'    => 'required|date|after_or_equal:date_debut',
                'nbr_place'   => 'required|integer|min:1',
                'status'      => 'sometimes|in:pending,approved,rejected,modified,canceled',
                'note'        => 'nullable|string',
                'payments_id' => 'nullable|exists:payments,id',
            ],
            'events' => [
                'user_id'    => 'required|exists:users,id',
                'event_id'   => 'required|exists:events,id',
                'group_id'   => 'required|exists:groups,id',
                'name'       => 'required|string|max:255',
                'email'      => 'nullable|email',
                'phone'      => 'nullable|string|max:20',
                'nbr_place'  => 'required|integer|min:1',
                'status'     => 'sometimes|in:en_attente_paiement,confirmée,en_attente_validation,refusée,annulée_par_utilisateur,annulée_par_organisateur,remboursement_en_attente,remboursée_partielle,remboursée_totale',
                'payment_id' => 'nullable|exists:payments,id',
                'created_by' => 'nullable|exists:users,id',
            ],
            'materielle' => [
                'user_id'        => 'required|exists:users,id',
                'materielle_id'  => 'required|exists:materielles,id',
                'fournisseur_id' => 'required|exists:fournisseurs,id',
                'date_debut'     => 'required|date',
                'date_fin'       => 'required|date|after_or_equal:date_debut',
                'quantite'       => 'required|integer|min:1',
                'montant_payer'  => 'required|numeric|min:0',
                'status'         => 'sometimes|in:pending,approved,rejected,canceled',
                'payments_id'    => 'nullable|exists:payments,id',
            ],
            'guides' => [
                'user_id'    => 'required|exists:users,id',
                'guide_id'   => 'required|exists:users,id',
                'circuit_id' => 'required|exists:circuits,id',
                'date_debut' => 'required|date',
                'date_fin'   => 'nullable|date|after_or_equal:date_debut',
                'type'       => 'nullable|string|max:255',
                'discription'=> 'nullable|string',
                'status'     => 'sometimes|in:pending,approved,rejected,canceled',
            ],
        ];

        $validator = Validator::make($request->all(), $rules[$type]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $validator->validated();

            if ($type === 'guides') {
                // Map frontend field names to actual DB column names
                $guideData = [
                    'reserver_id'   => $data['user_id'],
                    'guide_id'      => $data['guide_id'],
                    'circuit_id'    => $data['circuit_id'],
                    'creation_date' => $data['date_debut'],
                    'type'          => $data['type']        ?? null,
                    'discription'   => $data['discription'] ?? null,
                ];
                // Add status if the column exists
                if (isset($data['status'])) {
                    $guideData['status'] = $data['status'];
                }
                $reservation = Reservation_guide::create($guideData);
            } elseif ($type === 'events') {
                if (!isset($data['status'])) {
                    $data['status'] = 'en_attente_paiement';
                }
                // Check available places
                $event = Events::findOrFail($data['event_id']);
                if ($event->nbr_place_restante < $data['nbr_place']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Pas assez de places disponibles pour cet événement'
                    ], 422);
                }
                $event->nbr_place_restante -= $data['nbr_place'];
                $event->save();

                $reservation = Reservations_events::create($data);
            } else {
                if (!isset($data['status'])) {
                    $data['status'] = 'pending';
                }
                $modelClass = self::RESERVATION_TYPES[$type];
                $reservation = $modelClass::create($data);
            }

            DB::commit();

            $relations = $this->getRelationsForType($type);
            $reservation->load($relations);
            $reservation = $this->enrichReservation($reservation, $type);

            return response()->json([
                'success' => true,
                'message' => 'Réservation créée avec succès',
                'data'    => $reservation
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }


    private function shouldSendEmail($reservation, $type)
    {
        $email = null;
        if ($type === 'events' && isset($reservation->email)) {
            $email = $reservation->email;
        } elseif (isset($reservation->user) && isset($reservation->user->email)) {
            $email = $reservation->user->email;
        }
        
        return $email && filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    private function sendStatusUpdateEmail($reservation, $type, $oldStatus)
    {
        try {
            $email = null;
            if ($type === 'events' && isset($reservation->email)) {
                $email = $reservation->email;
            } elseif (isset($reservation->user) && isset($reservation->user->email)) {
                $email = $reservation->user->email;
            }
            
            if ($email) {
                Mail::to($email)->send(new ReservationStatusUpdated(
                    $reservation,
                    $type,
                    $oldStatus
                ));
            }
        } catch (\Exception $e) {
            \Log::error("Erreur envoi email: " . $e->getMessage());
        }
    }

    private function getApprovedStatus($type)
    {
        return [
            'center' => 'approved',
            'events' => 'confirmée',
            'materielle' => 'approved',
            'guides' => 'approved',
        ][$type];
    }

    private function getRejectedStatus($type)
    {
        return [
            'center' => 'rejected',
            'events' => 'refusée',
            'materielle' => 'rejected',
            'guides' => 'rejected',
        ][$type];
    }

    private function getPendingStatus($type)
    {
        return [
            'center' => 'pending',
            'events' => 'en_attente_validation',
            'materielle' => 'pending',
            'guides' => 'pending',
        ][$type];
    }

    private function getCanceledStatus($type)
    {
        return [
            'center' => 'canceled',
            'events' => 'annulée_par_organisateur',
            'materielle' => 'canceled',
            'guides' => 'canceled',
        ][$type];
    }

    private function paginateCollection($collection, $perPage, $page)
    {
        return $collection->slice(($page - 1) * $perPage, $perPage);
    }

    private function getReservationCounts()
    {
        return [
            'center' => Reservations_centre::count(),
            'events' => Reservations_events::count(),
            'materielle' => Reservations_materielles::count(),
            'guides' => Reservation_guide::count(),
        ];
    }

    private function getReservationsForExport(Request $request)
    {
        $type = $request->type;
        
        if ($type === 'all') {
            $reservations = collect();
            foreach (['center', 'events', 'materielle', 'guides'] as $t) {
                $method = 'get' . ucfirst($t) . 'Reservations';
                if (method_exists($this, $method)) {
                    $reservations = $reservations->concat($this->$method($request));
                }
            }
            return $reservations;
        }

        $method = 'get' . ucfirst($type) . 'Reservations';
        return method_exists($this, $method) ? $this->$method($request) : collect();
    }

    private function exportToCsv($reservations)
    {
        $filename = 'reservations_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($reservations) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'ID',
                'Type',
                'Code',
                'Titre',
                'Client',
                'Email',
                'Date début',
                'Date fin',
                'Capacité',
                'Prix',
                'Statut',
                'Date création'
            ]);

            foreach ($reservations as $r) {
                $clientName = 'N/A';
                $clientEmail = 'N/A';
                
                if (isset($r->user) && isset($r->user->name)) {
                    $clientName = $r->user->name;
                    $clientEmail = $r->user->email ?? 'N/A';
                } elseif (isset($r->name)) {
                    $clientName = $r->name;
                    $clientEmail = $r->email ?? 'N/A';
                }
                
                fputcsv($file, [
                    $r->id,
                    $r->reservation_type ?? 'N/A',
                    $r->code ?? 'N/A',
                    $r->display_name ?? 'N/A',
                    $clientName,
                    $clientEmail,
                    $r->date_debut ?? 'N/A',
                    $r->date_fin ?? 'N/A',
                    $r->capacity ?? $r->nbr_place ?? $r->quantite ?? 'N/A',
                    $r->price ?? $r->montant_payer ?? $r->total_price ?? '0',
                    $r->status ?? 'N/A',
                    isset($r->created_at) ? $r->created_at->format('Y-m-d H:i:s') : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportToExcel($reservations)
    {
        return $this->exportToCsv($reservations);
    }
}