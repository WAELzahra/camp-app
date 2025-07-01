<?php

namespace App\Http\Controllers\Event;

use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Controller;
use App\Models\Events;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Mail\NewEventNotification;
use App\Models\Reservations_events;
use App\Models\ProfileGroupe;
use App\Models\User;
use App\Models\FollowersGroupe;
use App\Models\Profile;
use App\Models\Circuit;

class EventController extends Controller
{
// Création d'un événement par un groupe
public function store(Request $request)
{
    if (auth()->user()->role->name !== 'groupe') {
        return response()->json(['message' => 'Accès refusé : seuls les groupes peuvent créer des événements.'], 403);
    }

    $validated = $request->validate([
        // Champs événement
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category' => 'required|string',
        'date_sortie' => 'required|date',
        'date_retoure' => 'required|date|after_or_equal:date_sortie',
        'ville_passente' => 'nullable|array',
        'nbr_place_total' => 'required|integer|min:1',
        'prix_place' => 'required|numeric|min:0',

        // Champs circuit
        'adresse_debut_circuit' => 'required|string',
        'adresse_fin_circuit' => 'required|string',
        'description_circuit' => 'nullable|string',
        'difficulty' => 'nullable|in:facile,moyenne,difficile',
        'distance_km' => 'nullable|numeric|min:0',
        'estimation_temps' => 'nullable|string',
        'danger_level' => 'nullable|in:low,moderate,high,extreme',
    ]);

    $userId = auth()->id();

    // Création du circuit
    $circuit = Circuit::create([
        'adresse_debut_circuit' => $validated['adresse_debut_circuit'],
        'adresse_fin_circuit' => $validated['adresse_fin_circuit'],
        'description' => $validated['description_circuit'] ?? null,
        'difficulty' => $validated['difficulty'] ?? null,
        'distance_km' => $validated['distance_km'] ?? null,
        'estimation_temps' => $validated['estimation_temps'] ?? null,
        'danger_level' => $validated['danger_level'] ?? null,
    ]);

    // Création de l'événement
    $event = Events::create([
        'title' => $validated['title'],
        'description' => $validated['description'] ?? null,
        'category' => $validated['category'],
        'date_sortie' => $validated['date_sortie'],
        'date_retoure' => $validated['date_retoure'],
        'ville_passente' => isset($validated['ville_passente']) ? json_encode($validated['ville_passente']) : null,
        'nbr_place_total' => $validated['nbr_place_total'],
        'prix_place' => $validated['prix_place'],
        'circuit_id' => $circuit->id,
        'group_id' => $userId,
        'status' => 'pending',
        'nbr_place_restante' => $validated['nbr_place_total'],
        'is_active' => false,
    ]);

    // Récupération du profil groupe
    $profile = Profile::where('user_id', $userId)->first();
    if (!$profile) {
        return response()->json(['message' => 'Profil non trouvé.'], 404);
    }

    $profileGroupe = ProfileGroupe::where('profile_id', $profile->id)->first();
    if (!$profileGroupe) {
        return response()->json(['message' => 'Profil groupe non trouvé.'], 404);
    }

    // Notifications aux abonnés
    $followersUserIds = FollowersGroupe::where('groupe_id', $profileGroupe->id)->pluck('user_id')->toArray();
    $users = User::whereIn('id', $followersUserIds)->get();

    foreach ($users as $user) {
        if ($user->email) {
            Mail::to($user->email)->send(new NewEventNotification($event, $user->name));
        }
    }

    return response()->json([
        'message' => 'Événement et circuit créés avec succès. Notifications envoyées aux abonnés.',
        'event' => $event,
        'circuit' => $circuit,
    ], 201);
}

// Affichage des détails d’un événement
public function show($id)
    {
        $event = Events::with('circuit')->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Détails de l’événement',
            'data' => $event
        ]);
}

// Mise à jour d’un événement par son créateur (groupe)
public function update(Request $request, $id)
    {
        $user = Auth::user();

        $event = Events::where('group_id', $user->id)->findOrFail($id);

        $event->update($request->only([
            'title',
            'description',
            'category',
            'date_sortie',
            'date_retoure',
            'ville_passente',
            'nbr_place_total',
            'prix_place',
            'circuit_id',
            'status'
        ]));

        return response()->json([
            'status' => true,
            'message' => 'Événement mis à jour avec succès',
            'data' => $event
        ]);
}

// Suppression d’un événement par le groupe
 public function destroy($id)
    {
        $user = Auth::user();
        $event = Events::where('group_id', $user->id)->findOrFail($id);
        $event->delete();

        return response()->json([
            'status' => true,
            'message' => 'Événement supprimé avec succès'
        ]);
}

// Liste des participants d’un événement, avec filtre par statut
 public function participants($id, Request $request)
    {
        $event = Events::where('group_id', auth()->id())->find($id);
        if (!$event) {
            return response()->json(['message' => 'Événement non trouvé'], 404);
        }

        $status = $request->query('status');
        $query = Reservations_events::with('user')->where('event_id', $id);

        if ($status) {
            $query->where('status', $status);
        }

        $participants = $query->get();

        return response()->json([
            'event_id' => $id,
            'participants' => $participants
        ]);
}

// Mise à jour du statut d’un participant (par le groupe)
public function updateStatus($id, Request $request)
    {
        $request->validate([
            'status' => 'required|in:en_attente,confirmé,annulé',
        ]);

        $reservation = Reservations_events::findOrFail($id);

        $event = Events::where('group_id', auth()->id())->find($reservation->event_id);
        if (!$event) {
            return response()->json(['message' => 'Accès refusé à cette réservation.'], 403);
        }

        $reservation->status = $request->status;
        $reservation->save();

        return response()->json(['message' => 'Statut mis à jour avec succès']);
 }

// Recherche et listing d’événements actifs (front office)
 public function index(Request $request)
    {
        $query = Events::where('is_active', 1);

        if ($request->has('title')) {
            $query->where('title', 'like', '%' . $request->title . '%');
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('destination')) {
            $query->where('ville_passente', 'like', '%' . $request->destination . '%');
        }

        if ($request->has('min_price')) {
            $query->where('prix_place', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('prix_place', '<=', $request->max_price);
        }

        if ($request->has('date_sortie')) {
            $query->whereDate('date_sortie', '=', $request->date_sortie);
        }

        $events = $query->orderBy('date_sortie', 'asc')->get();

        if ($events->isEmpty()) {
            return response()->json([
                'filters' => $request->all(),
                'results' => [],
                'message' => 'Aucun événement trouvé pour ces critères.'
            ], 404);
        }

        return response()->json([
            'filters' => $request->all(),
            'results' => $events
        ]);
 }

    // Recherche d’événements par mot-clé
    public function search(Request $request)
    {
        $keyword = trim($request->input('q'));

        if (!$keyword) {
            return response()->json(['message' => 'Veuillez entrer un mot-clé pour rechercher un événement.'], 400);
        }

        $events = Events::query()
            ->where(function ($query) use ($keyword) {
                $query->where('title', 'like', "%$keyword%")
                      ->orWhere('description', 'like', "%$keyword%")
                      ->orWhere('category', 'like', "%$keyword%")
                      ->orWhere('ville_passente', 'like', "%$keyword%")
                      ->orWhere('tags', 'like', "%$keyword%");
            })
            ->where('is_active', true)
            ->orderBy('date_sortie', 'asc')
            ->get();

        if ($events->isEmpty()) {
            return response()->json([
                'message' => 'Aucun événement trouvé pour "' . $keyword . '".'
            ], 404);
        }

        return response()->json([
            'message' => 'Résultats pour "' . $keyword . '"',
            'results' => $events
        ]);
    }

    // Détails publics d’un événement
    public function getEventDetails($id)
    {
        $event = Events::with('circuit')->find($id);

        if (!$event) {
            return response()->json(['message' => 'Événement non trouvé'], 404);
        }

        return response()->json([
            'event' => $event,
            'circuit' => $event->circuit,
        ]);
    }

    // Générer les liens de partage de l’événement
    public function getEventShareLinks($id)
    {
        $event = Events::find($id);

        if (!$event || !$event->is_active) {
            return response()->json(['message' => 'Événement introuvable.'], 404);
        }

        $url = url('/public/event/' . $event->id);

        $shareLinks = [
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($url),
            'whatsapp' => 'https://api.whatsapp.com/send?text=' . urlencode("Découvre cet événement : " . $url),
            'telegram' => 'https://t.me/share/url?url=' . urlencode($url) . '&text=' . urlencode('Découvre cet événement incroyable !'),
            'messenger' => 'https://www.facebook.com/dialog/send?app_id=TON_APP_ID_FACEBOOK&link=' . urlencode($url) . '&redirect_uri=' . urlencode(config('app.url'))
        ];

        return response()->json([
            'event_id' => $event->id,
            'share_links' => $shareLinks
        ]);
    }

    // Générer le lien de copie de l’événement
    public function getEventCopyLink($id)
    {
        $event = Events::find($id);

        if (!$event) {
            return response()->json(['message' => 'Événement non trouvé'], 404);
        }

        $publicLink = url("/public/event/" . $event->id);

        return response()->json([
            'link' => $publicLink
        ]);
    }
}
