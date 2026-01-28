<?php

namespace App\Http\Controllers\profile;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\Profile;
use App\Models\ProfileGuide;
use App\Models\ProfileCentre;
use App\Models\ProfileGroupe;
use App\Models\ProfileFournisseur;
use App\Models\Album;
use App\Models\Photos;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        $profile = $user->profile;

        // If profile doesn't exist, create it
        if (!$profile) {
            $profile = Profile::create([
                'user_id' => $user->id,
                'type' => $this->determineUserType($user->role_id)
            ]);
        }

        $data = [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'adresse' => $user->adresse,
                'ville' => $user->ville,
                'date_naissance' => $user->date_naissance,
                'sexe' => $user->sexe,
                'langue' => $user->langue,
                'avatar' => $user->avatar,
                'role_id' => $user->role_id,
            ],
            'profile' => $profile,
            'details' => null,
        ];

        switch ($profile->type) {
            case 'guide':
                $data['details'] = $profile->profileGuide ?? new ProfileGuide(['profile_id' => $profile->id]);
                break;
            case 'centre':
                $data['details'] = $profile->profileCentre ?? new ProfileCentre(['profile_id' => $profile->id]);
                break;
            case 'groupe':
                $data['details'] = $profile->profileGroupe ?? new ProfileGroupe(['profile_id' => $profile->id]);
                
                // Include group feedbacks
                $feedbacks = \App\Models\Feedbacks::with('user')
                    ->where('target_id', $user->id)
                    ->where('type', 'groupe')
                    ->where('status', 'approved')
                    ->latest()
                    ->take(5)
                    ->get();

                $average = \App\Models\Feedbacks::where('target_id', $user->id)
                    ->where('type', 'groupe')
                    ->where('status', 'approved')
                    ->avg('note');

                $count = \App\Models\Feedbacks::where('target_id', $user->id)
                    ->where('type', 'groupe')
                    ->where('status', 'approved')
                    ->count();

                $data['feedback_summary'] = [
                    'average_note' => round($average, 2),
                    'feedback_count' => $count,
                    'latest_feedbacks' => $feedbacks,
                ];
                break;
            case 'fournisseur':
                $data['details'] = $profile->profileFournisseur ?? new ProfileFournisseur(['profile_id' => $profile->id]);
                break;
            default: // campeur
                $data['details'] = null;
        }

        return response()->json($data);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        
        // Start transaction for data consistency
        DB::beginTransaction();
        
        try {
            // 1. Update basic user data
            $userData = $request->only([
                'first_name',
                'last_name',
                'email',
                'phone_number',
                'ville',
                'date_naissance',
                'sexe',
                'langue',
            ]);
            
            // Handle adresse separately as it might be in profile details
            if ($request->has('adresse')) {
                $userData['adresse'] = $request->adresse;
            }
            
            $user->update($userData);

            // 2. Get or create profile
            $profile = $user->profile;
            if (!$profile) {
                $profile = Profile::create([
                    'user_id' => $user->id,
                    'type' => $this->determineUserType($user->role_id),
                    'bio' => $request->bio ?? null,
                    'cover_image' => $request->cover_image ?? null,
                ]);
            } else {
                $profile->update([
                    'bio' => $request->bio ?? $profile->bio,
                    'cover_image' => $request->cover_image ?? $profile->cover_image,
                ]);
            }

            // 3. Update specific profile details based on user type
            $userType = $profile->type;
            
            switch ($userType) {
                case 'campeur':
                    // For campers, handle activities if provided
                    if ($request->has('activities')) {
                        $profile->activities = $request->activities;
                        $profile->save();
                    }
                    break;
                    
                case 'guide':
                    $guideData = $request->only([
                        'adresse',
                        'cin',
                        'experience',
                        'tarif',
                        'zone_travail',
                    ]);
                    
                    if ($profile->profileGuide) {
                        $profile->profileGuide->update($guideData);
                    } else {
                        $guideData['profile_id'] = $profile->id;
                        ProfileGuide::create($guideData);
                    }
                    break;

                case 'centre':
                    $centreData = $request->only([
                        'name',
                        'adresse',
                        'contact_email',
                        'contact_phone',
                        'manager_name',
                        'capacite',
                        'price_per_night',
                        'category',
                        'services_offerts',
                        'additional_services_description',
                        'legal_document',
                        'disponibilite',
                    ]);
                    
                    // Handle latitude/longitude if provided
                    if ($request->has('latitude')) $centreData['latitude'] = $request->latitude;
                    if ($request->has('longitude')) $centreData['longitude'] = $request->longitude;
                    
                    if ($profile->profileCentre) {
                        $profile->profileCentre->update($centreData);
                    } else {
                        $centreData['profile_id'] = $profile->id;
                        ProfileCentre::create($centreData);
                    }
                    break;

                case 'groupe':
                    $groupeData = $request->only([
                        'nom_groupe',
                        'cin_responsable',
                    ]);
                    
                    if ($profile->profileGroupe) {
                        $profile->profileGroupe->update($groupeData);
                    } else {
                        $groupeData['profile_id'] = $profile->id;
                        ProfileGroupe::create($groupeData);
                    }
                    break;

                case 'fournisseur':
                    $fournisseurData = $request->only([
                        'adresse',
                        'cin',
                        'intervale_prix',
                        'product_category',
                    ]);
                    
                    if ($profile->profileFournisseur) {
                        $profile->profileFournisseur->update($fournisseurData);
                    } else {
                        $fournisseurData['profile_id'] = $profile->id;
                        ProfileFournisseur::create($fournisseurData);
                    }
                    break;
            }
            
            // Commit transaction
            DB::commit();
            
            return response()->json([
                'message' => 'Profil mis à jour avec succès',
                'user' => $user->fresh(),
                'profile' => $profile->fresh(),
            ]);
            
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $user = Auth::user();

        // Delete old avatar if exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Store new file
        $avatarPath = $request->file('avatar')->store('avatars', 'public');

        // Update avatar field
        $user->update(['avatar' => $avatarPath]);

        return response()->json([
            'message' => 'Avatar mis à jour avec succès.',
            'avatar_url' => asset('storage/' . $avatarPath),
        ]);
    }

    public function storeOrUpdateProfilePhotos(Request $request)
    {
        $user = Auth::user();
        
        // Check user role - only allow specific roles to update photos
        $allowedRoles = ['guide', 'centre', 'groupe', 'fournisseur', 'admin'];
        if (!in_array($this->determineUserType($user->role_id), $allowedRoles)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à modifier les photos de profil.'
            ], 403);
        }

        // Validate images
        $request->validate([
            'photos.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        // Get or create album
        $album = Album::firstOrCreate(
            ['user_id' => $user->id, 'titre' => "Album de profil"],
            [
                'description' => 'Album personnel de profil',
                'type' => 'profile'
            ]
        );

        // Delete old photos from this album
        $oldPhotos = $album->photos;
        foreach ($oldPhotos as $oldPhoto) {
            if (Storage::disk('public')->exists($oldPhoto->path_to_img)) {
                Storage::disk('public')->delete($oldPhoto->path_to_img);
            }
            $oldPhoto->delete();
        }

        // Add new photos
        $newPhotoPaths = [];
        foreach ($request->file('photos') as $photo) {
            $path = $photo->store('photos', 'public');

            Photos::create([
                'path_to_img' => $path,
                'user_id' => $user->id,
                'album_id' => $album->id,
            ]);

            $newPhotoPaths[] = asset('storage/' . $path);
        }

        return response()->json([
            'message' => 'Album de profil mis à jour avec succès.',
            'photos_urls' => $newPhotoPaths
        ]);
    }
    
    /**
     * Get profile by user ID (for admin or specific needs)
     */
    public function showById($userId)
    {
        $user = User::findOrFail($userId);
        $profile = $user->profile;

        if (!$profile) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        $data = [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'ville' => $user->ville,
                'role_id' => $user->role_id,
            ],
            'profile' => $profile,
            'details' => null,
        ];

        switch ($profile->type) {
            case 'guide':
                $data['details'] = $profile->profileGuide;
                break;
            case 'centre':
                $data['details'] = $profile->profileCentre;
                break;
            case 'groupe':
                $data['details'] = $profile->profileGroupe;
                break;
            case 'fournisseur':
                $data['details'] = $profile->profileFournisseur;
                break;
        }

        return response()->json($data);
    }

    /**
     * Determine user type based on role_id
     */
    private function determineUserType($roleId)
    {
        switch ($roleId) {
            case 1: return 'campeur';
            case 2: return 'groupe';
            case 3: return 'centre';
            case 4: return 'fournisseur';
            case 5: return 'guide';
            case 6: return 'admin';
            default: return 'campeur';
        }
    }

    /**
     * Get specific profile details
     */
    public function getProfileDetails($type, $userId)
    {
        $user = User::findOrFail($userId);
        $profile = $user->profile;

        if (!$profile || $profile->type !== $type) {
            return response()->json(['message' => 'Profile not found or type mismatch'], 404);
        }

        switch ($type) {
            case 'guide':
                $details = $profile->profileGuide;
                break;
            case 'centre':
                $details = $profile->profileCentre;
                break;
            case 'groupe':
                $details = $profile->profileGroupe;
                break;
            case 'fournisseur':
                $details = $profile->profileFournisseur;
                break;
            default:
                return response()->json(['message' => 'Invalid profile type'], 400);
        }

        return response()->json([
            'profile' => $profile,
            'details' => $details
        ]);
    }

    // Helper methods (keeping your existing ones)
    private function getCurrentSeason()
    {
        $month = date('n');
        if (in_array($month, [12, 1, 2])) return 'hiver';
        if (in_array($month, [3, 4, 5])) return 'printemps';
        if (in_array($month, [6, 7, 8])) return 'été';
        return 'automne';
    }

    private function getUserPreferences($userId)
    {
        $user = User::findOrFail($userId);
        return $user->preferences ? json_decode($user->preferences, true) : [];
    }

    private function getUserRegion($userId)
    {
        $user = User::findOrFail($userId);
        $preferences = $this->getUserPreferences($userId);
        
        if (!empty($preferences['region'])) {
            return $preferences['region'];
        }

        if (!empty($user->ville)) {
            return $user->ville;
        }

        return 'Tunisie';
    }
}