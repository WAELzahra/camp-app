<?php

namespace App\Http\Controllers\Annonce;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Models\Annonce;
use App\Models\Photo;
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
        $annonces = Annonce::with('photos')
            ->where('user_id', $idUser)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status'   => 'success',
            'annonces' => $annonces,
        ]);
    }

    /**
     * Display all approved, non-archived annonces with optional filters.
     */
    public function getAll(Request $request)
    {
        $query = Annonce::with(['user.campingCentre', 'photos'])
            ->where('status', 'approved')
            ->where('is_archived', false);

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by activity
        if ($request->filled('activity')) {
            $query->whereJsonContains('activities', $request->activity);
        }

        // Search by title or description
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy    = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $annonces = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'status'   => 'success',
            'annonces' => $annonces,
        ]);
    }

    /**
     * Store a new annonce with all fields and image uploads.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'required|string',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'type'         => 'required|string|max:255',
            'activities'   => 'nullable|array',
            'latitude'     => 'nullable|string|max:20',
            'longitude'    => 'nullable|string|max:20',
            'address'      => 'nullable|string',
            'auto_archive' => 'boolean',
            'images'       => 'required|array|min:1',
            'images.*'     => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
            'cover_index'  => 'nullable|integer|min:0', // which uploaded image is the cover (0-based)
        ], [
            'title.required'          => 'Le titre est obligatoire.',
            'description.required'    => 'La description est obligatoire.',
            'type.required'           => 'Le type d\'annonce est obligatoire.',
            'images.required'         => 'Au moins une image est obligatoire.',
            'images.min'              => 'Au moins une image est obligatoire.',
            'images.*.image'          => 'Le fichier doit être une image.',
            'images.*.mimes'          => 'L\'image doit être au format jpeg, png, jpg ou gif.',
            'images.*.max'            => 'L\'image ne doit pas dépasser 5 Mo.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ]);
 
        $userId     = Auth::id();
        $user       = Auth::user();
        $coverIndex = (int) ($request->cover_index ?? 0); // default: first image is the cover
 
        DB::beginTransaction();
        try {
            $annonce = Annonce::create([
                'user_id'        => $userId,
                'title'          => $request->title,
                'description'    => $request->description,
                'start_date'     => $request->start_date,
                'end_date'       => $request->end_date,
                'type'           => $request->type,
                'activities'     => $request->activities,
                'latitude'       => $request->latitude,
                'longitude'      => $request->longitude,
                'address'        => $request->address,
                'auto_archive'   => $request->auto_archive ?? true,
                'is_archived'    => false,
                'status'         => 'pending',
                'views_count'    => 0,
                'likes_count'    => 0,
                'comments_count' => 0,
            ]);
 
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $idx => $image) {
                    $path = $image->store('annonces', 'public');
                    Photo::create([
                        'annonce_id'  => $annonce->id,
                        'path_to_img' => $path,
                        'is_cover'    => ($idx === $coverIndex), // ← mark cover
                        'order'       => $idx,
                    ]);
                }
            }
 
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Erreur lors de la création: ' . $e->getMessage(),
            ], 500);
        }
 
        // Mail outside transaction — failure never rolls back the annonce
        try {
            Mail::to($user->email)->send(new RequestAnnonceActivation($user, $annonce));
        } catch (\Exception $e) {
            Log::error('RequestAnnonceActivation mail failed: ' . $e->getMessage());
        }
 
        return response()->json([
            'status'  => 'success',
            'message' => 'Annonce créée avec succès et en attente de validation.',
            'annonce' => $annonce->load('photos'),
        ], 201);
    }
    /**
     * Display a specific annonce by ID and increment its view count.
     */
    public function show(string $annonce_id)
    {
        $annonce = Annonce::with(['photos', 'user.profile.profileCentre'])->find($annonce_id);  // ✅ Load profile.profileCentre

        if (!$annonce) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Annonce non trouvée.',
            ], 404);
        }

        $annonce->incrementViews();

        return response()->json([
            'status'  => 'annonce',
            'annonce' => $annonce,
        ]);
    }
    /**
     * Update an existing annonce with images.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'title'           => 'nullable|string|max:255',
            'description'     => 'nullable|string',
            'start_date'      => 'nullable|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
            'type'            => 'nullable|string|max:255',
            'activities'      => 'nullable|array',
            'latitude'        => 'nullable|string|max:20',
            'longitude'       => 'nullable|string|max:20',
            'address'         => 'nullable|string',
            'auto_archive'    => 'boolean',
            'images'          => 'nullable|array',
            'images.*'        => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'existing_images' => 'nullable|string',
            'cover_photo_id'  => 'nullable|integer', // ID of existing photo to set as cover
            'cover_index'     => 'nullable|integer|min:0', // index among new uploads
        ]);
 
        DB::beginTransaction();
        try {
            $annonce = Annonce::findOrFail($id);
 
            if (Auth::id() !== $annonce->user_id && Auth::user()->role_id !== 6) {
                return response()->json(['message' => 'Non autorisé.'], 403);
            }
 
            $annonce->update([
                'title'        => $request->title        ?? $annonce->title,
                'description'  => $request->description  ?? $annonce->description,
                'start_date'   => $request->start_date   ?? $annonce->start_date,
                'end_date'     => $request->end_date      ?? $annonce->end_date,
                'type'         => $request->type          ?? $annonce->type,
                'activities'   => $request->activities    ?? $annonce->activities,
                'latitude'     => $request->latitude      ?? $annonce->latitude,
                'longitude'    => $request->longitude     ?? $annonce->longitude,
                'address'      => $request->address       ?? $annonce->address,
                'auto_archive' => $request->has('auto_archive') ? $request->auto_archive : $annonce->auto_archive,
                'status'       => 'pending',
            ]);
 
            $coverPhotoId  = $request->filled('cover_photo_id') ? (int) $request->cover_photo_id : null;
            $coverIndex    = (int) ($request->cover_index ?? 0);
            $keepIds       = [];
 
            // ── Handle existing photos ──────────────────────────────────────
            if ($request->has('existing_images') && $request->existing_images !== '') {
                $keepIds = array_map(
                    'intval',
                    array_filter(explode(',', $request->existing_images), 'is_numeric')
                );
 
                // Delete photos NOT in keepIds
                $toDelete = array_diff($annonce->photos()->pluck('id')->toArray(), $keepIds);
                foreach ($toDelete as $photoId) {
                    $photo = Photo::find($photoId);
                    if ($photo) {
                        Storage::disk('public')->delete($photo->path_to_img);
                        $photo->delete();
                    }
                }
 
                // Set cover on an existing photo if requested
                if ($coverPhotoId && in_array($coverPhotoId, $keepIds)) {
                    Photo::where('annonce_id', $annonce->id)->update(['is_cover' => false]);
                    Photo::where('id', $coverPhotoId)->update(['is_cover' => true]);
                    $coverPhotoId = null; // already handled — new uploads won't override
                }
 
            } else {
                // No existing_images sent → delete all current photos
                foreach ($annonce->photos as $photo) {
                    Storage::disk('public')->delete($photo->path_to_img);
                    $photo->delete();
                }
            }
 
            // ── Upload new images ───────────────────────────────────────────
            if ($request->hasFile('images')) {
                // Check if an existing photo is already marked as cover
                $existingCoverSet = Photo::where('annonce_id', $annonce->id)
                                         ->where('is_cover', true)
                                         ->exists();
 
                foreach ($request->file('images') as $idx => $image) {
                    $path = $image->store('annonces', 'public');
 
                    // New upload is cover only when:
                    // 1. No existing photo was set as cover via cover_photo_id
                    // 2. No kept photo is already the cover
                    // 3. This is the designated cover_index
                    $isCover = !$existingCoverSet && ($idx === $coverIndex);
 
                    Photo::create([
                        'annonce_id'  => $annonce->id,
                        'path_to_img' => $path,
                        'is_cover'    => $isCover,
                        'order'       => count($keepIds) + $idx,
                    ]);
                }
            }
 
            // ── Safety net: ensure exactly one photo is the cover ───────────
            $freshPhotos = $annonce->fresh()->photos;
            if ($freshPhotos->isNotEmpty() && !$freshPhotos->contains('is_cover', true)) {
                $freshPhotos->first()->update(['is_cover' => true]);
            }
 
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage(),
            ], 500);
        }
 
        return response()->json([
            'status'  => 'success',
            'message' => 'Annonce mise à jour avec succès. En attente de validation.',
            'annonce' => $annonce->load('photos'),
        ], 200);
    }

    /**
     * Delete an annonce and its associated images.
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();
        try {
            $annonce = Annonce::with('photos')->findOrFail($id);

            if (Auth::id() !== $annonce->user_id && Auth::user()->role_id !== 6) {
                return response()->json(['message' => 'Non autorisé.'], 403);
            }

            foreach ($annonce->photos as $photo) {
                if ($photo->path_to_img) {
                    Storage::disk('public')->delete($photo->path_to_img);
                }
            }

            $annonce->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Annonce supprimée avec succès.',
        ], 200);
    }

    /**
     * Activate an annonce (admin only).
     */
    public function activate(int $annonceId)
    {
        if (Auth::user()->role_id !== 6) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $annonce = Annonce::findOrFail($annonceId);

        if ($annonce->status === 'approved') {
            return response()->json([
                'status'  => 'info',
                'message' => 'Cette annonce est déjà activée.',
            ], 400);
        }

        $annonce->update(['status' => 'approved']);

        // ── Mail outside any transaction ──────────────────────────────────────
        try {
            if ($annonce->user) {
                Mail::to($annonce->user->email)->send(
                    new ActivateAnnonceNotification($annonce->user, $annonce)
                );
            }
        } catch (\Exception $e) {
            Log::error('ActivateAnnonceNotification mail failed: ' . $e->getMessage());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Annonce activée avec succès.',
            'annonce' => $annonce,
        ]);
    }

    /**
     * Deactivate an annonce (admin or owner).
     */
    public function deactivate(int $id)
    {
        $annonce = Annonce::findOrFail($id);
        $user    = Auth::user();

        if ($user->role_id !== 6 && $user->id !== $annonce->user_id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        if (in_array($annonce->status, ['rejected', 'canceled'])) {
            return response()->json([
                'status'  => 'info',
                'message' => 'Cette annonce est déjà désactivée.',
            ], 400);
        }

        $annonce->status = 'rejected';
        $annonce->save();

        // ── Mail outside any transaction ──────────────────────────────────────
        try {
            if ($user->role_id === 6 && $annonce->user) {
                Mail::to($annonce->user->email)->send(
                    new AnnonceDeactivatedNotification($annonce->user, $annonce)
                );
            }
        } catch (\Exception $e) {
            Log::error('AnnonceDeactivatedNotification mail failed: ' . $e->getMessage());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Annonce désactivée avec succès.',
            'annonce' => $annonce,
        ]);
    }

    /**
     * Archive an annonce.
     */
    public function archive(int $id)
    {
        $annonce = Annonce::findOrFail($id);

        if (Auth::id() !== $annonce->user_id && Auth::user()->role_id !== 6) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $annonce->archive();

        return response()->json([
            'status'  => 'success',
            'message' => 'Annonce archivée avec succès.',
            'annonce' => $annonce,
        ]);
    }

    /**
     * Unarchive an annonce.
     */
    public function unarchive(int $id)
    {
        $annonce = Annonce::findOrFail($id);

        if (Auth::id() !== $annonce->user_id && Auth::user()->role_id !== 6) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $annonce->unarchive();

        return response()->json([
            'status'  => 'success',
            'message' => 'Annonce désarchivée avec succès.',
            'annonce' => $annonce,
        ]);
    }

    /**
     * Get archived annonces for a specific user.
     */
    public function getArchived($userId)
    {
        $annonces = Annonce::with('photos')
            ->where('user_id', $userId)
            ->where(function ($q) {
                $q->where('is_archived', true)
                  ->orWhere(function ($q2) {
                      $q2->where('auto_archive', true)
                         ->whereNotNull('end_date')
                         ->where('end_date', '<', now());
                  });
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'status'   => 'success',
            'annonces' => $annonces,
        ]);
    }

    /**
     * Like an annonce.
     */
    public function like(int $id)
    {
        $annonce = Annonce::findOrFail($id);
        $user    = Auth::user();

        if ($annonce->likes()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Already liked this post',
            ], 400);
        }

        $like = $annonce->likes()->create(['user_id' => $user->id]);
        $annonce->increment('likes_count');

        return response()->json([
            'success'     => true,
            'message'     => 'Post liked successfully',
            'likes_count' => $annonce->fresh()->likes_count,
            'like'        => $like,
        ]);
    }

    /**
     * Unlike an annonce.
     */
    public function unlike(int $id)
    {
        $annonce = Annonce::findOrFail($id);
        $user    = Auth::user();

        $deleted = $annonce->likes()->where('user_id', $user->id)->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Like not found',
            ], 404);
        }

        $annonce->decrement('likes_count');

        return response()->json([
            'success'     => true,
            'message'     => 'Post unliked successfully',
            'likes_count' => $annonce->fresh()->likes_count,
        ]);
    }

    /**
     * Get all users who liked an annonce.
     */
    public function getLikes(int $id)
    {
        $annonce = Annonce::findOrFail($id);
        $likes   = $annonce->likes()->with('user')->get();

        return response()->json([
            'success' => true,
            'likes'   => $likes,
            'total'   => $likes->count(),
        ]);
    }
    public function getUserLikes(Request $request)
    {

        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Not authenticated',
                'liked_ids' => []
            ], 401);
        }
        
        $likedIds = $user->likedAnnonces()->pluck('annonce_id')->toArray();
        
        return response()->json([
            'success' => true,
            'liked_ids' => $likedIds
        ]);
    }
    /**
     * GET /annonces/my-liked — full annonce objects the authenticated user has liked.
     */
    public function myLiked(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'data' => []], 401);
        }

        $likedIds = $user->likedAnnonces()->pluck('annonce_id')->toArray();

        if (empty($likedIds)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $annonces = Annonce::with(['photos', 'user'])
            ->whereIn('id', $likedIds)
            ->latest()
            ->get()
            ->map(function ($a) {
                $cover = $a->photos->firstWhere('is_cover', true) ?? $a->photos->first();
                return [
                    'id'            => $a->id,
                    'title'         => $a->title,
                    'description'   => $a->description,
                    'address'       => $a->address ?? '',
                    'start_date'    => $a->start_date,
                    'end_date'      => $a->end_date,
                    'status'        => $a->status,
                    'type'          => $a->type,
                    'created_at'    => $a->created_at,
                    'views_count'   => $a->views_count ?? 0,
                    'likes_count'   => $a->likes_count ?? 0,
                    'comments_count'=> $a->comments_count ?? 0,
                    'photos'        => $a->photos->map(fn($p) => [
                        'id'          => $p->id,
                        'path_to_img' => $p->path_to_img,
                        'is_cover'    => $p->is_cover,
                    ])->values(),
                    'user'          => $a->user ? [
                        'id'         => $a->user->id,
                        'first_name' => $a->user->first_name,
                        'last_name'  => $a->user->last_name,
                        'avatar'     => $a->user->avatar,
                    ] : null,
                ];
            });

        return response()->json(['success' => true, 'data' => $annonces]);
    }

    /**
     * Check if the authenticated user liked an annonce.
     */
    public function checkLike(int $id)
    {
        $annonce = Annonce::findOrFail($id);
        $liked   = $annonce->likes()->where('user_id', Auth::id())->exists();

        return response()->json([
            'success' => true,
            'liked'   => $liked,
        ]);
    }
}