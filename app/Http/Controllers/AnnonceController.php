<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Annonce;
use App\Models\Photos;

class AnnonceController extends Controller
{


    /**
     * Display a list of the posts.
     */
    public function index($idUser)
    {

    }

    // display the form to create a poste
    public function create()
    {
    }

    // display a specific poste
    public function show(string $annonce_id)
    {
    }

    // display the form to edit the post
    public function edit(string $annonce_id)
    {
    }


    // store the post
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string',
            'image' => 'required|string', // image as string path or URL
        ]);
    
        $userId = Auth::id();
    
        DB::beginTransaction();
    
        try {
            $annonce = Annonce::create([
                'user_id' => $userId,
                'description' => $request->description,
            ]);
    
            Photos::create([
                'annonce_id' => $annonce->id,
                'path_to_img' => $request->image,  // just take string from request
            ]);
    
            DB::commit();
    
            return response()->json([
                'message' => 'Annonce and image path saved successfully.',
                'annonce' => $annonce,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
    
            return response()->json([
                'message' => 'Error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    // update the post
    public function update(Request $request, string $id)
    {
        $request->validate([
            'description' => 'string',
            'image' => 'required|string', 
        ]);

        DB::beginTransaction();

        try {
            $annonce = Annonce::findOrFail($id);

            $annonce->update([
                'description' => $request->description,
                'updated_at' => now(),
            ]);
            $photo = Photos::where('annonce_id', $annonce->id)->first();

            if ($photo) {
                $photo->update([
                    'path_to_img' => $request->image,
                ]);
            }
            DB::commit();

            return response()->json([
                'message' => 'Annonce updated successfully.',
                'annonce' => $annonce,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    // delete the post
    public function destroy(string $id)
    {
        DB::beginTransaction();

        try {
            $annonce = Annonce::with('photos')->findOrFail($id);

            // Delete related images
            if ($annonce->image) {
                Storage::delete($annonce->image);
            }
            
            $annonce->delete();

            DB::commit();

            return response()->json([
                'message' => 'Annonce and associated images deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }
}
