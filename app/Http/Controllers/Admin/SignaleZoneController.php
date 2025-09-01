<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Signales;
use Illuminate\Support\Facades\Auth;

class SignaleZoneController extends Controller
{
    // Liste des signalements d’une zone (admin)
    public function index($zoneId)
    {
        $signalements = Signales::with(['user', 'admin'])
            ->where('zone_id', $zoneId)
            ->where('type', 'zone')
            ->latest()
            ->get();

        return response()->json($signalements);
    }

    // Valider un signalement (admin)
    public function validateSignalement($id)
    {
        $user = auth()->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $signalement = Signales::findOrFail($id);

        if ($signalement->status !== 'pending') {
            return response()->json(['message' => 'Ce signalement a déjà été traité.'], 400);
        }

        $signalement->update([
            'status' => 'validated',
            'admin_id' => $user->id,
            'validated_at' => now(),
            'rejection_reason' => null,
            'rejected_at' => null
        ]);

        return response()->json([
            'message' => 'Signalement validé avec succès.',
            'data' => $signalement
        ]);
    }


    // Rejeter un signalement (admin)
        public function rejectSignalement(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $signalement = Signales::findOrFail($id);

        if ($signalement->status !== 'pending') {
            return response()->json(['message' => 'Ce signalement a déjà été traité.'], 400);
        }

        $signalement->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
            'admin_id' => $user->id,
            'rejected_at' => now(),
            'validated_at' => null
        ]);

        return response()->json([
            'message' => 'Signalement rejeté avec succès.',
            'data' => $signalement
        ]);
    }

}
