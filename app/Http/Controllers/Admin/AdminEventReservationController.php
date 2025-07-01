<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\Reservations_events;
use App\Models\Events;
use App\Models\User;
use App\Mail\ReservationStatusUpdated;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Controller;
use App\Models\Feedbacks;
use Illuminate\Support\Facades\Auth;


class AdminEventReservationController extends Controller
{

// Voir  listReservations
    public function listReservations(Request $request)
{
    $query = Reservations_events::with(['event', 'user', 'payment']);

    // Filtres optionnels
    if ($request->has('event_id')) {
        $query->where('event_id', $request->event_id);
    }
    if ($request->has('user_id')) {
        $query->where('user_id', $request->user_id);
    }
    if ($request->has('group_id')) {
        // Filtrer les réservations par groupe via l'événement
        $query->whereHas('event', function($q) use ($request) {
            $q->where('group_id', $request->group_id);
        });
    }
    if ($request->has('status')) {
        $query->where('status', $request->status);
    }

    $reservations = $query->orderBy('created_at', 'desc')->paginate(20);

    return response()->json($reservations);
}

// Voir détails d'une réservation 
public function getReservationDetails($id)
{
    $reservation = Reservations_events::with(['user', 'event', 'payment'])->findOrFail($id);

    return response()->json($reservation);
}

// Mettre à jour une réservation
public function update(Request $request, $reservationId)
{
    $reservation = Reservations_events::findOrFail($reservationId);

    // 🔐 Autorisation selon rôle :
    $user = Auth::user();

   if ($user->role->name !== 'admin' && $reservation->group_id !== $user->id) {
    return response()->json(['message' => 'Non autorisé à modifier cette réservation.'], 403);
}

    $data = $request->json()->all();

    if (empty($data)) {
        return response()->json(['message' => 'Aucune donnée reçue pour la mise à jour.'], 400);
    }

    $rules = [
        'status' => 'sometimes|in:confirmé,refusé,en_attente,annulé',
        'nbr_place' => 'sometimes|integer|min:1',
        'name' => 'sometimes|string',
        'email' => 'sometimes|email',
        'phone' => 'sometimes|string',
    ];

    $validator = \Validator::make($data, $rules);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors(), 'message' => 'Validation échouée.'], 422);
    }

    if (isset($data['nbr_place'])) {
        $event = Events::findOrFail($reservation->event_id);
        $diff = $data['nbr_place'] - $reservation->nbr_place;

        if ($diff > 0 && $event->nbr_place_restante < $diff) {
            return response()->json(['message' => 'Pas assez de places disponibles.'], 400);
        }

        $event->nbr_place_restante -= $diff;
        $event->save();
    }

    $reservation->fill($data);
    $reservation->save();

    return response()->json([
        'message' => $user->role === 'admin'
            ? 'Réservation mise à jour avec succès par l\'admin.'
            : 'Réservation mise à jour avec succès.',
        'reservation' => $reservation->fresh(),
    ]);
}

// Supprimer une réservation
public function deleteReservation($id)
{
    $reservation = Reservations_events::findOrFail($id);
    $reservation->delete();

    return response()->json(['message' => 'Réservation supprimée.']);
}

// Exporter les réservations au format CSV
public function exportReservations(Request $request)
{
    $reservations = Reservations_events::with(['user', 'event', 'payment'])
        ->when($request->event_id, fn($q) => $q->where('event_id', $request->event_id))
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->get();

    $csv = "Nom,Email,Événement,Nbr de places,Statut\n";
    foreach ($reservations as $r) {
        $csv .= "{$r->name},{$r->email},{$r->event->title},{$r->nbr_place},{$r->status}\n";
    }

    return response($csv)
        ->header('Content-Type', 'text/csv')
        ->header('Content-Disposition', 'attachment; filename="reservations.csv"');
}

// Ajouter une réservation manuellement
public function addManualParticipant(Request $request, $eventId)
{
    // Validation des champs reçus
    $request->validate([
        'name' => 'required|string',
        'email' => 'nullable|email',
        'phone' => 'nullable|string',
        'nbr_place' => 'required|integer|min:1',
    ]);

    // Récupérer l'événement ou 404 si introuvable
    $event = Events::findOrFail($eventId);

    // Vérifier la disponibilité des places restantes
    if ($event->nbr_place_restante < $request->nbr_place) {
        return response()->json(['message' => 'Nombre de places insuffisant pour cet événement.'], 400);
    }

    // Créer la réservation / participant
    $reservation = Reservations_events::create([
        'event_id' => $eventId,
        'group_id' => auth()->id(),          // Id du groupe ou user connecté
        'name' => $request->name,
        'email' => $request->email,
        'phone' => $request->phone,
        'nbr_place' => $request->nbr_place,
        'status' => 'confirmé',
        'created_by' => auth()->id(),
    ]);

    // Mettre à jour le nombre de places restantes
    $event->nbr_place_restante -= $request->nbr_place;
    $event->save();

    // Réponse JSON
    return response()->json([
        'message' => 'Participant ajouté avec succès.',
        'data' => $reservation,
    ]);
}

// Statistiques des réservations
public function stats(Request $request)
{
    $stats = Reservations_events::selectRaw('status, COUNT(*) as count')
        ->when($request->event_id, fn($q) => $q->where('event_id', $request->event_id))
        ->groupBy('status')
        ->get();

    return response()->json($stats);
}



}
