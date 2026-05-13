<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Gestion des dépenses par l'utilisateur connecté.
 * Chaque utilisateur ne voit et ne gère que SES dépenses.
 */
class ExpenseController extends Controller
{
    /** Liste + filtres + pagination */
    public function index(Request $request)
    {
        $userId = Auth::id();

        $query = Expense::forUser($userId)
            ->with('event:id,title')
            ->latest('date_depense');

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
                  ->orWhere('notes', 'like', "%{$s}%");
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('date_depense', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date_depense', '<=', $request->date_to);
        }

        $expenses = $query->paginate($request->get('per_page', 12));

        return response()->json(['success' => true, 'data' => $expenses]);
    }

    /** Statistiques personnelles */
    public function stats()
    {
        $userId = Auth::id();

        $base = Expense::forUser($userId)->where('status', 'confirmé');

        $total = $base->sum('montant');

        $parCategorie = Expense::forUser($userId)
            ->where('status', 'confirmé')
            ->selectRaw('categorie, SUM(montant) as total, COUNT(*) as count')
            ->groupBy('categorie')
            ->orderByDesc('total')
            ->get();

        $parMois = Expense::forUser($userId)
            ->where('status', 'confirmé')
            ->selectRaw("DATE_FORMAT(date_depense,'%Y-%m') as mois, SUM(montant) as total")
            ->groupBy('mois')
            ->orderBy('mois')
            ->limit(12)
            ->get();

        $count   = Expense::forUser($userId)->count();
        $brouill = Expense::forUser($userId)->where('status', 'brouillon')->count();

        return response()->json([
            'success' => true,
            'data'    => compact('total', 'parCategorie', 'parMois', 'count', 'brouill'),
        ]);
    }

    /** Créer une dépense */
    public function store(Request $request)
    {
        $data = $request->validate([
            'titre'        => 'required|string|max:255',
            'montant'      => 'required|numeric|min:0.01',
            'categorie'    => 'required|in:transport,hébergement,nourriture,équipement,marketing,maintenance,salaires,location,formation,communication,assurance,autre',
            'status'       => 'sometimes|in:brouillon,confirmé,remboursé',
            'date_depense' => 'required|date',
            'event_id'     => 'nullable|exists:events,id',
            'reference'    => 'nullable|string|max:100',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $expense = Expense::create(array_merge($data, ['user_id' => Auth::id()]));

        return response()->json(['success' => true, 'data' => $expense->load('event:id,title')], 201);
    }

    /** Afficher une dépense */
    public function show($id)
    {
        $expense = Expense::forUser(Auth::id())->with('event:id,title')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $expense]);
    }

    /** Modifier une dépense */
    public function update(Request $request, $id)
    {
        $expense = Expense::forUser(Auth::id())->findOrFail($id);

        $data = $request->validate([
            'titre'        => 'sometimes|string|max:255',
            'montant'      => 'sometimes|numeric|min:0.01',
            'categorie'    => 'sometimes|in:transport,hébergement,nourriture,équipement,marketing,maintenance,salaires,location,formation,communication,assurance,autre',
            'status'       => 'sometimes|in:brouillon,confirmé,remboursé',
            'date_depense' => 'sometimes|date',
            'event_id'     => 'nullable|exists:events,id',
            'reference'    => 'nullable|string|max:100',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $expense->update($data);

        return response()->json(['success' => true, 'data' => $expense->fresh('event:id,title')]);
    }

    /** Supprimer une dépense */
    public function destroy($id)
    {
        $expense = Expense::forUser(Auth::id())->findOrFail($id);
        $expense->delete();
        return response()->json(['success' => true, 'message' => 'Dépense supprimée.']);
    }
}
