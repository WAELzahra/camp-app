<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Vue admin de toutes les dépenses de la plateforme.
 */
class AdminExpenseController extends Controller
{
    /** Liste complète avec filtres */
    public function index(Request $request)
    {
        $query = Expense::with([
            'user:id,first_name,last_name,email,role_id',
            'event:id,title',
        ])->latest('date_depense');

        if ($request->filled('categorie') && $request->categorie !== 'all') {
            $query->where('categorie', $request->categorie);
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('titre', 'like', "%{$s}%")
                  ->orWhere('reference', 'like', "%{$s}%")
                  ->orWhereHas('user', fn($u) => $u
                      ->where('first_name', 'like', "%{$s}%")
                      ->orWhere('last_name',  'like', "%{$s}%")
                      ->orWhere('email',       'like', "%{$s}%"));
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('date_depense', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date_depense', '<=', $request->date_to);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $expenses = $query->paginate($request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $expenses]);
    }

    /** Statistiques globales pour le dashboard */
    public function stats()
    {
        $totalMontant = Expense::where('status', 'confirmé')->sum('montant');
        $totalCount   = Expense::count();
        $brouillons   = Expense::where('status', 'brouillon')->count();
        $rembourses   = Expense::where('status', 'remboursé')->count();

        $parCategorie = Expense::where('status', 'confirmé')
            ->selectRaw('categorie, SUM(montant) as total, COUNT(*) as count')
            ->groupBy('categorie')
            ->orderByDesc('total')
            ->get();

        $parMois = Expense::where('status', 'confirmé')
            ->selectRaw("DATE_FORMAT(date_depense,'%Y-%m') as mois, SUM(montant) as total, COUNT(*) as count")
            ->groupBy('mois')
            ->orderBy('mois')
            ->limit(12)
            ->get();

        // Top 5 utilisateurs par dépenses
        $topUsers = Expense::where('status', 'confirmé')
            ->selectRaw('user_id, SUM(montant) as total, COUNT(*) as count')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('user:id,first_name,last_name,email,role_id')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => compact(
                'totalMontant', 'totalCount', 'brouillons', 'rembourses',
                'parCategorie', 'parMois', 'topUsers'
            ),
        ]);
    }

    /** Modifier le statut d'une dépense (ex: marquer comme remboursé) */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:brouillon,confirmé,remboursé',
        ]);

        $expense = Expense::with('user:id,first_name,last_name,email')->findOrFail($id);
        $expense->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour.',
            'data'    => $expense->fresh(['user:id,first_name,last_name,email', 'event:id,title']),
        ]);
    }

    /** Supprimer une dépense (admin) */
    public function destroy($id)
    {
        $expense = Expense::findOrFail($id);
        $expense->delete();
        return response()->json(['success' => true, 'message' => 'Dépense supprimée.']);
    }
}
