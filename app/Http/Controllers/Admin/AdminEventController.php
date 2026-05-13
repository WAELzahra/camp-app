<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Events;
use App\Models\User;
use App\Models\Reservations_events;
use App\Mail\EventActivated;
use App\Mail\EventDeactivated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AdminEventController extends Controller
{
    /**
     * Liste tous les événements (avec filtres et pagination)
     */
    public function index(Request $request)
    {
        $query = Events::with(['group', 'photos']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('event_type') && $request->event_type !== 'all') {
            $query->where('event_type', $request->event_type);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('start_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('end_date', '<=', $request->date_to);
        }

        $sortBy  = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $events = $query->paginate($request->get('per_page', 20));

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
     * Afficher un événement
     */
    public function show($id)
    {
        $event = Events::with(['group', 'photos', 'reservation'])->find($id);

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }

        return response()->json(['success' => true, 'data' => $event]);
    }

    /**
     * Créer un événement
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id'               => 'required|exists:users,id',
            'title'                  => 'required|string|max:255',
            'description'            => 'nullable|string',
            'event_type'             => 'required|in:camping,hiking,voyage',
            'start_date'             => 'required|date',
            'end_date'               => 'required|date|after_or_equal:start_date',
            'capacity'               => 'required|integer|min:1',
            'price'                  => 'required|numeric|min:0',
            'status'                 => 'sometimes|in:pending,scheduled,ongoing,finished,canceled,postponed,full',
            'address'                => 'nullable|string',
            'tags'                   => 'nullable|array',
            'camping_duration'       => 'nullable|integer|min:1',
            'camping_gear'           => 'nullable|string',
            'is_group_travel'        => 'boolean',
            'difficulty'             => 'nullable|in:easy,moderate,difficult,expert',
            'hiking_duration'        => 'nullable|numeric|min:0',
            'elevation_gain'         => 'nullable|integer|min:0',
            'departure_city'         => 'nullable|string',
            'arrival_city'           => 'nullable|string',
            'departure_time'         => 'nullable|date_format:H:i',
            'estimated_arrival_time' => 'nullable|date_format:H:i',
            'bus_company'            => 'nullable|string',
            'bus_number'             => 'nullable|string',
            'city_stops'             => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $data                    = $validator->validated();
            $data['status']          = $data['status'] ?? 'pending';
            $data['remaining_spots'] = $data['capacity'];
            $data['is_active']       = false;

            $event = Events::create($data);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Événement créé avec succès',
                'data'    => $event->load(['group', 'photos']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()], 500);
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
            'title'                  => 'sometimes|string|max:255',
            'description'            => 'nullable|string',
            'event_type'             => 'sometimes|in:camping,hiking,voyage',
            'start_date'             => 'sometimes|date',
            'end_date'               => 'sometimes|date|after_or_equal:start_date',
            'capacity'               => 'sometimes|integer|min:1',
            'price'                  => 'sometimes|numeric|min:0',
            'status'                 => 'sometimes|in:pending,scheduled,ongoing,finished,canceled,postponed,full',
            'address'                => 'nullable|string',
            'tags'                   => 'nullable|array',
            'camping_duration'       => 'nullable|integer|min:1',
            'camping_gear'           => 'nullable|string',
            'is_group_travel'        => 'boolean',
            'difficulty'             => 'nullable|in:easy,moderate,difficult,expert',
            'hiking_duration'        => 'nullable|numeric|min:0',
            'elevation_gain'         => 'nullable|integer|min:0',
            'departure_city'         => 'nullable|string',
            'arrival_city'           => 'nullable|string',
            'departure_time'         => 'nullable|date_format:H:i',
            'estimated_arrival_time' => 'nullable|date_format:H:i',
            'bus_company'            => 'nullable|string',
            'bus_number'             => 'nullable|string',
            'city_stops'             => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Adjust remaining_spots if capacity changed
        $data = $validator->validated();
        if (isset($data['capacity'])) {
            $reserved = $event->capacity - $event->remaining_spots;
            if ($data['capacity'] < $reserved) {
                return response()->json([
                    'success' => false,
                    'message' => "Impossible de réduire la capacité en dessous des places réservées ({$reserved}).",
                ], 422);
            }
            $data['remaining_spots'] = $data['capacity'] - $reserved;
        }

        DB::beginTransaction();
        try {
            $event->update($data);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Événement mis à jour avec succès',
                'data'    => $event->fresh(['group', 'photos']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Activer un événement (envoie un email au groupe)
     */
    public function activate($id)
    {
        $event = Events::with('group')->find($id);

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }
        if ($event->is_active) {
            return response()->json(['success' => false, 'message' => 'Événement déjà activé'], 422);
        }

        $event->is_active = true;
        $event->status    = 'scheduled';
        $event->save();

        if ($event->group && $event->group->email) {
            try { Mail::to($event->group->email)->send(new EventActivated($event)); } catch (\Exception $e) {}
        }

        return response()->json(['success' => true, 'message' => 'Événement activé avec succès']);
    }

    /**
     * Désactiver un événement
     */
    public function deactivate($id)
    {
        $event = Events::with('group')->find($id);

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }
        if (!$event->is_active) {
            return response()->json(['success' => false, 'message' => 'Événement déjà désactivé'], 422);
        }

        $event->is_active = false;
        $event->status    = 'pending';
        $event->save();

        if ($event->group && $event->group->email) {
            try { Mail::to($event->group->email)->send(new EventDeactivated($event)); } catch (\Exception $e) {}
        }

        return response()->json(['success' => true, 'message' => 'Événement désactivé avec succès']);
    }

    /**
     * Annuler un événement et marquer les réservations confirmées
     */
    public function cancelEvent($id)
    {
        $event = Events::find($id);

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }

        DB::beginTransaction();
        try {
            $event->status    = 'canceled';
            $event->is_active = false;
            $event->save();

            Reservations_events::where('event_id', $id)
                ->whereIn('status', ['confirmée', 'en_attente_validation'])
                ->update(['status' => 'annulée_par_organisateur']);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Événement annulé avec succès']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()], 500);
        }
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

            return response()->json(['success' => true, 'message' => 'Événement supprimé avec succès']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Réservations d'un événement (avec filtres)
     */
    public function reservations($id, Request $request)
    {
        $event = Events::find($id);

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }

        $query = Reservations_events::with(['user', 'payment'])
            ->where('event_id', $id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $reservations = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $reservations->items(),
            'meta'    => [
                'current_page' => $reservations->currentPage(),
                'total'        => $reservations->total(),
                'last_page'    => $reservations->lastPage(),
            ],
        ]);
    }

    /**
     * Statistiques d'un événement
     */
    public function statistics($id)
    {
        $event = Events::find($id);

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }

        $reservations = Reservations_events::where('event_id', $id);

        $totalReservations = (clone $reservations)->count();
        $totalParticipants = (clone $reservations)->sum('nbr_place');
        $byStatus = (clone $reservations)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'success' => true,
            'data'    => [
                'total_reservations' => $totalReservations,
                'total_participants' => $totalParticipants,
                'revenue'            => $totalParticipants * $event->price,
                'available_spots'    => $event->remaining_spots,
                'capacity'           => $event->capacity,
                'reservation_rate'   => $event->capacity > 0
                    ? round(($totalParticipants / $event->capacity) * 100, 2)
                    : 0,
                'by_status'          => $byStatus,
            ],
        ]);
    }

    /**
     * Exporter les réservations en CSV
     */
    public function exportReservationsCsv($id)
    {
        $event = Events::find($id);

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }

        $reservations = Reservations_events::with('user')->where('event_id', $id)->get();

        $filename = "reservations_event_{$id}.csv";
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($reservations) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Nom', 'Email', 'Téléphone', 'Places', 'Statut', 'Date']);

            foreach ($reservations as $res) {
                $firstName = $res->user ? $res->user->first_name . ' ' . $res->user->last_name : 'N/A';
                fputcsv($file, [
                    $res->id,
                    $res->name ?? $firstName,
                    $res->email ?? ($res->user->email ?? 'N/A'),
                    $res->phone ?? 'N/A',
                    $res->nbr_place,
                    $res->status,
                    $res->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Récupérer les groupes organisateurs
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

        return response()->json(['success' => true, 'data' => $groups]);
    }
}
