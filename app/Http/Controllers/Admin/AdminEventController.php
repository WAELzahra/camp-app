<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Events;
use Illuminate\Http\Request;
use App\Models\Reservations_events;
use App\Mail\EventActivated;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use App\Models\Users; 
use App\Models\Circuit; 


class AdminEventController extends Controller
{

// Affiche la liste des événements avec pagination    
    public function index()
    {
        return response()->json(Events::latest()->paginate(10));
    }

    // Affiche les événements d'un groupe spécifique
    public function show($id)
    {
        $event = Events::with('group')->find($id);
        return $event ? response()->json($event) : response()->json(['message' => 'Événement introuvable'], 404);
    }
//// Permet à un administrateur de créer un nouvel événement
//    public function store(Request $request)
// {
//     // Optionnel : vérifier que l'utilisateur est admin
//     if (auth()->user()->role->name !== 'admin') {
//         return response()->json(['message' => 'Accès refusé : seuls les admins peuvent créer des événements.'], 403);
//     }

//     // Validation des données, ici on demande group_id car admin peut créer pour un groupe spécifique
//     $validated = $request->validate([
//         'title' => 'required|string|max:255',
//         'description' => 'nullable|string',
//         'category' => 'required|string',
//         'date_sortie' => 'required|date',
//         'date_retoure' => 'required|date|after_or_equal:date_sortie',
//         'ville_passente' => 'nullable|array',
//         'nbr_place_total' => 'required|integer|min:1',
//         'prix_place' => 'required|numeric|min:0',
//         'circuit_id' => 'required|exists:circuits,id',
//         'group_id' => 'required|exists:users,id', // vérifie que le groupe existe
//         'status' => 'nullable|string|in:pending,active,canceled', // statuts possibles, à adapter
//         'is_active' => 'nullable|boolean',
//     ]);

//     $event = Events::create([
//         'title' => $validated['title'],
//         'description' => $validated['description'] ?? null,
//         'category' => $validated['category'],
//         'date_sortie' => $validated['date_sortie'],
//         'date_retoure' => $validated['date_retoure'],
//         'ville_passente' => $validated['ville_passente'] ? json_encode($validated['ville_passente']) : null,
//         'nbr_place_total' => $validated['nbr_place_total'],
//         'nbr_place_restante' => $validated['nbr_place_total'],
//         'prix_place' => $validated['prix_place'],
//         'circuit_id' => $validated['circuit_id'],
//         'group_id' => $validated['group_id'],
//         'status' => $validated['status'] ?? 'pending',
//         'is_active' => $validated['is_active'] ?? false,
//     ]);

//     return response()->json([
//         'message' => 'Événement créé avec succès par l\'admin.',
//         'event' => $event
//     ], 201);
// }

public function store(Request $request)
{
    if (auth()->user()->role->name !== 'admin') {
        return response()->json(['message' => 'Accès refusé : seuls les admins peuvent créer des événements.'], 403);
    }

    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category' => 'required|string',
        'date_sortie' => 'required|date',
        'date_retoure' => 'required|date|after_or_equal:date_sortie',
        'ville_passente' => 'nullable|array',
        'nbr_place_total' => 'required|integer|min:1',
        'prix_place' => 'required|numeric|min:0',
        'group_id' => 'required|exists:users,id',
        'status' => 'nullable|string|in:pending,active,canceled',
        'is_active' => 'nullable|boolean',

        // Soit circuit_id, soit création du circuit avec ces champs
        'circuit_id' => 'nullable|exists:circuits,id',
        'adresse_debut_circuit' => 'required_without:circuit_id|string',
        'adresse_fin_circuit' => 'required_without:circuit_id|string',
        'description_circuit' => 'nullable|string',
        'difficulty' => 'nullable|in:facile,moyenne,difficile',
        'distance_km' => 'nullable|numeric|min:0',
        'estimation_temps' => 'nullable|string',
        'danger_level' => 'nullable|in:low,moderate,high,extreme',
    ]);

    // Si circuit_id n’est pas fourni, on crée un circuit
    if (empty($validated['circuit_id'])) {
        $circuit = Circuit::create([
            'adresse_debut_circuit' => $validated['adresse_debut_circuit'],
            'adresse_fin_circuit' => $validated['adresse_fin_circuit'],
            'description' => $validated['description_circuit'] ?? null,
            'difficulty' => $validated['difficulty'] ?? null,
            'distance_km' => $validated['distance_km'] ?? null,
            'estimation_temps' => $validated['estimation_temps'] ?? null,
            'danger_level' => $validated['danger_level'] ?? null,
        ]);

        $circuitId = $circuit->id;
    } else {
        $circuitId = $validated['circuit_id'];
    }

    $event = Events::create([
        'title' => $validated['title'],
        'description' => $validated['description'] ?? null,
        'category' => $validated['category'],
        'date_sortie' => $validated['date_sortie'],
        'date_retoure' => $validated['date_retoure'],
        'ville_passente' => isset($validated['ville_passente']) ? json_encode($validated['ville_passente']) : null,
        'nbr_place_total' => $validated['nbr_place_total'],
        'nbr_place_restante' => $validated['nbr_place_total'],
        'prix_place' => $validated['prix_place'],
        'circuit_id' => $circuitId,
        'group_id' => $validated['group_id'],
        'status' => $validated['status'] ?? 'pending',
        'is_active' => $validated['is_active'] ?? false,
    ]);

    return response()->json([
        'message' => 'Événement créé avec succès par l\'admin.',
        'event' => $event,
    ], 201);
}


// Met à jour un événement existant
   public function update(Request $request, $id)
{
    $event = Events::findOrFail($id);

    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category' => 'required|string',
        'date_sortie' => 'required|date',
        'date_retoure' => 'required|date|after_or_equal:date_sortie',
        'ville_passente' => 'nullable|array',
        'nbr_place_total' => 'required|integer|min:1',
        'prix_place' => 'required|numeric|min:0',
        'circuit_id' => 'required|exists:circuits,id',
        'status' => 'required|string',
        'is_active' => 'required|boolean',
    ]);

    $old_total = $event->nbr_place_total;
    $new_total = $validated['nbr_place_total'];

    // Calcul du nombre de places déjà réservées
    $places_reservees = $old_total - $event->nbr_place_restante;

    // Si nouvelle capacité inférieure aux places déjà réservées, refuse la mise à jour
    if ($new_total < $places_reservees) {
        return response()->json([
            'message' => "Impossible de réduire le nombre total de places en dessous des places déjà réservées ($places_reservees)."
        ], 422);
    }

    $event->title = $validated['title'];
    $event->description = $validated['description'] ?? null;
    $event->category = $validated['category'];
    $event->date_sortie = $validated['date_sortie'];
    $event->date_retoure = $validated['date_retoure'];
    $event->ville_passente = json_encode($validated['ville_passente']);
    $event->nbr_place_total = $new_total;
    // Ajuste nbr_place_restante pour correspondre à la nouvelle capacité moins les réservations
    $event->nbr_place_restante = $new_total - $places_reservees;
    $event->prix_place = $validated['prix_place'];
    $event->circuit_id = $validated['circuit_id'];
    $event->status = $validated['status'];
    $event->is_active = $validated['is_active'];

    $event->save();

    return response()->json([
        'message' => 'Événement mis à jour.',
        'event' => $event
    ]);
}

// Supprime un événement
    public function destroy($id)
    {
        $event = Events::find($id);

        if (!$event) {
            return response()->json(['message' => 'Événement introuvable.'], 404);
        }

        $event->delete();

        return response()->json(['message' => 'Événement supprimé avec succès.']);
    }

// Active un événement (seul un admin peut le faire)
    public function activate(Request $request, $id)
    {
        $user = auth()->user();
        if ($user->role->name !== 'admin') {
            return response()->json(['message' => 'Seul un administrateur peut activer un événement.'], 403);
        }

        $event = Events::with('group')->findOrFail($id);

        if ($event->is_active) {
            return response()->json(['message' => 'Événement déjà activé.']);
        }

        $event->is_active = true;
        $event->save();

        if ($event->group && $event->group->email) {
            Mail::to($event->group->email)->send(new EventActivated($event));
        }

        return response()->json([
            'message' => 'Événement activé avec succès.',
            'event' => $event
        ]);
    }

// Affiche les réservations d'un événement
    public function reservations($eventId, Request $request)
    {
        $query = Reservations_events::with('user', 'payment')
            ->where('event_id', $eventId);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_start')) {
            $query->whereDate('created_at', '>=', $request->date_start);
        }

        if ($request->has('date_end')) {
            $query->whereDate('created_at', '<=', $request->date_end);
        }

        return response()->json($query->latest()->paginate(15));
    }

// Affiche les statistiques d'un événement
    public function statistics($eventId)
    {
        $event = Events::findOrFail($eventId);

        $total = Reservations_events::where('event_id', $eventId)->count();
        $confirmes = Reservations_events::where('event_id', $eventId)->where('status', 'confirmé')->count();
        $annules = Reservations_events::where('event_id', $eventId)->where('status', 'annulé')->count();
        $attente = Reservations_events::where('event_id', $eventId)->where('status', 'en_attente')->count();

        return response()->json([
            'event_id' => $eventId,
            'total' => $total,
            'confirmes' => $confirmes,
            'annules' => $annules,
            'en_attente' => $attente,
            'places_restantes' => $event->nbr_place_restante,
        ]);
    }

// Annule un événement et rembourse les réservations confirmées
    public function cancelEvent($eventId)
    {
        $event = Events::findOrFail($eventId);

        $event->status = 'canceled';
        $event->is_active = false;
        $event->save();

        $reservations = Reservations_events::where('event_id', $eventId)
            ->where('status', 'confirmé')
            ->get();

        foreach ($reservations as $reservation) {
            $reservation->status = 'annulé';
            $reservation->save();

            if ($reservation->payment) {
                $reservation->payment->status = 'refunded';
                $reservation->payment->save();
            }
        }

        return response()->json([
            'message' => 'Événement annulé et réservations remboursées.'
        ]);
    }

// Exporte les réservations d'un événement en CSV
    public function exportReservationsCsv($eventId)
    {
        $reservations = Reservations_events::where('event_id', $eventId)->with('user')->get();

        $csvData = [];
        $csvData[] = ['Nom', 'Email', 'Téléphone', 'Status', 'Nombre de places', 'Date'];

        foreach ($reservations as $res) {
            $csvData[] = [
                $res->user->name ?? '',
                $res->email ?? '',
                $res->user->phone ?? '',
                $res->status,
                $res->nbr_place,
                $res->created_at->format('Y-m-d H:i')
            ];
        }

        $filename = "reservations_event_$eventId.csv";

        $handle = fopen('php://temp', 'r+');
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);

        return Response::stream(function () use ($handle) {
            fpassthru($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
        ]);
    }
}