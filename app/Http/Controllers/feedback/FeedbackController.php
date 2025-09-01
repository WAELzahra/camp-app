<?php

namespace App\Http\Controllers\feedback;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Feedbacks;
use App\Models\User;
use App\Http\Controllers\Controller;

class FeedbackController extends Controller
{

 /**
     * Créer ou mettre à jour un feedback
     */
    public function storeOrUpdateFeedback(Request $request, $type, $targetId)
    {
        $user = Auth::user()->load('role');

        // Vérification du rôle
        if (!$user->role || $user->role->name !== 'campeur') {
            return response()->json(['message' => 'Seuls les campeurs peuvent laisser un feedback.'], 403);
        }

        // Types autorisés
        $allowedTypes = ['groupe', 'guide', 'fournisseur', 'centre_user', 'centre_camping'];
        $type = strtolower($type);

        if (!in_array($type, $allowedTypes)) {
            return response()->json(['message' => 'Type de cible non valide.'], 400);
        }

        // Récupération de la cible selon le type
        try {
            switch ($type) {
                case 'centre_user':
                    $target = User::with('role')
                        ->where('id', $targetId)
                        ->whereHas('role', fn($q) => $q->where('name', 'centre'))
                        ->firstOrFail();
                    break;

                case 'centre_camping':
                    $target = CampingCentre::findOrFail($targetId);
                    break;

                default:
                    $target = User::with('role')
                        ->where('id', $targetId)
                        ->whereHas('role', fn($q) => $q->where('name', $type))
                        ->firstOrFail();
                    break;
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Cible introuvable.'], 404);
        }

        // Validation des données
        $validated = $request->validate([
            'contenu' => 'nullable|string|max:1000',
            'note'    => 'required|integer|min:1|max:5',
        ]);

        // Création ou mise à jour du feedback
        $feedback = Feedbacks::firstOrNew([
            'user_id'   => $user->id,
            'target_id' => $targetId,
            'type'      => $type,
        ]);

        $feedback->contenu = $validated['contenu'] ?? $feedback->contenu;
        $feedback->note    = $validated['note'];
        $feedback->status  = 'pending';
        $feedback->save();

        $message = $feedback->wasRecentlyCreated
            ? 'Votre avis a été soumis. Il sera publié après validation.'
            : 'Votre avis a été mis à jour. Il sera publié après validation.';

        return response()->json([
            'message'  => $message,
            'feedback' => $feedback,
            'target'   => $target, // Optionnel : pour afficher les infos du guide/groupe
        ], $feedback->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Lister les feedbacks validés d’un profil (groupe / guide / fournisseur / centre)
     */
    public function getFeedbacks($type, $targetId)
{
    $allowedTypes = ['groupe','guide','fournisseur','centre_user','centre_camping'];
    if (!in_array($type, $allowedTypes)) {
        return response()->json(['message' => 'Type de cible non valide.'], 400);
    }

    $feedbacks = Feedbacks::with('user')
        ->where('target_id', $targetId)
        ->where('type', $type)
        ->where('status', 'approved')
        ->latest()
        ->get();

    $avg = Feedbacks::where('target_id', $targetId)
        ->where('type', $type)
        ->where('status', 'approved')
        ->avg('note');

    return response()->json([
        'average_note' => is_null($avg) ? null : round($avg, 1),
        'count'        => $feedbacks->count(),
        'feedbacks'    => $feedbacks,
    ]);
}



// Ajouter un feedback sur une zone
    public function storeZone(Request $request, $zoneId)
{
    $data = $request->validate([
        'note' => 'required|integer|min:1|max:5',
        'contenu' => 'nullable|string',
    ]);

    // Vérifie si un feedback existe déjà pour cet utilisateur et cette zone
    $existing = Feedbacks::where('user_id', auth()->id())
        ->where('zone_id', $zoneId)
        ->where('type', 'zone')
        ->first();

    if ($existing) {
        return response()->json([
            'message' => 'Vous avez déjà soumis un avis pour cette zone.',
            'feedback' => $existing
        ], 200);
    }

    // Création du feedback
    $feedback = Feedbacks::create([
        'user_id' => auth()->id(),
        'zone_id' => $zoneId,
        'target_id' => null,
        'event_id' => null,
        'contenu' => $data['contenu'],
        'note' => $data['note'],
        'type' => 'zone',
        'status' => 'pending',
    ]);

    return response()->json([
        'message' => 'Votre avis sur la zone a été enregistré avec succès. Il sera publié après validation.',
        'feedback' => $feedback
    ], 201);
    }


// Lister tous les feedbacks d'une zone
    public function listZone($zoneId)
    {
        $feedbacks = Feedbacks::with('user')
            ->where('zone_id', $zoneId)
            ->where('type', 'zone')
            ->where('status', 'approved')
            ->latest()
            ->get();

        return response()->json($feedbacks);
    }

// Méthode auxiliaire pour retourner les infos liées au feedback
// Dans FeedbackController
public function getFeedbackRelatedPublic($id)
{
    $feedback = Feedbacks::with(['zone', 'event', 'user_target'])->findOrFail($id);
    return response()->json([
        'feedback_id' => $feedback->id,
        'related' => $this->getFeedbackRelated($feedback),
    ]);
}







}