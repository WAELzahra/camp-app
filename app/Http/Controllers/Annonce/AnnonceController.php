<?php

namespace App\Http\Controllers\Annonce;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Models\Annonce;
use App\Models\Photos;
use App\Mail\RequestAnnonceActivation;
use App\Mail\ActivateAnnonceNotification;
use App\Mail\AnnonceDeactivatedNotification;
use App\Http\Controllers\Controller;



class AnnonceController extends Controller
{
    /**
     * Display a list of annonces for a specific user.
     */
    public function index($idUser)
    {
        $annonces = Annonce::with('photos')->where('user_id', $idUser)->get();

        return response()->json([
            'status' => 'success',
            'annonces' => $annonces
        ]);
    }

    /**
     * Display the form to create a new annonce.
     */
    public function create()
    {
        return response()->json([
            'message' => 'Provide description and image to create a new annonce.'
        ]);
    }

    /**
     * Display a specific annonce by ID.
     */
    public function show(string $annonce_id)
    {
        $annonce = Annonce::with('photos')->find($annonce_id);

        if (!$annonce) {
            return response()->json([
                'status' => 'error',
                'message' => 'Annonce not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'annonce' => $annonce
        ]);
    }

    /**
     * Display the form to edit a specific annonce.
     */
    public function edit(string $annonce_id)
    {
        $annonce = Annonce::find($annonce_id);

        if (!$annonce) {
            return response()->json([
                'status' => 'error',
                'message' => 'Annonce not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'You can now edit this annonce.',
            'annonce' => $annonce
        ]);
    }

    /**
     * Store a new annonce with validation and photo creation.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'tag' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'required|string',
            'image' => 'required|string',
        ], [
            'title.required' => 'Le titre est obligatoire.',
            'description.required' => 'La description est obligatoire.',
            'image.required' => 'L\'image est obligatoire.',
        ]);
    
        $userId = Auth::id();
        $user = Auth::user();
    
        DB::beginTransaction();
        try {
            $annonce = Annonce::create([
                'user_id' => $userId,
                'title' => $request->title,
                'tag' => $request->tag,
                'category' => $request->category,
                'description' => $request->description,
                'status' => 'down'
            ]);
    
            Photos::create([
                'annonce_id' => $annonce->id,
                'path_to_img' => $request->image,
            ]);
    
            DB::commit();
            Mail::to($user->email)->send(new RequestAnnonceActivation($user));
    
            return response()->json([
                'status' => 'success',
                'message' => 'Annonce ajoutée avec succès.',
                'annonce' => $annonce
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
    
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de l\'ajout: ' . $e->getMessage()
            ], 500);
        }
    }
    

    /**
     * Update an existing annonce and associated photo.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'tag' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
        ]);
    
        DB::beginTransaction();
        try {
            $annonce = Annonce::findOrFail($id);
    
            $annonce->update([
                'title' => $request->title ?? $annonce->title,
                'tag' => $request->tag ?? $annonce->tag,
                'category' => $request->category ?? $annonce->category,
                'description' => $request->description ?? $annonce->description,
                'status' => 'down',
                'updated_at' => now(),
            ]);
    
            $photo = Photos::where('annonce_id', $annonce->id)->first();
            if ($photo && $request->image) {
                $photo->update([
                    'path_to_img' => $request->image,
                ]);
            }
    
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => 'Annonce mise à jour avec succès.',
                'annonce' => $annonce
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
    
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }
    

    /**
     * Delete an annonce and associated image path.
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();
        try {
            $annonce = Annonce::with('photos')->findOrFail($id);

            $photo = $annonce->photos->first();
            if ($photo && $photo->path_to_img) {
                Storage::delete($photo->path_to_img);
            }

            $annonce->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Annonce supprimée avec succès.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * desactivate annonce
     */
    public function deactivate(int $id)
    {
        $annonce = Annonce::findOrFail($id);
        $user = Auth::user();
    
        if ($user->role_id !== 6 && $user->id !== $annonce->user_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
    
        if ($annonce->status === 'down') {
            return response()->json([
                'status' => 'info',
                'message' => 'Cette annonce est déjà désactivée.'
            ], 400);
        }
    
        $annonce->status = 'down';
        $annonce->save();
    
        if ($user->role_id === 6) {
            $owner = $annonce->user;
            if ($owner) {
                Mail::to($owner->email)->send(new AnnonceDeactivatedNotification($owner, $annonce));
            }
        }
    
        return response()->json([
            'status' => 'success',
            'message' => 'Annonce désactivée avec succès.',
            'annonce' => $annonce
        ]);
    }
    
    /**
     * activate annonce
     */
    public function activate(int $annonceId)
    {
        $annonce = Annonce::findOrFail($annonceId);
    
        if ($annonce->status === 'up') {
            return response()->json([
                'status' => 'info',
                'message' => 'Cette annonce est déjà activée.'
            ], 400);
        }
    
        $annonce->update([
            'status' => 'up'
        ]);
    
        $user = $annonce->user;
    
        if ($user) {
            Mail::to($user->email)->send(new ActivateAnnonceNotification($user));
        }
    
        return response()->json([
            'status' => 'success',
            'message' => 'Annonce activée avec succès.',
            'annonce' => $annonce
        ]);
    }
    
    
}
