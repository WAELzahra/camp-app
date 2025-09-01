<?php

namespace App\Http\Controllers\Boutique;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Boutiques;
use Illuminate\Support\Facades\Mail;
use App\Mail\RequestBoutiqueActivation;
use App\Mail\BoutiqueActivationAccepted;
use App\Mail\BoutiqueDeactivatedNotification;


class BoutiqueController extends Controller
{
    /**
     * Display all boutiques.
     */
    public function index()
    {
        $boutiques = Boutiques::with('fournisseur')->get();

        return response()->json([
            'status' => 'success',
            'boutiques' => $boutiques,
        ]);
    }

    /**
     * Return a message describing how to create a boutique.
     * (Useful for API/placeholder routes)
     */
    public function create()
    {
        return response()->json([
            'message' => 'Provide nom_boutique and optional description to create a boutique.'
        ]);
    }

    /**
     * Show a form-like response for editing a boutique.
     */
    public function edit(int $boutique_id)
    {
        $boutique = Boutiques::find($boutique_id);

        if (!$boutique) {
            return response()->json([
                'status' => 'error',
                'message' => 'Boutique not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'boutique' => $boutique,
        ]);
    }

    /**
     * Show a specific boutique by ID.
     */
    public function show(int $fournisseur_id)
    {
        $boutique = Boutiques::where('fournisseur_id', $fournisseur_id)->first();
    
        if (!$boutique) {
            return response()->json([
                'status' => 'error',
                'message' => 'No boutique found for this fournisseur.'
            ], 404);
        }
    
        return response()->json([
            'status' => 'success',
            'boutique' => $boutique,
        ]);
    }
    

    /**
     * Add a new boutique for the authenticated fournisseur.
     */
    public function add(Request $request)
    {
        $validated = $request->validate([
            'nom_boutique' => 'required|string',
            'description' => 'nullable|string',
        ], [
            'nom_boutique.required' => 'Le nom de la boutique est obligatoire.',
        ]);

        $userId = Auth::id();
        $user = Auth::user();    

        $boutique = Boutiques::create([
            'fournisseur_id' => $userId,
            'nom_boutique' => $request->nom_boutique,
            'description' => $request->description,
            'status' => false,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        Mail::to($user->email)->send(new RequestBoutiqueActivation($user));

        return response()->json([
            'status' => 'success',
            'message' => 'Boutique ajoutée avec succès. attend lactivation de ladmin',
            'boutique' => $boutique,
        ], 201);
    }

    /**
     * Update the boutique for the authenticated fournisseur.
     */
    public function update(Request $request)
    {
        $request->validate([
            'nom_boutique' => 'required|string',
            'description' => 'nullable|string',
        ], [
            'nom_boutique.required' => 'Le nom de la boutique est requis.',
        ]);

        $userId = Auth::id();
        $user = Auth::user();    

        $updated = Boutiques::where('fournisseur_id', $userId)->first();

        if (!$updated) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aucune boutique trouvée pour mise à jour.'
            ], 404);
        }

        $updated->update([
            'nom_boutique' => $request->nom_boutique,
            'description' => $request->description,
            'status' => false,
            'updated_at' => now(),
        ]);
        Mail::to($user->email)->send(new RequestBoutiqueActivation($user));

        return response()->json([
            'status' => 'success',
            'message' => 'Boutique mise à jour avec succès. attend lactivation',
            'boutique' => $updated,
        ], 200);
    }

    /**
     * Delete the boutique owned by the authenticated fournisseur.
     */
    public function destroy()
    {
        $userId = Auth::id();

        $boutique = Boutiques::where('fournisseur_id', $userId)->first();

        if (!$boutique) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aucune boutique à supprimer.'
            ], 404);
        }

        $boutique->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Boutique supprimée avec succès.'
        ], 200);
    }
        /**
     * desactivate boutique
     */
    public function deactivate(int $id)
    {
        $user = Auth::user();

        // Will throw 404 automatically if not found
        $boutique = Boutiques::findOrFail($id);

        // Check authorization (owner or admin = role_id 6)
        if ($user->role_id !== 6 && $user->id !== $boutique->fournisseur_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Already deactivated (adapt to your DB type)
        if (! $boutique->status) {  // if boolean column
        // if ($boutique->status === 'down') {  // if string column
            return response()->json([
                'status' => 'info',
                'message' => 'Cette boutique est déjà désactivée.'
            ], 400);
        }

        // Deactivate boutique
        $boutique->status = false; // or 'down'
        $boutique->save();

        // Notify owner (only if admin deactivates)
        if ($user->role_id === 6) {
            $owner = $boutique->fournisseur; // relation must exist
            if ($owner) {
                Mail::to($owner->email)->send(new BoutiqueDeactivatedNotification($owner, $boutique));
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Boutique désactivée avec succès.',
            'boutique' => $boutique
        ]);
    } 
    /**
     * activate boutique
     */
    public function activate(int $id)
    {
        $user = Auth::user();    

        // Find boutique by fournisseur_id and the given $id
        $boutique = Boutiques::findOrFail($id);

        // If no boutique found, return error
        if (! $boutique) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aucune boutique trouvée pour cet utilisateur.'
            ], 404);
        }

        // If already active
        if ($boutique->status == true) {
            return response()->json([
                'status' => 'info',
                'message' => 'Cette boutique est déjà activée.'
            ], 400);
        }

        // Activate boutique
        $boutique->update([
            'status' => true
        ]);

        // Send email notification to owner
        Mail::to($user->email)->send(new BoutiqueActivationAccepted($user, $boutique->nom_boutique));

        return response()->json([
            'status' => 'success',
            'message' => 'Boutique activée avec succès.',
            'boutique' => $boutique
        ]);
    }


}
