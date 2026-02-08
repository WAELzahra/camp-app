<?php

namespace App\Http\Controllers\profile;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\Profile;
use App\Models\ServiceCategory;
use App\Models\ProfileCenterService;
use App\Models\ProfileCenterEquipment;
use App\Models\ProfileGuide;
use App\Models\ProfileCentre;
use App\Models\ProfileGroupe;
use App\Models\ProfileFournisseur;
use App\Models\Album;
use Illuminate\Support\Facades\Validator; 
use App\Models\Photo;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Update center general settings
     */
    public function updateCenter(Request $request, $centerId)
    {
        try {
            $user = Auth::user();
            $center = ProfileCentre::findOrFail($centerId);

            // Verify ownership
            if ($center->profile->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $data = $request->only(['show_standard_service']);
            
            // Convert to boolean if present
            if (isset($data['show_standard_service'])) {
                $data['show_standard_service'] = (bool) $data['show_standard_service'];
            }

            $center->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Center updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update center',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function changePassword(Request $request)
    {
        try {
            // Validate request - Note: using new_password_confirmation
            $validator = Validator::make($request->all(), [
                'current_password' => 'required',
                'new_password' => 'required|min:8|different:current_password|confirmed', // 'confirmed' rule
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get authenticated user
            $user = Auth::user();
            
            // Check if current password matches
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            // Update password
            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
        
        DB::beginTransaction();
        
        try {
            \Log::info('Profile update request:', $request->all());
            
            // 1. Update basic user data (ONLY fields in users table)
            $userData = [];
            
            // Fields that exist in users table
            $userFields = ['first_name', 'last_name', 'email', 'phone_number', 'ville', 'date_naissance', 'sexe', 'langue'];
            
            foreach ($userFields as $field) {
                if ($request->has($field)) {
                    $userData[$field] = $request->input($field);
                }
            }
            
            // DO NOT include 'adresse' here - it's not in users table
            
            if (!empty($userData)) {
                $user->update($userData);
            }

            // 2. Get or create profile
            $profile = $user->profile;
            if (!$profile) {
                $profileData = [
                    'user_id' => $user->id,
                    'type' => $this->determineUserType($user->role_id),
                ];
                
                if ($request->has('bio')) {
                    $profileData['bio'] = $request->bio;
                }
                if ($request->has('cover_image')) {
                    $profileData['cover_image'] = $request->cover_image;
                }
                // Activities will be added after migration
                if ($request->has('activities')) {
                    $profileData['activities'] = $request->activities;
                }
                
                $profile = Profile::create($profileData);
            } else {
                $profileUpdateData = [];
                
                if ($request->has('bio')) {
                    $profileUpdateData['bio'] = $request->bio;
                }
                if ($request->has('cover_image')) {
                    $profileUpdateData['cover_image'] = $request->cover_image;
                }
                // Activities will be added after migration
                if ($request->has('activities')) {
                    $profileUpdateData['activities'] = $request->activities;
                }
                
                if (!empty($profileUpdateData)) {
                    $profile->update($profileUpdateData);
                }
            }

            // 3. Update specific profile details based on user type
            $userType = $profile->type;
            
            switch ($userType) {
                case 'campeur':
                    // Activities already handled above
                    // No additional profile detail table for campers
                    break;
                    
                case 'guide':
                    $guideData = $request->only([
                        'adresse',  // Goes to profile_guide table
                        'cin',
                        'experience',
                        'tarif',
                        'zone_travail',
                    ]);
                    
                    // Convert numeric fields
                    if (isset($guideData['experience'])) {
                        $guideData['experience'] = (int) $guideData['experience'];
                    }
                    if (isset($guideData['tarif'])) {
                        $guideData['tarif'] = (float) $guideData['tarif'];
                    }
                    
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
                        'adresse',  // Goes to profile_centres table
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
                    
                    // Handle numeric conversions
                    if (isset($centreData['capacite'])) {
                        $centreData['capacite'] = (int) $centreData['capacite'];
                    }
                    if (isset($centreData['price_per_night'])) {
                        $centreData['price_per_night'] = (float) $centreData['price_per_night'];
                    }
                    if (isset($centreData['disponibilite'])) {
                        $centreData['disponibilite'] = (bool) $centreData['disponibilite'] ? 1 : 0;
                    }
                    
                    // Handle latitude/longitude
                    if ($request->has('latitude')) {
                        $centreData['latitude'] = (float) $request->latitude;
                    }
                    if ($request->has('longitude')) {
                        $centreData['longitude'] = (float) $request->longitude;
                    }
                    
                    // Handle established_date
                    if ($request->has('established_date')) {
                        $centreData['established_date'] = $request->established_date;
                    }
                    
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
                        'adresse',  // Goes to profile_fournisseur table
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
            
            DB::commit();
            
            \Log::info('Profile updated successfully for user: ' . $user->id);
            
            return response()->json([
                'message' => 'Profil mis à jour avec succès',
                'user' => $user->fresh(),
                'profile' => $profile->fresh(),
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Profile update error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du profil',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
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

        // DEBUG: Check file actually exists
        $fullPath = storage_path('app/public/' . $avatarPath);
        $fileExists = file_exists($fullPath);
        
        // DEBUG: Get file info
        $fileInfo = $fileExists ? [
            'size' => filesize($fullPath),
            'mime' => mime_content_type($fullPath),
        ] : null;

        return response()->json([
            'message' => 'Avatar updated successfully.',
            'avatar_url' => asset('storage/' . $avatarPath),
            'debug' => [
                'path' => $avatarPath,
                'full_path' => $fullPath,
                'file_exists' => $fileExists,
                'file_info' => $fileInfo,
                'storage_url' => Storage::disk('public')->url($avatarPath),
                'asset_url' => asset('storage/' . $avatarPath),
                'url_function' => url('storage/' . $avatarPath),
            ]
        ]);
    }

    public function updateInfo(Request $request)
    {
        $user = Auth::user();
        
        // Validate the request
        $request->validate([
            'album_title' => 'required|string|max:255',
            'album_description' => 'nullable|string',
        ]);

        try {
            // Get the user's profile album (create if doesn't exist)
            $albumTitle = $request->input('album_title');
            $albumDescription = $request->input('album_description', null);
            
            $album = Album::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'titre' => $albumTitle,
                    'description' => $albumDescription,
                ]
            );


            // If the album already exists, update its info
            $updateData = [
                'titre' => $albumTitle,
                'updated_at' => now(),
            ];
            
            if ($request->has('album_description')) {
                $updateData['description'] = $albumDescription;
            }
            
            $album->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Album info updated successfully',
                'album' => [
                    'id' => $album->id,
                    'title' => $album->titre,
                    'description' => $album->description,
                    'cover_image' => $album->path_to_img ? asset('storage/' . $album->path_to_img) : null,
                    'photo_count' => $album->photos()->count(),
                    'created_at' => $album->created_at ? $album->created_at->format('Y-m-d H:i:s') : null,
                ],
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Album info update error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update album info',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    public function storeOrUpdateProfilePhotos(Request $request)
    {
        $user = Auth::user();
        
        // Validate the request
        $request->validate([
            'photos' => 'required|array|min:1',
            'photos.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:25600',
            'album_title' => 'nullable|string|max:255',
            'album_description' => 'nullable|string',
        ]);

        DB::beginTransaction();
        
        try {
            // Get or create album for the user
            $albumTitle = $request->input('album_title', 'Profile Gallery');
            $albumDescription = $request->input('album_description', null);
            
            $album = Album::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'titre' => 'Profile Gallery', // Search for profile album
                ],
                [
                    'titre' => $albumTitle, // Create with provided or default title
                    'description' => $albumDescription,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Always update album info when photos are uploaded
            $updateData = [
                'titre' => $albumTitle,
                'updated_at' => now(),
            ];
            
            if ($request->has('album_description')) {
                $updateData['description'] = $albumDescription;
            }
            
            $album->update($updateData);

            // Rest of your existing photo upload code...
            $uploadedPhotos = [];
            $order = $album->photos()->max('order') ?? 0;

            foreach ($request->file('photos') as $index => $photo) {
                // Generate unique filename
                $originalName = pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $photo->getClientOriginalExtension();
                $filename = $originalName . '_' . time() . '_' . uniqid() . '.' . $extension;
                
                // Store the image
                $path = $photo->storeAs('profile_photos', $filename, 'public');
                
                // Create photo record
                $photoRecord = Photo::create([
                    'path_to_img' => $path,
                    'user_id' => $user->id,
                    'album_id' => $album->id,
                    'is_cover' => ($index === 0 && $album->photos()->where('is_cover', 1)->count() === 0) ? 1 : 0,
                    'order' => ++$order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $uploadedPhotos[] = [
                    'id' => $photoRecord->id,
                    'path' => $path,
                    'url' => asset('storage/' . $path),
                    'is_cover' => $photoRecord->is_cover,
                    'order' => $photoRecord->order,
                ];
            }

            // Update album cover image path if this is the first photo
            if ($album->path_to_img === null && count($uploadedPhotos) > 0) {
                $album->update([
                    'path_to_img' => $uploadedPhotos[0]['path'],
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($uploadedPhotos) . ' photo(s) uploaded successfully',
                'album' => [
                    'id' => $album->id,
                    'title' => $album->titre,
                    'description' => $album->description,
                    'cover_image' => $album->path_to_img ? asset('storage/' . $album->path_to_img) : null,
                    'photo_count' => $album->photos()->count(),
                ],
                'photos' => $uploadedPhotos,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Profile photos upload error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload photos',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    // Additional method to get user's profile photos
    public function getProfilePhotos()
    {
        try {
            $user = Auth::user();
            
            \Log::info('Fetching profile photos for user: ' . $user->id);
            
            // Get or create profile album with a default title
            $album = Album::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'titre' => 'Profile Gallery',
                ],
                [
                    'titre' => 'Profile Gallery',
                    'description' => 'User profile gallery images',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Get photos from this album
            $photos = Photo::where('user_id', $user->id)
                ->where('album_id', $album->id)
                ->orderBy('order', 'asc')
                ->orderBy('created_at', 'desc')
                ->get();

            \Log::info('Album found/created: ' . $album->id . ', photos count: ' . $photos->count());

            $formattedPhotos = $photos->map(function($photo) {
                return [
                    'id' => $photo->id,
                    'url' => asset('storage/' . $photo->path_to_img),
                    'path' => $photo->path_to_img,
                    'is_cover' => (bool)$photo->is_cover,
                    'order' => $photo->order,
                    'created_at' => $photo->created_at ? $photo->created_at->format('Y-m-d H:i:s') : null,
                ];
            });

            return response()->json([
                'success' => true,
                'album' => [
                    'id' => $album->id,
                    'title' => $album->titre,
                    'description' => $album->description,
                    'cover_image' => $album->path_to_img ? asset('storage/' . $album->path_to_img) : null,
                    'photo_count' => $photos->count(),
                    'created_at' => $album->created_at ? $album->created_at->format('Y-m-d H:i:s') : null,
                ],
                'photos' => $formattedPhotos->values()->all(),
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in getProfilePhotos: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile photos',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // Method to delete a photo
    public function deletePhoto($photoId)
    {
        $user = Auth::user();
        
        // Get the user's profile album
        $album = Album::where('user_id', $user->id)
            ->where('titre', 'Profile Gallery')
            ->first();
        
        if (!$album) {
            return response()->json([
                'success' => false,
                'message' => 'Profile album not found',
            ], 404);
        }
        
        // Only delete photos from the profile album
        $photo = Photo::where('id', $photoId)
            ->where('user_id', $user->id)
            ->where('album_id', $album->id)
            ->firstOrFail();

        DB::beginTransaction();
        
        try {
            // Delete the physical file
            if (Storage::disk('public')->exists($photo->path_to_img)) {
                Storage::disk('public')->delete($photo->path_to_img);
            }

            // If this was the cover photo, update the album cover
            if ($photo->is_cover) {
                $nextCover = Photo::where('album_id', $album->id)
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $photo->id)
                    ->orderBy('order', 'asc')
                    ->first();
                
                if ($nextCover) {
                    $nextCover->update(['is_cover' => 1]);
                    $album->update(['path_to_img' => $nextCover->path_to_img]);
                } else {
                    $album->update(['path_to_img' => null]);
                }
            }

            // Delete the photo record
            $photo->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Photo deleted successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Photo deletion error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete photo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Method to set cover photo
    public function setCoverPhoto($photoId)
    {
        $user = Auth::user();
        
        // Get the user's profile album
        $album = Album::where('user_id', $user->id)
            ->where('titre', 'Profile Gallery')
            ->first();
        
        if (!$album) {
            return response()->json([
                'success' => false,
                'message' => 'Profile album not found',
            ], 404);
        }
        
        // Only set cover for photos from the profile album
        $photo = Photo::where('id', $photoId)
            ->where('user_id', $user->id)
            ->where('album_id', $album->id)
            ->firstOrFail();

        DB::beginTransaction();
        
        try {
            // Reset all other cover photos in THIS album
            Photo::where('album_id', $album->id)
                ->where('user_id', $user->id)
                ->update(['is_cover' => 0]);

            // Set this photo as cover
            $photo->update(['is_cover' => 1]);

            // Update album cover image
            $album->update([
                'path_to_img' => $photo->path_to_img,
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cover photo updated successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Cover photo update error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update cover photo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Method to reorder photos
    public function reorderPhotos(Request $request)
    {
        $user = Auth::user();
        
        // Get the user's profile album
        $album = Album::where('user_id', $user->id)
            ->where('titre', 'Profile Gallery')
            ->first();
        
        if (!$album) {
            return response()->json([
                'success' => false,
                'message' => 'Profile album not found',
            ], 404);
        }
        
        $request->validate([
            'photos' => 'required|array|min:1',
            'photos.*.id' => 'required|exists:photos,id,user_id,' . $user->id . ',album_id,' . $album->id,
            'photos.*.order' => 'required|integer|min:0',
        ]);

        DB::beginTransaction();
        
        try {
            foreach ($request->photos as $photoData) {
                Photo::where('id', $photoData['id'])
                    ->where('user_id', $user->id)
                    ->where('album_id', $album->id)
                    ->update(['order' => $photoData['order']]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Photo reordered successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Photo reorder error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder photos',
                'error' => $e->getMessage(),
            ], 500);
        }
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
     * Add custom service
     */
    public function addCustomService(Request $request, $centerId)
    {
        try {
            $user = Auth::user();
            $center = ProfileCentre::findOrFail($centerId);

            // Verify ownership
            if ($center->profile->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'price' => 'required|numeric|min:0',
                'unit' => 'required|string|max:50',
                'is_available' => 'boolean',
                'min_quantity' => 'integer|min:1',
                'max_quantity' => 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if custom service with same name already exists for this center
            $existingService = ProfileCenterService::where('profile_center_id', $centerId)
                ->whereNull('service_category_id')
                ->where('name', $request->name)
                ->first();

            if ($existingService) {
                return response()->json([
                    'success' => false,
                    'message' => 'A custom service with this name already exists'
                ], 409);
            }

            $serviceData = [
                'profile_center_id' => $centerId,
                'service_category_id' => null, // NULL for custom services
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'is_available' => $request->is_available ?? true,
                'is_standard' => false,
                'unit' => $request->unit,
                'min_quantity' => $request->min_quantity ?? 1,
                'max_quantity' => $request->max_quantity,
            ];

            $service = ProfileCenterService::create($serviceData);

            return response()->json([
                'success' => true,
                'message' => 'Custom service added successfully',
                'data' => $service
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add custom service',
                'error' => $e->getMessage()
            ], 500);
        }
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

        /**
     * Get service categories
     */
    public function getServiceCategories()
    {
        try {
            $categories = ServiceCategory::where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch service categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get center services
     */
    public function getCenterServices($centerId)
    {
        try {
            $user = Auth::user();
            $center = ProfileCentre::findOrFail($centerId);

            // Verify ownership
            if ($center->profile->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $services = ProfileCenterService::with('serviceCategory')
                ->where('profile_center_id', $centerId)
                ->orderBy('is_standard', 'desc')
                ->orderBy('created_at', 'asc')
                ->get();

            $formattedServices = $services->map(function($service) {
                // For custom services (service_category_id is NULL)
                if (is_null($service->service_category_id)) {
                    return [
                        'id' => $service->id,
                        'service_category_id' => null,
                        'name' => $service->name,
                        'description' => $service->description,
                        'price' => (float) $service->price,
                        'unit' => $service->unit,
                        'is_standard' => (bool) $service->is_standard,
                        'is_available' => (bool) $service->is_available,
                        'min_quantity' => $service->min_quantity,
                        'max_quantity' => $service->max_quantity,
                        'is_custom' => true, // Flag to identify custom services
                    ];
                }
                
                // For predefined category services
                return [
                    'id' => $service->id,
                    'service_category_id' => $service->service_category_id,
                    'name' => $service->serviceCategory->name,
                    'description' => $service->description ?? $service->serviceCategory->description,
                    'price' => (float) $service->price,
                    'unit' => $service->unit,
                    'is_standard' => (bool) $service->is_standard,
                    'is_available' => (bool) $service->is_available,
                    'min_quantity' => $service->min_quantity,
                    'max_quantity' => $service->max_quantity,
                    'is_custom' => false,
                ];
            });

            return response()->json($formattedServices);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch center services',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateCenterService(Request $request, $centerId, $serviceId = null)
    {
        try {
            $user = Auth::user();
            $center = ProfileCentre::findOrFail($centerId);

            // Verify ownership
            if ($center->profile->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'service_category_id' => 'nullable|exists:service_categories,id',
                'name' => 'nullable|string|max:255',
                'description' => 'required|string',
                'price' => 'required|numeric|min:0',
                'is_available' => 'boolean',
                'unit' => 'required|string|max:50',
                'min_quantity' => 'integer|min:1',
                'max_quantity' => 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Determine if this is a custom service (service_category_id is null or empty)
            $isCustomService = empty($request->service_category_id);
            
            // For new custom services, use the addCustomService method
            if (!$serviceId && $isCustomService) {
                return $this->addCustomService($request, $centerId);
            }

            $serviceData = [
                'profile_center_id' => $centerId,
                'price' => $request->price,
                'description' => $request->description,
                'is_available' => $request->is_available ?? true,
                'unit' => $request->unit,
                'min_quantity' => $request->min_quantity ?? 1,
                'max_quantity' => $request->max_quantity,
            ];

            // Handle custom services
            if ($isCustomService) {
                $serviceData['service_category_id'] = null;
                $serviceData['name'] = $request->name;
                $serviceData['is_standard'] = false;
            } else {
                // For category-based services
                $serviceData['service_category_id'] = $request->service_category_id;
                $serviceData['is_standard'] = false;
                
                $serviceCategory = ServiceCategory::find($request->service_category_id);
                if ($serviceCategory) {
                    $serviceData['name'] = $serviceCategory->name;
                    
                    // Check if price meets minimum requirement for standard categories
                    if ($serviceCategory->is_standard && $request->price < $serviceCategory->min_price) {
                        return response()->json([
                            'success' => false,
                            'message' => "Price must be at least {$serviceCategory->min_price} TND for this service"
                        ], 422);
                    }
                }
            }

            if ($serviceId) {
                // Update existing service
                $service = ProfileCenterService::where('id', $serviceId)
                    ->where('profile_center_id', $centerId)
                    ->firstOrFail();
                    
                $service->update($serviceData);
            } else {
                // Create new service
                // Check if service already exists
                $existingService = ProfileCenterService::where('profile_center_id', $centerId);
                
                if ($isCustomService) {
                    $existingService = $existingService->where('name', $request->name)
                        ->whereNull('service_category_id');
                } else {
                    $existingService = $existingService->where('service_category_id', $request->service_category_id);
                }
                
                $existingService = $existingService->first();

                if ($existingService) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Service already exists for this center'
                    ], 409);
                }

                $service = ProfileCenterService::create($serviceData);
            }

            return response()->json([
                'success' => true,
                'message' => $serviceId ? 'Service updated successfully' : 'Service added successfully',
                'data' => $service
            ]);
        } catch (\Exception $e) {
            \Log::error('updateCenterService error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            \Log::error('Request data: ' . json_encode($request->all()));
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update service',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }


    /**
     * Delete center service
     */
    public function deleteCenterService($centerId, $serviceId)
    {
        try {
            $user = Auth::user();
            $center = ProfileCentre::findOrFail($centerId);

            // Verify ownership
            if ($center->profile->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $service = ProfileCenterService::where('id', $serviceId)
                ->where('profile_center_id', $centerId)
                ->firstOrFail();

            // Don't allow deletion of standard service
            if ($service->is_standard) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete standard service'
                ], 422);
            }

            $service->delete();

            return response()->json([
                'success' => true,
                'message' => 'Service deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get center equipment
     */
    public function getCenterEquipment($centerId)
    {
        try {
            $user = Auth::user();
            $center = ProfileCentre::findOrFail($centerId);

            // Verify ownership
            if ($center->profile->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $equipment = ProfileCenterEquipment::where('profile_center_id', $centerId)->get();

            return response()->json($equipment);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch equipment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update center equipment
     */
    public function updateCenterEquipment(Request $request, $centerId)
    {
        try {
            $user = Auth::user();
            $center = ProfileCentre::findOrFail($centerId);

            // Verify ownership
            if ($center->profile->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'type' => 'required|in:toilets,drinking_water,electricity,parking,wifi,showers,security,kitchen,bbq_area,swimming_pool',
                'is_available' => 'required|boolean',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $equipment = ProfileCenterEquipment::updateOrCreate(
                [
                    'profile_center_id' => $centerId,
                    'type' => $request->type,
                ],
                [
                    'is_available' => $request->is_available,
                    'notes' => $request->notes,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Equipment updated successfully',
                'data' => $equipment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update equipment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}