<?php


namespace App\Http\Controllers\Groupe;

use App\Models\ProfileGroupe;
use Illuminate\Http\Request;
use App\Models\FollowersGroupe;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class FollowGroupeController extends Controller
{

    // Suivre un groupe
    public function follow(Request $request, $groupeId)
    {
        $user = Auth::user();

        // Vérifier que le groupe existe
        $groupe = ProfileGroupe::find($groupeId);
        if (!$groupe) {
            return response()->json(['message' => 'Groupe introuvable.'], 404);
        }

        // Vérifier si déjà abonné
        $exists = FollowersGroupe::where('user_id', $user->id)
            ->where('groupe_id', $groupeId)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Déjà abonné à ce groupe.'], 200);
        }

        // Créer l'abonnement
        FollowersGroupe::create([
            'user_id' => $user->id,         // toujours un user
            'groupe_id' => $groupeId        // id d'un profile_groupe
        ]);

        return response()->json(['message' => 'Abonnement réussi.']);
    }
    // Se désabonner d'un groupe
    public function unfollow(Request $request, $groupeId)
    {
        $user = Auth::user();

        FollowersGroupe::where('user_id', $user->id)
            ->where('groupe_id', $groupeId)
            ->delete();

        return response()->json(['message' => 'Désabonnement réussi.']);
    }
    // Récupérer les groupes suivis par l'utilisateur
    public function myFollowedGroupes()
    {
        $user = Auth::user();

        // Charger les groupes suivis avec leur détails
        $groupes = FollowersGroupe::where('user_id', $user->id)
            ->with('groupe') // Assurez-vous que la relation 'groupe' est définie dans le modèle
            ->get();

        return response()->json($groupes);
    }
}
