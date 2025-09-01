<?php

namespace App\Http\Controllers\Materielle;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Photos;
use App\Models\User;

use App\Models\Materielles;
use App\Mail\MaterielleNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class MaterielleController extends Controller
{

    // Display all the materielle that belong to the fournisseur
    public function index(int $fournisseur_id)
    {
        try {
            $materielles = Materielles::where('fournisseur_id', $fournisseur_id)->get();

            if ($materielles->isEmpty()) {
                return response()->json(['status' => 'error', 'message' => 'No materielles found for this fournisseur.'], 404);
            }

            return response()->json(['status' => 'success', 'data' => $materielles]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to retrieve materielles.'], 500);
        }
    }

    public function create()
    {
        try {
            return response()->json([
                'status' => 'success',
                'message' => 'You can now create a new materielle.',
                'defaults' => [
                    'nom' => '',
                    'description' => '',
                    'tarif_nuit' => 0,
                    'quantite_total' => 0
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Unable to prepare materielle creation.'], 500);
        }
    }

    // Display the form to edit the materielle
    public function edit(int $materielle_id)
    {
        try {
            $materielle = Materielles::find($materielle_id);

            if (!$materielle) {
                return response()->json(['status' => 'error', 'message' => 'Materielle not found.'], 404);
            }

            return response()->json(['status' => 'success', 'data' => $materielle]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Unable to fetch materielle for editing.'], 500);
        }
    }

    // Display a specific materielle
    public function show(int $materielle_id)
    {
        try {
            $materielle = Materielles::with('photos')->find($materielle_id);

            if (!$materielle) {
                return response()->json(['status' => 'error', 'message' => 'Materielle not found.'], 404);
            }

            return response()->json(['status' => 'success', 'data' => $materielle]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Unable to retrieve materielle.'], 500);
        }
    }

    // compare 2 materielles  
    public function compare(int $id1, int $id2)
    {
        try {
            $materielle1 = Materielles::find($id1);
            $materielle2 = Materielles::find($id2);

            if (!$materielle1 || !$materielle2) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'One or both materielles not found.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'materielle1' => $materielle1,
                'materielle2' => $materielle2
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Comparison failed.'], 500);
        }
    }
    // Store a newly created resource in storage
public function store(Request $request)
{
    $validated = $request->validate([
        'category_id' => 'required|exists:materielles_categories,id',
        'nom' => 'required|string',
        'description' => 'required|string',
        'image' => 'required|string',
        'tarif_nuit' => 'required|numeric',
        'quantite_dispo' => 'required|integer',
        'quantite_total' => 'required|integer',
        'type' => 'required|string',
    ]);

    $profileId = Auth::id();

    // ✅ Check if the user already has a materielle with the same name
    $existing = Materielles::where('fournisseur_id', $profileId)
                            ->where('nom', $validated['nom'])
                            ->first();

    if ($existing) {
        return response()->json([
            'status' => 'error',
            'message' => 'You already added a materielle with the same name.'
        ], 409); // 409 = Conflict
    }

    DB::beginTransaction();
    try {
        $materielle = Materielles::create([
            'category_id' => $validated['category_id'],
            'fournisseur_id' => $profileId,
            'nom' => $validated['nom'],
            'description' => $validated['description'],
            'image' => $validated['image'],
            'quantite_dispo' => $validated['quantite_dispo'],
            'quantite_total' => $validated['quantite_total'],
            'type' => $validated['type'],
            'tarif_nuit' => $validated['tarif_nuit']
        ]);

        Photos::create([
            'materielle_id' => $materielle->id,
            'path_to_img' => $validated['image']
        ]);

        DB::commit();

        // 🔔 Send notification
        Mail::to($materielle->fournisseur->email)
            ->send(new MaterielleNotification($materielle->fournisseur, $materielle, 'created'));

        return response()->json([
            'status' => 'success',
            'message' => 'Materielle added successfully. Awaiting activation',
            'materielle' => $materielle
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to store materielle. ' . $e->getMessage()
        ], 500);
    }
}


    // Update
    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:materielles_categories,id',
            'nom' => 'string',
            'description' => 'string',
            'tarif_nuit' => 'numeric',
            'quantite_dispo' => 'integer',
            'quantite_total' => 'integer',
            'type' => 'string',
            'image' => 'string'
        ]);

        DB::beginTransaction();
        try {
            $materielle = Materielles::findOrFail($id);

            $materielle->update($validated);

            $photo = Photos::where('materielle_id', $materielle->id)->first();
            if ($photo) {
                $photo->update(['path_to_img' => $validated['image']]);
            }

            DB::commit();

            // 🔔 Notification mise à jour
            Mail::to($materielle->fournisseur->email)
                ->send(new MaterielleNotification($materielle->fournisseur, $materielle, 'created'));

            return response()->json([
                'status' => 'success',
                'message' => 'Materielle updated successfully.',
                'materielle' => $materielle
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Update failed. ' . $e->getMessage()], 500);
        }
    }

    // Activate
    public function activate(int $id)
    {
        $materielle = Materielles::findOrFail($id);

        $materielle->status = 'up';
        $materielle->save();

        // 🔔 Notification activation
        $this->sendNotification($materielle, 'activated');

        return response()->json([
            'status' => 'success',
            'message' => 'Materiel activé avec succès.',
            'materielle' => $materielle
        ]);
    }
    public function destroy($id) { 
        DB::beginTransaction(); 
        try { 
            $materielle = Materielles::with('photos')->findOrFail($id); 
            if ($materielle->image) 
                { 
                    Storage::delete($materielle->image); 
                } 
                $materielle->delete();
                DB::commit(); 
                return response()->json([ 'status' => 'success', 'message' => 'Materielle deleted successfully.' ]); 
            } catch (\Exception $e) { 
                DB::rollBack(); return response()->json([ 'status' => 'error', 'message' => 'Failed to delete materielle. ' . $e->getMessage() 
            ], 500); 
        } 
    }

    // Deactivate
    public function deactivate(int $id)
    {
        $materielle = Materielles::findOrFail($id);

        $materielle->status = 'down';
        $materielle->save();

        // 🔔 Notification désactivation
        $this->sendNotification($materielle, 'deactivated');

        return response()->json([
            'status' => 'success',
            'message' => 'Materiel désactivé avec succès.',
            'materielle' => $materielle
        ]);
    }

    // ----------- NOTIFICATION ------------
    private function sendNotification($materielle, string $action)
    {
        $user = User::find($materielle->fournisseur_id);

        if ($user && $user->email) {
            Mail::to($user->email)->send(new MaterielleNotification($materielle, $action));
        }
    }

}
