<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Feedbacks;
use App\Models\User;

class AdminFeedbackController extends Controller
{
    // 1. Lister tous les feedbacks avec filtres et recherche
    public function index(Request $request)
    {
        $query = Feedbacks::with(['user', 'zone', 'event', 'user_target']);

        // Filtrer par type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filtrer par statut
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtrer par note
        if ($request->filled('note')) {
            $query->where('note', $request->note);
        }

        // Recherche par contenu ou par nom utilisateur
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('contenu', 'like', "%$search%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%$search%"))
                  ->orWhereHas('user_target', fn($u) => $u->where('name', 'like', "%$search%"));
            });
        }

        // Pagination
        $feedbacks = $query->latest()->paginate(20);

        return response()->json($feedbacks);
    }

    // 2. Détails d’un feedback
    public function show($id)
    {
        $feedback = Feedbacks::with(['user', 'zone', 'event', 'user_target'])->findOrFail($id);

        return response()->json([
            'feedback' => $feedback,
            'related'  => $this->getFeedbackRelated($feedback)
        ]);
    }

    // 3. Valider un feedback
    public function approve($id)
    {
        $feedback = Feedbacks::findOrFail($id);
        $feedback->update(['status' => 'approved']);

        return response()->json(['message' => 'Feedback approuvé', 'feedback' => $feedback]);
    }

    // 4. Rejeter un feedback
    public function reject($id)
    {
        $feedback = Feedbacks::findOrFail($id);
        $feedback->update(['status' => 'rejected']);

        return response()->json(['message' => 'Feedback rejeté', 'feedback' => $feedback]);
    }

    // Méthode privée pour récupérer la cible liée
    private function getFeedbackRelated(Feedbacks $feedback)
    {
        switch ($feedback->type) {
            case 'zone':
                return $feedback->zone;
            case 'groupe':
            case 'guide':
            case 'fournisseur':
            case 'centre_user':
            case 'centre_camping':
                return $feedback->user_target;
            case 'event':
                return $feedback->event;
            default:
                return null;
        }
    }
}