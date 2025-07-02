<?php

namespace App\Http\Controllers\feedback;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Feedbacks;
use App\Models\User;
use App\Http\Controllers\Controller;

class FeedbackController extends Controller
{


public function storeOrUpdateFeedback(Request $request, $groupeId)
{
    $user = Auth::user();

    // Seuls les campeurs peuvent laisser un feedback
    if ($user->role->name !== 'campeur') {
        return response()->json(['message' => 'Seuls les campeurs peuvent laisser un feedback.'], 403);
    }

    // Vérifier que le groupe existe et est bien un utilisateur de rôle "groupe"
    $target = \App\Models\User::findOrFail($groupeId);
    if ($target->role->name !== 'groupe') {
        return response()->json(['message' => 'Le profil ciblé n’est pas un groupe de camping.'], 400);
    }

    // Valider les données
    $validated = $request->validate([
        'contenu' => 'nullable|string|max:1000',
        'note' => 'required|integer|min:1|max:5',
    ]);

    // Chercher un feedback existant du même user pour ce groupe
    $feedback = Feedbacks::where('user_id', $user->id)
        ->where('target_id', $target->id)
        ->where('type', 'groupe')
        ->first();

    if ($feedback) {
        // Mise à jour
        $feedback->update([
            'contenu' => $validated['contenu'] ?? $feedback->contenu,
            'note' => $validated['note'],
            'status' => 'pending', // remettre en attente de validation à chaque modification
        ]);

        $message = 'Votre avis a été mis à jour. Il sera publié après validation.';
    } else {
        // Création
        $feedback = Feedbacks::create([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'contenu' => $validated['contenu'] ?? null,
            'note' => $validated['note'],
            'type' => 'groupe',
            'status' => 'pending',
        ]);

        $message = 'Votre avis a été soumis. Il sera publié après validation.';
    }

    return response()->json([
        'message' => $message,
        'feedback' => $feedback
    ], $feedback->wasRecentlyCreated ? 201 : 200);
}

}