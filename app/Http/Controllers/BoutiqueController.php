<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Boutiques;

class BoutiqueController extends Controller
{
    // Show all shops
    public function index()
    {
    }
    // return the view for the founisseur to create a boutique
    public function create(){

    } 
    // display the form to edit the post
    public function edit()
    {

    }

    // display the shop of a fournisseur
    public function show()
    {
    }

    // add a shop
    public function add(Request $request)
    {
        $validated = $request->validate([
            'nom_boutique' => 'required|string',
            'description' => 'nullable|string',
            ]);

        $userId = Auth::id();

        $boutique = Boutiques::create([
            'fournisseur_id' => $userId,
            'nom_boutique' =>  $request->nom_boutique,
            'description' => $request->description,
            'status' => false,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        return response()->json([
            'message' => 'boutique added successfully.',
            'boutique' => $boutique,
        ], 201);
    }

    // update a shop
    public function update(Request $request)
    {
        $userId = Auth::id();

        $updated = Boutiques::where('fournisseur_id', $userId)
        ->update([
            'description' => $request->description,
            'nom_boutique' => $request->nom_boutique,
        ]);
    
        return response()->json([
            'message' => 'boutique updated successfully.',
        ], 201);

    }

    //delete a shop
    public function destroy(){
        $userId = Auth::id();

        $delete = Boutiques::where('fournisseur_id', $userId)
        ->delete();
        return response()->json([
            'message' => 'boutique removed successfully.',
        ], 201);
    }
}
