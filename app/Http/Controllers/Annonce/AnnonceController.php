<?php

namespace App\Http\Controllers\Annonce;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Models\Annonce;
use App\Models\Photo;
use App\Mail\RequestAnnonceActivation;
use App\Mail\ActivateAnnonceNotification;
use App\Mail\AnnonceDeactivatedNotification;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class AnnonceController extends Controller
{
    /**
     * Display a list of annonces for a specific user.
     */
    public function index($idUser)
    {
        $annonces = Annonce::with('photos')
            ->where('user_id', $idUser)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'annonces' => $annonces
        ]);
    }

    /**
     * Display all annonces with filters
     */
    public function getAll(Request $request)
    {
        $query = Annonce::with(['user', 'photos'])
            ->where('status', 'approved')
            ->where('is_archived', false);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by activities
        if ($request->has('activity')) {
            $query->whereJsonContains('activities', $request->activity);
        }

        // Search by title or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $annonces = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'annonces' => $annonces
        ]);
    }


    /**
     * Store a new annonce with all fields and image uploads.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'type' => 'required|string|max:255',
            'activities' => 'nullable|array',
            'latitude' => 'nullable|string|max:20',
            'longitude' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'auto_archive' => 'boolean',
            'images' => 'required|array|min:1',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120' // Validate each file
        ], [
            'title.required' => 'Le titre est obligatoire.',
            'description.required' => 'La description est obligatoire.',
            'type.required' => 'Le type d\'annonce est obligatoire.',
            'images.required' => 'Au moins une image est obligatoire.',
            'images.min' => 'Au moins une image est obligatoire.',
            'images.*.image' => 'Le fichier doit être une image.',
            'images.*.mimes' => 'L\'image doit être au format jpeg, png, jpg ou gif.',
            'images.*.max' => 'L\'image ne doit pas dépasser 5 Mo.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.'
        ]);

        $userId = Auth::id();
        $user = Auth::user();

        DB::beginTransaction();
        try {
            $annonce = Annonce::create([
                'user_id' => $userId,
                'title' => $request->title,
                'description' => $request->description,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'type' => $request->type,
                'activities' => $request->activities,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'address' => $request->address,
                'auto_archive' => $request->auto_archive ?? true,
                'is_archived' => false,
                'status' => 'pending',
                'views_count' => 0,
                'likes_count' => 0,
                'comments_count' => 0
            ]);

            // Handle multiple image uploads
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    // Store image in storage/app/public/annonces
                    $path = $image->store('annonces', 'public');
                    
                    Photo::create([
                        'annonce_id' => $annonce->id,
                        'path_to_img' => $path, // Stores 'annonces/filename.jpg'
                    ]);
                }
            }

            DB::commit();
            
            // Send activation request email
            Mail::to($user->email)->send(new RequestAnnonceActivation($user, $annonce));

            return response()->json([
                'status' => 'success',
                'message' => 'Annonce créée avec succès et en attente de validation.',
                'annonce' => $annonce->load('photos')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Display a specific annonce by ID.
     */
    public function show(string $annonce_id)
    {
        $annonce = Annonce::with(['photos', 'user'])->find($annonce_id);

        if (!$annonce) {
            return response()->json([
                'status' => 'error',
                'message' => 'Annonce non trouvée.'
            ], 404);
        }

        // Increment views
        $annonce->incrementViews();

        return response()->json([
            'status' => 'success',
            'annonce' => $annonce
        ]);
    }


    /**
     * Update an existing annonce with images.
     */
      public function update(Request $request, string $id)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'type' => 'nullable|string|max:255',
            'activities' => 'nullable|array',
            'latitude' => 'nullable|string|max:20',
            'longitude' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'auto_archive' => 'boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'existing_images' => 'nullable|string' // Expects comma-separated IDs
        ]);

        DB::beginTransaction();
        try {
            $annonce = Annonce::findOrFail($id);
            
            // Check authorization
            if (Auth::id() !== $annonce->user_id && Auth::user()->role_id !== 6) {
                return response()->json(['message' => 'Non autorisé.'], 403);
            }

            // Update annonce fields
            $annonce->update([
                'title' => $request->title ?? $annonce->title,
                'description' => $request->description ?? $annonce->description,
                'start_date' => $request->start_date ?? $annonce->start_date,
                'end_date' => $request->end_date ?? $annonce->end_date,
                'type' => $request->type ?? $annonce->type,
                'activities' => $request->activities ?? $annonce->activities,
                'latitude' => $request->latitude ?? $annonce->latitude,
                'longitude' => $request->longitude ?? $annonce->longitude,
                'address' => $request->address ?? $annonce->address,
                'auto_archive' => $request->has('auto_archive') ? $request->auto_archive : $annonce->auto_archive,
                'status' => 'pending', // Reset to pending after update
            ]);

            // Handle existing images
            if ($request->has('existing_images')) {
                // Parse comma-separated string into array
                $existingImageIds = array_filter(
                    explode(',', $request->existing_images),
                    'is_numeric'
                );
                $existingImageIds = array_map('intval', $existingImageIds);
                
                // Get current photo IDs
                $currentPhotoIds = $annonce->photos()->pluck('id')->toArray();
                
                // Find photos to delete (current photos not in existing_images list)
                $toDelete = array_diff($currentPhotoIds, $existingImageIds);
                
                foreach ($toDelete as $photoId) {
                    $photo = Photo::find($photoId);
                    if ($photo) {
                        // Delete file from storage
                        Storage::disk('public')->delete($photo->path_to_img);
                        // Delete database record
                        $photo->delete();
                    }
                }
            } else {
                // If no existing_images provided, delete all current photos
                foreach ($annonce->photos as $photo) {
                    Storage::disk('public')->delete($photo->path_to_img);
                    $photo->delete();
                }
            }

            // Upload new images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('annonces', 'public');
                    
                    Photo::create([
                        'annonce_id' => $annonce->id,
                        'path_to_img' => $path,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Annonce mise à jour avec succès. En attente de validation.',
                'annonce' => $annonce->load('photos')
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
     * Delete an annonce and associated images.
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();
        try {
            $annonce = Annonce::with('photos')->findOrFail($id);
            
            // Check authorization
            if (Auth::id() !== $annonce->user_id && Auth::user()->role_id !== 6) {
                return response()->json(['message' => 'Non autorisé.'], 403);
            }

            // Delete image files from storage
            foreach ($annonce->photos as $photo) {
                if ($photo->path_to_img) {
                    Storage::disk('public')->delete($photo->path_to_img);
                }
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
     * Activate annonce (admin only)
     */
    public function activate(int $annonceId)
    {
        $annonce = Annonce::findOrFail($annonceId);
        
        // Check if user is admin
        if (Auth::user()->role_id !== 6) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }
    
        if ($annonce->status === 'approved') {
            return response()->json([
                'status' => 'info',
                'message' => 'Cette annonce est déjà activée.'
            ], 400);
        }
    
        $annonce->update([
            'status' => 'approved'
        ]);
    
        $user = $annonce->user;
    
        if ($user) {
            Mail::to($user->email)->send(new ActivateAnnonceNotification($user, $annonce));
        }
    
        return response()->json([
            'status' => 'success',
            'message' => 'Annonce activée avec succès.',
            'annonce' => $annonce
        ]);
    }
    
    /**
     * Deactivate annonce
     */
    public function deactivate(int $id)
    {
        $annonce = Annonce::findOrFail($id);
        $user = Auth::user();
    
        if ($user->role_id !== 6 && $user->id !== $annonce->user_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }
    
        if ($annonce->status === 'rejected' || $annonce->status === 'canceled') {
            return response()->json([
                'status' => 'info',
                'message' => 'Cette annonce est déjà désactivée.'
            ], 400);
        }
    
        $annonce->status = 'rejected';
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
     * Archive an annonce
     */
    public function archive(int $id)
    {
        $annonce = Annonce::findOrFail($id);
        
        if (Auth::id() !== $annonce->user_id && Auth::user()->role_id !== 6) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $annonce->archive();

        return response()->json([
            'status' => 'success',
            'message' => 'Annonce archivée avec succès.',
            'annonce' => $annonce
        ]);
    }

    /**
     * Unarchive an annonce
     */
    public function unarchive(int $id)
    {
        $annonce = Annonce::findOrFail($id);
        
        if (Auth::id() !== $annonce->user_id && Auth::user()->role_id !== 6) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $annonce->unarchive();

        return response()->json([
            'status' => 'success',
            'message' => 'Annonce désarchivée avec succès.',
            'annonce' => $annonce
        ]);
    }

    /**
     * Get archived annonces for a user
     */
    public function getArchived($userId)
    {
        $annonces = Annonce::with('photos')
            ->where('user_id', $userId)
            ->where('is_archived', true)
            ->orWhere(function($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->where('auto_archive', true)
                  ->whereNotNull('end_date')
                  ->where('end_date', '<', now());
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'annonces' => $annonces
        ]);
    }

    /**
     * Like an annonce
     */
    public function like(int $id)
    {
        $annonce = Annonce::findOrFail($id);
        $user = Auth::user();

        // Check if already liked
        $existingLike = $annonce->likes()->where('user_id', $user->id)->first();

        if ($existingLike) {
            return response()->json([
                'success' => false,
                'message' => 'Already liked this post'
            ], 400);
        }

        // Create like
        $like = $annonce->likes()->create([
            'user_id' => $user->id
        ]);

        // Increment likes_count on annonce
        $annonce->increment('likes_count');

        return response()->json([
            'success' => true,
            'message' => 'Post liked successfully',
            'likes_count' => $annonce->fresh()->likes_count,
            'like' => $like
        ]);
    }

    /**
     * Unlike an annonce
     */
    public function unlike(int $id)
    {
        $annonce = Annonce::findOrFail($id);
        $user = Auth::user();

        // Find and delete the like
        $deleted = $annonce->likes()->where('user_id', $user->id)->delete();

        if ($deleted) {
            // Decrement likes_count on annonce
            $annonce->decrement('likes_count');
            
            return response()->json([
                'success' => true,
                'message' => 'Post unliked successfully',
                'likes_count' => $annonce->fresh()->likes_count
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Like not found'
        ], 404);
    }

    /**
     * Get users who liked an annonce
     */
    public function getLikes(int $id)
    {
        $annonce = Annonce::findOrFail($id);
        
        $likes = $annonce->likes()->with('user')->get();

        return response()->json([
            'success' => true,
            'likes' => $likes,
            'total' => $likes->count()
        ]);
    }

    /**
     * Check if user liked an annonce
     */
    public function checkLike(int $id)
    {
        $annonce = Annonce::findOrFail($id);
        $user = Auth::user();
        
        $liked = $annonce->likes()->where('user_id', $user->id)->exists();

        return response()->json([
            'success' => true,
            'liked' => $liked
        ]);
    }
}