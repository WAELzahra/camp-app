<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Photos;
use App\Models\Materielles;



class MaterielleController extends Controller
{
    //display all the materielle that belong to the fournisseur
    public function index(int $fournisseur_id)
    {
    }

    // return the view for the founisseur to create a materielle
    public function create(){

    } 
    // display the form to edit the materielle
    public function edit(int $materielle_id)
    {
    }

    // display a specific materielle 
    public function show(int $materielle_id)
    {
    }



    // Store a newly created resource in storage
    public function store(Request $request)
    {
        $validate = $request->validate([
            'category_id' => 'required',
            'nom' => 'required|string',
            'description' => 'required|string',
            'image' => 'required|string',
            'tarif_nuit' => 'required|numeric',
            'quantite_dispo' => 'required|integer',
            'quantite_total' => 'required|integer',
            'type' => 'required|string',
        ]);

        $profileId = Auth::id(); 

        DB::beginTransaction();
        try{
            $materielle = Materielles::create([
                'category_id' => $request->category_id,
                'fournisseur_id' => $profileId,
                'nom' => $request->nom,
                'description' => $request->description,
                'image' => $request->image,
                'quantite_dispo' => $request->quantite_dispo,
                'quantite_total' => $request->quantite_total,
                'type' => $request->type,
                'tarif_nuit' => $request->tarif_nuit
            ]);
            Photos::create([
                'materielle_id' => $materielle->id,
                'path_to_img' => $request->image,  
            ]);
            DB::commit();
        
            return response()->json([
                'message' => 'materielle added successfully.',
                'materielle' => $materielle,
            ], 201);   
        }catch (\Exception $e) {
            DB::rollBack();
    
            return response()->json([
                'message' => 'Error occurred: ' . $e->getMessage(),
            ], 500);
        }
 
    }


    // Update the specified resource in storage
    public function update(Request $request,int $id)
    {
        $validate = $request->validate([
            'category_id' => 'required',
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
            $materielle->update([
                'category_id' => $request->category_id,
                'nom' => $request->nom,
                'description' => $request->description,
                'image' => $request->image,
                'quantite_dispo' => $request->quantite_dispo,
                'quantite_total' => $request->quantite_total,
                'type' => $request->type,
                'updated_at' => now()
            ]);
            $photo = Photos::where('materielle_id', $materielle->id)->first();

            if ($photo) {
                $photo->update([
                    'path_to_img' => $request->image,
                ]);
            }
            DB::commit();

            return response()->json([
                'message' => 'materielle updated successfully.',
                'materielle' => $materielle,
            ], 201);    
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Remove the specified resource from storage
    public function destroy($id)
    {
        DB::beginTransaction();
        try {

            $materielle = Materielles::with('photos')->findOrFail($id);

            // Delete related images
            if ($materielle->image) {
                Storage::delete($materielle->image);
            }
            
            $materielle->delete();


            DB::commit();

            return response()->json([
                'message' => 'Materielles deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    
    

}
