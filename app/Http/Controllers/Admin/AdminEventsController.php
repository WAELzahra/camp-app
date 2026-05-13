<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Events;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AdminEventsController extends Controller
{
    /**
     * Liste tous les événements (avec filtres)
     */
    public function index(Request $request)
    {
        $query = Events::with(['group', 'photos']);

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('event_type') && $request->event_type !== 'all') {
            $query->where('event_type', $request->event_type);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('start_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('end_date', '<=', $request->date_to);
        }

        $sortBy  = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = $request->get('per_page', 20);
        $events  = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $events->items(),
            'meta'    => [
                'current_page' => $events->currentPage(),
                'per_page'     => $events->perPage(),
                'total'        => $events->total(),
                'last_page'    => $events->lastPage(),
            ],
        ]);
    }

    /**
     * Statistiques globales des événements
     */
    public function stats()
    {
        $total     = Events::count();
        $active    = Events::where('is_active', true)->count();
        $byStatus  = Events::select('status', DB::raw('count(*) as total'))
                           ->groupBy('status')
                           ->pluck('total', 'status');
        $byType    = Events::select('event_type', DB::raw('count(*) as total'))
                           ->groupBy('event_type')
                           ->pluck('total', 'event_type');

        return response()->json([
            'success' => true,
            'data'    => [
                'total'      => $total,
                'active'     => $active,
                'by_status'  => $byStatus,
                'by_type'    => $byType,
            ],
        ]);
    }

    /**
     * Afficher un événement
     */
    public function show($id)
    {
        $event = Events::with(['group', 'photos', 'reservation'])->find($id);

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $event,
        ]);
    }

    /**
     * Créer un événement
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id'                 => 'required|exists:users,id',
            'title'                    => 'required|string|max:255',
            'description'              => 'nullable|string',
            'event_type'               => 'required|in:camping,hiking,voyage',
            'start_date'               => 'required|date',
            'end_date'                 => 'required|date|after_or_equal:start_date',
            'capacity'                 => 'required|integer|min:1',
            'price'                    => 'required|numeric|min:0',
            'status'                   => 'sometimes|in:pending,scheduled,ongoing,finished,canceled,postponed,full',
            'address'                  => 'nullable|string',
            'tags'                     => 'nullable|array',
            // Camping
            'camping_duration'         => 'nullable|integer|min:1',
            'camping_gear'             => 'nullable|string',
            'is_group_travel'          => 'boolean',
            // Hiking
            'difficulty'               => 'nullable|in:easy,moderate,difficult,expert',
            'hiking_duration'          => 'nullable|numeric|min:0',
            'elevation_gain'           => 'nullable|integer|min:0',
            // Voyage
            'departure_city'           => 'nullable|string',
            'arrival_city'             => 'nullable|string',
            'departure_time'           => 'nullable|date_format:H:i',
            'estimated_arrival_time'   => 'nullable|date_format:H:i',
            'bus_company'              => 'nullable|string',
            'bus_number'               => 'nullable|string',
            'city_stops'               => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $data = $validator->validated();

            $data['status']          = $data['status'] ?? 'pending';
            $data['remaining_spots'] = $data['capacity'];
            $data['is_active']       = $data['is_active'] ?? false;

            $event = Events::create($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Événement créé avec succès',
                'data'    => $event->load(['group', 'photos']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour un événement
     */
    public function update(Request $request, $id)
    {
        $event = Events::find($id);

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title'                    => 'sometimes|string|max:255',
            'description'              => 'nullable|string',
            'event_type'               => 'sometimes|in:camping,hiking,voyage',
            'start_date'               => 'sometimes|date',
            'end_date'                 => 'sometimes|date|after_or_equal:start_date',
            'capacity'                 => 'sometimes|integer|min:1',
            'price'                    => 'sometimes|numeric|min:0',
            'status'                   => 'sometimes|in:pending,scheduled,ongoing,finished,canceled,postponed,full',
            'address'                  => 'nullable|string',
            'tags'                     => 'nullable|array',
            'camping_duration'         => 'nullable|integer|min:1',
            'camping_gear'             => 'nullable|string',
            'is_group_travel'          => 'boolean',
            'difficulty'               => 'nullable|in:easy,moderate,difficult,expert',
            'hiking_duration'          => 'nullable|numeric|min:0',
            'elevation_gain'           => 'nullable|integer|min:0',
            'departure_city'           => 'nullable|string',
            'arrival_city'             => 'nullable|string',
            'departure_time'           => 'nullable|date_format:H:i',
            'estimated_arrival_time'   => 'nullable|date_format:H:i',
            'bus_company'              => 'nullable|string',
            'bus_number'               => 'nullable|string',
            'city_stops'               => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $event->update($validator->validated());
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Événement mis à jour avec succès',
                'data'    => $event->fresh(['group', 'photos']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour uniquement le statut d'un événement
     */
    public function updateStatus(Request $request, $id)
    {
        $event = Events::find($id);

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status'    => 'required|in:pending,scheduled,ongoing,finished,canceled,postponed,full',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $event->status = $request->status;

        // Auto-sync is_active with status so the public detail endpoint can find the event.
        // An explicit is_active value in the request takes precedence.
        if ($request->has('is_active')) {
            $event->is_active = $request->is_active;
        } else {
            $event->is_active = in_array($request->status, ['scheduled', 'ongoing', 'full']);
        }

        $event->save();

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour avec succès',
            'data'    => $event->only(['id', 'status', 'is_active']),
        ]);
    }

    /**
     * Supprimer un événement
     */
    public function destroy($id)
    {
        $event = Events::find($id);

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }

        DB::beginTransaction();
        try {
            $event->reservation()->delete();
            $event->photos()->delete();
            $event->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Événement supprimé avec succès',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Récupérer les groupes (utilisateurs avec role group)
     */
    public function getGroups(Request $request)
    {
        $groups = User::whereHas('role', fn($q) => $q->where('name', 'groupe'))
            ->when($request->search, function ($query, $search) {
                $query->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            })
            ->select('id', 'first_name', 'last_name', 'email')
            ->limit(100)
            ->get()
            ->map(fn($u) => [
                'id'    => $u->id,
                'name'  => $u->first_name . ' ' . $u->last_name,
                'email' => $u->email,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $groups,
        ]);
    }
    public function activate($id)
    {
        $event = Events::find($id);
        
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }
        
        $event->is_active = true;
        $event->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Événement activé avec succès',
            'data' => $event
        ]);
    }
    
    /**
     * Désactive un événement
     */
    public function deactivate($id)
    {
        $event = Events::find($id);
        
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }
        
        $event->is_active = false;
        $event->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Événement désactivé avec succès',
            'data' => $event
        ]);
    }
    
    /**
     * Annule un événement
     */
    public function cancelEvent($id)
    {
        $event = Events::find($id);
        
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }
        
        $event->status = 'canceled';
        $event->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Événement annulé avec succès',
            'data' => $event
        ]);
    }
    
    /**
     * Récupère les réservations d'un événement
     */
    public function reservations($id)
    {
        $event = Events::with(['reservation.user'])->find($id);
        
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }
        
        $reservations = $event->reservation->map(function($reservation) {
            return [
                'id' => $reservation->id,
                'user_id' => $reservation->user_id,
                'event_id' => $reservation->event_id,
                'name' => $reservation->user->first_name . ' ' . $reservation->user->last_name,
                'email' => $reservation->user->email,
                'phone' => $reservation->user->phone_number ?? '',
                'nbr_place' => $reservation->nbr_place,
                'status' => $reservation->status,
                'created_at' => $reservation->created_at,
                'user' => [
                    'id' => $reservation->user->id,
                    'first_name' => $reservation->user->first_name,
                    'last_name' => $reservation->user->last_name,
                    'email' => $reservation->user->email,
                    'phone_number' => $reservation->user->phone_number,
                    'ville' => $reservation->user->ville,
                ]
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $reservations
        ]);
    }
    
    /**
     * Statistiques d'un événement
     */
    public function statistics($id)
    {
        $event = Events::with('reservation')->find($id);
        
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }
        
        $reservations = $event->reservation;
        
        $stats = [
            'total_reservations' => $reservations->count(),
            'total_places' => $reservations->sum('nbr_place'),
            'by_status' => $reservations->groupBy('status')->map->count(),
            'revenue' => $reservations->sum(function($r) use ($event) {
                return $r->nbr_place * $event->price;
            }),
            'remaining_spots' => $event->remaining_spots,
            'capacity' => $event->capacity,
        ];
        
        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
    
    /**
     * Exporte les réservations en CSV
     */
    public function exportReservationsCsv($id)
    {
        $event = Events::with('reservation.user')->find($id);
        
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="reservations_' . $event->id . '.csv"',
        ];
        
        $callback = function() use ($event) {
            $file = fopen('php://output', 'w');
            
            // Headers
            fputcsv($file, ['ID', 'Nom', 'Email', 'Téléphone', 'Nombre de places', 'Statut', 'Date de réservation']);
            
            // Data
            foreach ($event->reservation as $reservation) {
                fputcsv($file, [
                    $reservation->id,
                    $reservation->user->first_name . ' ' . $reservation->user->last_name,
                    $reservation->user->email,
                    $reservation->user->phone_number ?? '',
                    $reservation->nbr_place,
                    $reservation->status,
                    $reservation->created_at,
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
}

