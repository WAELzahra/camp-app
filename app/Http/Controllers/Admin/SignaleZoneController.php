<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Signales;

class SignaleZoneController extends Controller
{
    // Liste globale de tous les signalements (admin)
    public function indexAll(Request $request)
    {
        $query = Signales::with(['user', 'zone', 'admin']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('is_treated') && $request->is_treated !== 'all') {
            $query->where('is_treated', (bool) $request->is_treated);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('contenu', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($u) use ($search) {
                      $u->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('date') && $request->date !== 'all') {
            switch ($request->date) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'week':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereMonth('created_at', now()->month)
                          ->whereYear('created_at', now()->year);
                    break;
                case 'year':
                    $query->whereYear('created_at', now()->year);
                    break;
            }
        }

        $perPage = $request->get('per_page', 10);
        $signalements = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $signalements,
        ]);
    }

    // Liste des signalements d'une zone (admin)
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

        $signalement->update([
            'status' => 'validated',
            'admin_id' => $user->id,
            'validated_at' => now(),
            'rejection_reason' => null,
            'rejected_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Signalement validé avec succès.',
            'data' => $signalement->fresh(['user', 'zone', 'admin']),
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
            'reason' => 'required|string|max:500',
        ]);

        $signalement = Signales::findOrFail($id);

        $signalement->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
            'admin_id' => $user->id,
            'rejected_at' => now(),
            'validated_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Signalement rejeté avec succès.',
            'data' => $signalement->fresh(['user', 'zone', 'admin']),
        ]);
    }

    // Supprimer un signalement (admin)
    public function destroy($id)
    {
        $user = auth()->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $signalement = Signales::findOrFail($id);
        $signalement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Signalement supprimé avec succès.',
        ]);
    }
}
