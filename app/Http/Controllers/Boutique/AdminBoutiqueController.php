<?php

namespace App\Http\Controllers\Boutique;

use App\Http\Controllers\Controller;
use App\Models\Boutiques;
use Illuminate\Http\Request;

class AdminBoutiqueController extends Controller
{
    /**
     * List all boutiques with their supplier info.
     */
    public function index()
    {
        $boutiques = Boutiques::with('fournisseur')->orderByDesc('created_at')->get();

        return response()->json([
            'status'    => 'success',
            'boutiques' => $boutiques,
        ]);
    }

    /**
     * Activate a boutique (set status = true).
     */
    public function activate(int $id)
    {
        $boutique = Boutiques::findOrFail($id);
        $boutique->update(['status' => true]);

        return response()->json([
            'status'   => 'success',
            'message'  => 'Boutique activée.',
            'boutique' => $boutique->fresh(),
        ]);
    }

    /**
     * Deactivate a boutique (set status = false).
     */
    public function deactivate(int $id)
    {
        $boutique = Boutiques::findOrFail($id);
        $boutique->update(['status' => false]);

        return response()->json([
            'status'   => 'success',
            'message'  => 'Boutique désactivée.',
            'boutique' => $boutique->fresh(),
        ]);
    }

    /**
     * Delete a boutique.
     */
    public function destroy(int $id)
    {
        $boutique = Boutiques::findOrFail($id);

        if ($boutique->path_to_img) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($boutique->path_to_img);
        }

        $boutique->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Boutique supprimée.',
        ]);
    }
}
