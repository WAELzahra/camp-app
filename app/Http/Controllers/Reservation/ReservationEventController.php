<?php

namespace App\Http\Controllers\Reservation;

use App\Models\Reservations_events;
use App\Models\Events;
use App\Models\Payments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReservationStatusUpdated;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Http\Controllers\Controller;



class ReservationEventController extends Controller
{
    /**
     * Lister les participants d'un événement avec option de filtrage par statut.
     */
    public function participants($eventId, Request $request)
    {
        $event = Events::find($eventId);
        if (!$event) {
            return response()->json(['message' => 'Événement non trouvé'], 404);
        }

        $status = $request->query('status');

        $query = Reservations_events::with('user')->where('event_id', $eventId);

        if ($status) {
            $query->where('status', $status);
        }

        $participants = $query->get();

        return response()->json([
            'event_id' => $eventId,
            'event_title' => $event->title,
            'participants' => $participants,
        ]);
    }

    /**
     * Statistiques du nombre de participants par statut pour un événement.
     */
    public function participantStats($eventId)
    {
        $event = Events::find($eventId);
        if (!$event) {
            return response()->json(['message' => 'Événement non trouvé'], 404);
        }

        $stats = Reservations_events::where('event_id', $eventId)
            ->select('status', \DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        return response()->json([
            'event_id' => $eventId,
            'event_title' => $event->title,
            'stats' => $stats,
        ]);
    }

    /**
     * Ajouter manuellement un participant à un événement.
     */
    public function addManualParticipant(Request $request, $eventId)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'nbr_place' => 'required|integer|min:1',
        ]);

        $event = Events::findOrFail($eventId);

        if ($event->nbr_place_restante < $request->nbr_place) {
            return response()->json(['message' => 'Nombre de places insuffisant pour cet événement.'], 400);
        }

        $reservation = Reservations_events::create([
            'event_id' => $eventId,
            'group_id' => auth()->id(),
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'nbr_place' => $request->nbr_place,
            'status' => 'confirmé',
            'created_by' => auth()->id(),
        ]);

        $event->nbr_place_restante -= $request->nbr_place;
        $event->save();

        return response()->json([
            'message' => 'Participant ajouté avec succès.',
            'data' => $reservation,
        ]);
    }

    /**
     * Supprimer une réservation/participant.
     */
    public function destroy($reservationId)
    {
        $reservation = Reservations_events::find($reservationId);

        if (!$reservation) {
            return response()->json(['message' => 'Réservation introuvable'], 404);
        }

        $reservation->delete();

        return response()->json(['message' => 'Réservation supprimée avec succès']);
    }

    /**
     * Recherche avancée de participants sur un événement.
     */
    public function search(Request $request, $eventId)
    {
        $query = Reservations_events::where('event_id', $eventId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }
        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }

        $results = $query->get();

        if ($results->isEmpty()) {
            return response()->json([
                'message' => 'Aucun participant trouvé pour cet événement avec les critères spécifiés.'
            ], 404);
        }

        return response()->json($results);
    }

    /**
     * Mettre à jour un participant ajouté manuellement, avec gestion des places.
     */
public function updateManualParticipant(Request $request, $reservationId)
{
    $reservation = Reservations_events::findOrFail($reservationId);

    if ($reservation->created_by !== auth()->id()) {
        return response()->json(['message' => 'Non autorisé à modifier ce participant.'], 403);
    }

    // Récupérer les données JSON brutes
    $data = $request->json()->all();

    if (empty($data)) {
        return response()->json(['message' => 'Aucune donnée reçue pour la mise à jour.'], 400);
    }

    // Valider uniquement les champs présents
    $rules = [
        'name' => 'sometimes|string',
        'email' => 'sometimes|email',
        'phone' => 'sometimes|string',
        'nbr_place' => 'sometimes|integer|min:1',
    ];

    $validator = \Validator::make($data, $rules);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Construire le tableau des champs à mettre à jour
    $dataToUpdate = [];

    foreach (['name', 'email', 'phone', 'nbr_place'] as $field) {
        if (array_key_exists($field, $data)) {
            $dataToUpdate[$field] = $data[$field];
        }
    }

    if (empty($dataToUpdate)) {
        return response()->json(['message' => 'Aucune donnée à mettre à jour.'], 400);
    }

    // Si nbr_place est modifié, vérifier la disponibilité des places restantes
    if (isset($dataToUpdate['nbr_place'])) {
        $diff = $dataToUpdate['nbr_place'] - $reservation->nbr_place;
        $event = Events::findOrFail($reservation->event_id);

        if ($diff > 0 && $event->nbr_place_restante < $diff) {
            return response()->json(['message' => 'Pas assez de places disponibles.'], 400);
        }

        $event->nbr_place_restante -= $diff;
        $event->save();
    }

    // Mise à jour en base
    $reservation->update($dataToUpdate);

    return response()->json([
        'message' => 'Participant mis à jour avec succès.',
        'participant' => $reservation->fresh(),
    ]);
}


    /**
     * Créer une réservation avec paiement (intégration à compléter).
     * Créer une réservation avec le statut "en_attente_paiement".
     */
    public function createReservationWithPayment(Request $request)
    {
        $request->validate([
            'event_id' => 'required|exists:events,id',
            'nbr_place' => 'required|integer|min:1',
            'group_id' => 'required|exists:users,id',
        ]);

        $user = auth()->user();

        $reservation = Reservations_events::create([
            'user_id' => $user->id,
            'group_id' => $request->group_id,
            'event_id' => $request->event_id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'nbr_place' => $request->nbr_place,
            'status' => 'en_attente_paiement',
            'created_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Réservation créée en attente de paiement.',
            'reservation' => $reservation,
        ], 201);
    }

    /**
     * Afficher une réservation avec ses détails.
     */
    public function show($reservationId)
    {
        $reservation = Reservations_events::with('event')->findOrFail($reservationId);

        return response()->json($reservation);
    }

    public function toutesMesReservations(Request $request)
{
    $user = Auth::user();

    $query = Reservations_events::where('group_id', $user->id)
        ->with(['event', 'user', 'payment']);

    // 🔍 Filtres dynamiques
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    if ($request->filled('name')) {
        $query->where('name', 'like', '%' . $request->name . '%');
    }

    if ($request->filled('email')) {
        $query->where('email', 'like', '%' . $request->email . '%');
    }

    if ($request->filled('phone')) {
        $query->where('phone', 'like', '%' . $request->phone . '%');
    }

    if ($request->filled('from')) {
        $query->whereDate('created_at', '>=', $request->from);
    }

    if ($request->filled('to')) {
        $query->whereDate('created_at', '<=', $request->to);
    }

    // 🔄 Récupération des données paginées
    $reservations = $query->orderBy('created_at', 'desc')->paginate(10);

    // ✅ Vérification si vide
    if ($reservations->isEmpty()) {
        return response()->json([
            'message' => 'Aucune réservation trouvée.'
        ], 200); // ou 204 si tu préfères
    }

    return response()->json($reservations);
}

    public function reservationsParEvenement($event_id)
    {
        $user = Auth::user();

        $reservations = Reservations_events::where('group_id', $user->id)
            ->where('event_id', $event_id)
            ->with(['user', 'payment'])
            ->orderByDesc('created_at')
            ->get();

        if ($reservations->isEmpty()) {
            return response()->json(['message' => 'Aucune réservation trouvée pour cet événement.'], 200);
        }

        return response()->json($reservations);
    }

    // Export des réservations (CSV)
    public function exportReservations(Request $request)
    {
        $user = Auth::user();

        $reservations = Reservations_events::where('group_id', $user->id)->with(['event'])->get();

        if ($reservations->isEmpty()) {
            return response()->json(['message' => 'Aucune réservation à exporter.'], 200);
        }

        $csvData = [];
        $csvData[] = ["Nom", "Email", "Téléphone", "Événement", "Statut", "Places", "Date"];

        foreach ($reservations as $r) {
            $csvData[] = [
                $r->name,
                $r->email,
                $r->phone,
                optional($r->event)->title,
                $r->status,
                $r->nbr_place,
                $r->created_at,
            ];
        }

        $filename = 'reservations_export_' . now()->timestamp . '.csv';
        $handle = fopen(storage_path("app/$filename"), 'w');
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return response()->download(storage_path("app/$filename"))->deleteFileAfterSend(true);
    }

    //  Mise à jour du statut (confirmé/refusé)
    public function updateStatus(Request $request, $id)
{
    $reservation = Reservations_events::findOrFail($id);

    if ($reservation->group_id !== Auth::id()) {
        return response()->json(['message' => 'Non autorisé.'], 403);
    }

    // Récupérer les données JSON brutes
    $data = $request->json()->all();

    // Valider à partir de $data
    $validator = \Validator::make($data, [
        'status' => 'required|in:confirmé,refusé,en_attente,annulé'
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors(), 'message' => 'Validation échouée.'], 422);
    }

    // Mise à jour
    $reservation->status = $data['status'];
    $reservation->save();

    return response()->json(['message' => 'Statut mis à jour avec succès.']);
}


    // Annulation avec remboursement
    // Annuler une réservation et déclencher un remboursement.
    
    public function cancelReservation($id)
    {
        $reservation = Reservations_events::with('payment')->findOrFail($id);

        if ($reservation->group_id !== Auth::id()) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        // Exemple : appel API remboursement Flouci ici via $reservation->payment->payment_id

        $reservation->status = 'annulé';
        $reservation->save();

        return response()->json(['message' => 'Réservation annulée et remboursement lancé.']);
    }

    //  Modifier le nombre de places
    public function updatePlaces(Request $request, $id)
{
    $data = $request->json()->all();

    $validator = \Validator::make($data, [
        'nbr_place' => 'required|integer|min:1'
    ]);

    if ($validator->fails()) {
        return response()->json(['message' => 'Erreur de validation.', 'errors' => $validator->errors()], 422);
    }

    $reservation = Reservations_events::findOrFail($id);

    if ($reservation->group_id !== Auth::id()) {
        return response()->json(['message' => 'Non autorisé.'], 403);
    }

    $event = Events::findOrFail($reservation->event_id);

    $ancienne_place = $reservation->nbr_place;
    $nouvelle_place = $data['nbr_place'];
    $diff = $nouvelle_place - $ancienne_place;

    // Vérifier si assez de places restantes si on augmente
    if ($diff > 0 && $event->nbr_place_restante < $diff) {
        return response()->json(['message' => 'Pas assez de places restantes.'], 400);
    }

    // Mettre à jour le champ `nbr_place_restante`
    $event->nbr_place_restante -= $diff;
    $event->save();

    // Mettre à jour la réservation
    $reservation->nbr_place = $nouvelle_place;
    $reservation->save();

    return response()->json(['message' => 'Nombre de places mis à jour.']);
}


    // Statistiques de participation
    public function reservationStats()
    {
        $user = Auth::user();

        $total = Reservations_events::where('group_id', $user->id)->count();
        $confirmes = Reservations_events::where('group_id', $user->id)->where('status', 'confirmé')->count();
        $en_attente = Reservations_events::where('group_id', $user->id)->where('status', 'en_attente')->count();
        $annules = Reservations_events::where('group_id', $user->id)->where('status', 'annulé')->count();

        return response()->json([
            'total' => $total,
            'confirmés' => $confirmes,
            'en_attente' => $en_attente,
            'annulés' => $annules,
        ]);
    }


    public function updateReservation(Request $request, $reservationId)
{
    $reservation = Reservations_events::findOrFail($reservationId);

    // Vérifier que le groupe connecté est bien propriétaire de la réservation
    if ($reservation->group_id !== Auth::id()) {
        return response()->json(['message' => 'Non autorisé à modifier cette réservation.'], 403);
    }

    // Récupérer les données JSON brutes
    $data = $request->json()->all();

    if (empty($data)) {
        return response()->json(['message' => 'Aucune donnée reçue pour la mise à jour.'], 400);
    }

    // Définir les règles de validation selon les champs modifiables
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

    // Gestion spécifique pour nbr_place : vérifier la disponibilité des places restantes
    if (isset($data['nbr_place'])) {
        $event = Events::findOrFail($reservation->event_id);
        $diff = $data['nbr_place'] - $reservation->nbr_place;

        if ($diff > 0 && $event->nbr_place_restante < $diff) {
            return response()->json(['message' => 'Pas assez de places disponibles.'], 400);
        }

        // Mettre à jour nbr_place_restante sur l'événement
        $event->nbr_place_restante -= $diff;
        $event->save();
    }

    // Appliquer la mise à jour sur la réservation
    $reservation->fill($data);
    $reservation->save();

    return response()->json([
        'message' => 'Réservation mise à jour avec succès.',
        'reservation' => $reservation->fresh(),
    ]);
}

// Afficher mes réservations passées
public function mesReservationsPassees(Request $request)
{
    $user = Auth::user();
    $today = now()->toDateString();

    $query = Reservations_events::where('group_id', $user->id)
        ->whereHas('event', function ($q) use ($today) {
            $q->whereDate('date_sortie', '<', $today);
        })
        ->with(['event', 'user', 'payment']);

    $reservations = $query->orderBy('created_at', 'desc')->paginate(10);

    return response()->json($reservations);
}



};
