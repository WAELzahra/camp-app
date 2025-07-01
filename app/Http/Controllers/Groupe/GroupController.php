<?php

namespace App\Http\Controllers\Groupe;

use App\Models\ProfileGroupe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Profile;
use App\Models\User;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\ReservationEvent;
use App\Models\FollowGroupe;
use App\Models\Album;
use App\Models\Payment;
use App\Models\PaymentStatus;
use App\Models\EventParticipant;
use App\Models\Feedbacks;
use App\Models\Role;

class GroupController extends Controller 
{
  /**
     * Affiche les détails d’un groupe spécifique avec ses informations de base,
     * son nombre d’abonnés (followers) et ses événements à venir.
     */
    public function show($id)
{
    $groupe = ProfileGroupe::with([
        'profile.user',
        'profile.user.events' => function ($q) {
            $q->whereDate('date_sortie', '>=', now());
        },
        'followers'
    ])->find($id);

    if (!$groupe) {
        return response()->json(['success' => false, 'message' => 'Groupe introuvable.'], 404);
    }

    return response()->json([
        'success' => true,
        'groupe' => [
            'id' => $groupe->id,
            'nom_groupe' => $groupe->nom_groupe,
            'cin_responsable' => $groupe->cin_responsable,
            'bio' => $groupe->profile->bio ?? null,
            'ville' => $groupe->profile->user->ville ?? null,
            'email' => $groupe->profile->user->email ?? null,
            'phone_number' => $groupe->profile->user->phone_number ?? null,
            'followers_count' => $groupe->followers->count(),
            'evenements_a_venir' => $groupe->profile->user->events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'date_sortie' => $event->date_sortie,
                ];
            }),
        ]
    ]);
}
  /**
     * Fournit une liste de recommandations de groupes similaires
     * (basée sur la même ville) à un groupe donné.
     */
public function recommandations($id)
{
    $groupe = ProfileGroupe::with('profile.user')->find($id);

    if (!$groupe) {
        return response()->json(['success' => false, 'message' => 'Groupe introuvable.'], 404);
    }

    $ville = $groupe->profile->user->ville ?? null;

    $recommandations = ProfileGroupe::where('id', '!=', $id)
        ->whereHas('profile.user', function ($q) use ($ville) {
            if ($ville) {
                $q->where('ville', $ville);
            }
        })
        ->limit(5)
        ->with('profile.user')
        ->get();

    return response()->json([
        'success' => true,
        'recommandations' => $recommandations->map(function ($g) {
            return [
                'id' => $g->id,
                'nom_groupe' => $g->nom_groupe,
                'ville' => $g->profile->user->ville ?? null,
                'bio' => $g->profile->bio ?? null,
            ];
        })
    ]);
}

    /**
     * Récupère l’album photo associé à un groupe donné (s’il existe).
     */
public function albums($id)
{
    $groupe = ProfileGroupe::find($id);

    if (!$groupe || !$groupe->id_album_photo) {
        return response()->json(['success' => false, 'message' => 'Aucun album trouvé.']);
    }

    $album = Album::with('photos')->find($groupe->id_album_photo);

    return response()->json([
        'success' => true,
        'albums' => $album
    ]);
}

  /**
     * Recherche avancée de groupes en fonction de plusieurs critères :
     * nom, ville, bio, type, email, téléphone, ainsi que les événements associés
     * (titre, tags, prix, ville passante, etc.).
     */
    public function searchGroups(Request $request)
    {
        // Récupérer tous les filtres possibles
        $filters = $request->only([
            'nom_groupe',
            'cin_responsable',
            'type_profile',
            'bio',
            'ville_user',
            'email_user',
            'phone_number_user',
            'event_title',
            'event_ville_passente',
            'event_date_sortie',
            'event_tags',
            'event_prix_min',
            'event_prix_max'
        ]);

        $query = ProfileGroupe::query()
            ->join('profiles', 'profile_groupes.profile_id', '=', 'profiles.id')
            ->join('users', 'profiles.user_id', '=', 'users.id')
            ->select('profile_groupes.*');

        // Filtres sur ProfileGroupe
        if (!empty($filters['nom_groupe'])) {
            $query->where('profile_groupes.nom_groupe', 'like', '%' . $filters['nom_groupe'] . '%');
        }

        if (!empty($filters['cin_responsable'])) {
            $query->where('profile_groupes.cin_responsable', 'like', '%' . $filters['cin_responsable'] . '%');
        }

        // Filtres sur Profile
        if (!empty($filters['type_profile'])) {
            $query->where('profiles.type', $filters['type_profile']);
        }

        if (!empty($filters['bio'])) {
            $query->where('profiles.bio', 'like', '%' . $filters['bio'] . '%');
        }

        // Filtres sur User
        if (!empty($filters['ville_user'])) {
            $query->where('users.ville', 'like', '%' . $filters['ville_user'] . '%');
        }

        if (!empty($filters['email_user'])) {
            $query->where('users.email', 'like', '%' . $filters['email_user'] . '%');
        }

        if (!empty($filters['phone_number_user'])) {
            $query->where('users.phone_number', 'like', '%' . $filters['phone_number_user'] . '%');
        }

        // Filtres sur événements liés au groupe
        if (!empty($filters['event_title']) ||
            !empty($filters['event_ville_passente']) ||
            !empty($filters['event_date_sortie']) ||
            !empty($filters['event_tags']) ||
            !empty($filters['event_prix_min']) ||
            !empty($filters['event_prix_max'])
        ) {
            // On joint la table events sur users.id = events.group_id
            $query->join('events', 'users.id', '=', 'events.group_id');

            if (!empty($filters['event_title'])) {
                $query->where('events.title', 'like', '%' . $filters['event_title'] . '%');
            }

            if (!empty($filters['event_ville_passente'])) {
                // Comme ville_passente est casté en array JSON, on peut chercher en JSON (MySQL >=5.7)
                $ville = $filters['event_ville_passente'];
                $query->whereJsonContains('events.ville_passente', $ville);
            }

            if (!empty($filters['event_date_sortie'])) {
                $query->whereDate('events.date_sortie', '>=', $filters['event_date_sortie']);
            }

            if (!empty($filters['event_tags'])) {
                // Tags séparés par virgule, on cherche au moins un tag
                $tags = explode(',', $filters['event_tags']);
                $query->where(function ($q) use ($tags) {
                    foreach ($tags as $tag) {
                        $tag = trim($tag);
                        $q->orWhere('events.tags', 'like', "%$tag%");
                    }
                });
            }

            if (!empty($filters['event_prix_min'])) {
                $query->where('events.prix_place', '>=', $filters['event_prix_min']);
            }

            if (!empty($filters['event_prix_max'])) {
                $query->where('events.prix_place', '<=', $filters['event_prix_max']);
            }
        }

        $query->groupBy('profile_groupes.id'); // éviter doublons à cause du join events

        // Charger les relations utiles pour la réponse
        $query->with(['profile.user']);

        $groupes = $query->get();

        if ($groupes->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Aucun groupe trouvé correspondant aux critères.',
                'groupes' => []
            ]);
        }

        // Formatage des données pour la réponse
        $result = $groupes->map(function ($groupe) {
            return [
                'id' => $groupe->id,
                'nom_groupe' => $groupe->nom_groupe,
                'cin_responsable' => $groupe->cin_responsable,
                'type_profile' => $groupe->profile->type ?? null,
                'bio' => $groupe->profile->bio ?? null,
                'ville' => $groupe->profile->user->ville ?? null,
                'email' => $groupe->profile->user->email ?? null,
                'phone_number' => $groupe->profile->user->phone_number ?? null,
                // Tu peux aussi ajouter les événements liés ici si tu veux
            ];
        });

        return response()->json([
            'success' => true,
            'groupes' => $result
        ]);
    }

       /**
     * Récupère une liste paginée des groupes avec les 3 derniers feedbacks validés
     * pour chaque groupe et les statistiques globales de satisfaction (note moyenne, nombre total).
     */
public function listGroupsWithFeedbacks()
{
    // Récupérer les groupes (ici on suppose que 'type' == 'groupe' dans profiles)
    $groupes = User::whereHas('profile', function($q) {
        $q->where('type', 'groupe');
    })
    ->with(['profile.profileGroupe']) // charger détails groupe
    ->paginate(10);

    // Pour chaque groupe, calculer les stats de feedbacks
    $groupes->getCollection()->transform(function ($groupe) {
        $feedbacks = Feedbacks::with('user')
            ->where('target_id', $groupe->id)
            ->where('type', 'groupe')
            ->where('status', 'approved')
            ->latest()
            ->take(3)
            ->get();

        $average = Feedbacks::where('target_id', $groupe->id)
            ->where('type', 'groupe')
            ->where('status', 'approved')
            ->avg('note');

        $count = Feedbacks::where('target_id', $groupe->id)
            ->where('type', 'groupe')
            ->where('status', 'approved')
            ->count();

        $groupe->feedback_summary = [
            'average_note' => round($average, 2),
            'feedback_count' => $count,
            'latest_feedbacks' => $feedbacks,
        ];

        return $groupe;
    });

    return response()->json($groupes);
}

    /**
     * Récupère les feedbacks approuvés pour un groupe spécifique,
     * avec pagination, moyenne des notes, et total des avis.
     */
public function listForGroup($groupeId)
{
    $feedbacks = Feedbacks::with('user')
        ->where('target_id', $groupeId)
        ->where('type', 'groupe')
        ->where('status', 'approved')
        ->latest()
        ->paginate(5); // pagination

    $average = Feedbacks::where('target_id', $groupeId)
        ->where('type', 'groupe')
        ->where('status', 'approved')
        ->avg('note');

    $count = Feedbacks::where('target_id', $groupeId)
        ->where('type', 'groupe')
        ->where('status', 'approved')
        ->count();

    return response()->json([
        'average_note' => round($average, 2),
        'feedback_count' => $count,
        'feedbacks' => $feedbacks
    ]);
}

}
