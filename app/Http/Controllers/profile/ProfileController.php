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

            // Remove 'show_standard_service' as it's not in schema
            // Only update fields that exist in schema
            $data = $request->only([
                'name',
                'capacite',
                'price_per_night',
                'category',
                'disponibilite',
                'latitude',
                'longitude',
                'contact_email',
                'contact_phone',
                'manager_name',
                'established_date',
            ]);

            // Convert boolean for disponibilite
            if (isset($data['disponibilite'])) {
                $data['disponibilite'] = (bool) $data['disponibilite'] ? 1 : 0;
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
            // Validate request
            $validator = Validator::make($request->all(), [
                'current_password' => 'required',
                'new_password' => 'required|min:8|different:current_password|confirmed',
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

    public function searchUsers(Request $request)
    {
        $query = $request->get('q', '');
        $users = \App\Models\User::where('is_active', 1)
            ->where(function($q) use ($query) {
                $q->where('first_name', 'LIKE', "%{$query}%")
                ->orWhere('last_name', 'LIKE', "%{$query}%")
                ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->select('id', 'first_name', 'last_name', 'email', 'avatar', 'phone_number')
            ->limit(20)
            ->get();
        return response()->json(['success' => true, 'data' => $users]);
    }
    public function update(Request $request)
    {
        $user = Auth::user();
        
        DB::beginTransaction();
        
        try {
            \Log::info('=== PROFILE UPDATE START ===');
            \Log::info('User ID: ' . $user->id);
            \Log::info('Request data:', $request->all());
            
            // 1. Update basic user data
            $userData = [];
            $userFields = ['first_name', 'last_name', 'email', 'phone_number', 'ville', 'date_naissance', 'sexe', 'langue'];
            
            foreach ($userFields as $field) {
                if ($request->has($field)) {
                    $userData[$field] = $request->input($field);
                }
            }
            
            if (!empty($userData)) {
                $user->update($userData);
                \Log::info('User data updated:', $userData);
            }

            // 2. Get or create profile
            $profile = $user->profile;
            if (!$profile) {
                $profileData = [
                    'user_id' => $user->id,
                    'type' => $this->determineUserType($user->role_id),
                ];
                
                $profileFields = ['bio', 'cover_image', 'activities', 'city', 'address'];
                foreach ($profileFields as $field) {
                    if ($request->has($field)) {
                        $profileData[$field] = $request->$field;
                    }
                }
                
                $profile = Profile::create($profileData);
                \Log::info('Profile created:', ['profile_id' => $profile->id]);
            } else {
                $profileUpdateData = [];
                $profileFields = ['bio', 'cover_image', 'activities', 'city', 'address'];
                
                foreach ($profileFields as $field) {
                    if ($request->has($field)) {
                        $profileUpdateData[$field] = $request->$field;
                    }
                }
                
                if (!empty($profileUpdateData)) {
                    $profile->update($profileUpdateData);
                    \Log::info('Profile updated:', $profileUpdateData);
                }
            }

            // 3. Update specific profile details based on user type
            $userType = $profile->type;
            \Log::info('User type:', ['type' => $userType]);
            
            switch ($userType) {
                case 'campeur':
                    // Activities already handled above
                    break;
                    
                case 'guide':
                    $guideData = $request->only([
                        'experience',
                        'tarif',
                        'zone_travail',
                        'certificat_type',
                        'certificat_expiration',
                    ]);
                    
                    // Convert numeric fields
                    if (isset($guideData['experience'])) {
                        $guideData['experience'] = (int) $guideData['experience'];
                    }
                    if (isset($guideData['tarif'])) {
                        $guideData['tarif'] = (float) $guideData['tarif'];
                    }
                    
                    // Handle certificat upload
                    if ($request->hasFile('certificat_file')) {
                        $file = $request->file('certificat_file');
                        
                        $validator = Validator::make($request->all(), [
                            'certificat_file' => 'file|mimes:jpeg,png,jpg,pdf|max:5120',
                        ]);
                        
                        if ($validator->fails()) {
                            throw new \Exception('Certificat validation failed: ' . json_encode($validator->errors()));
                        }
                        
                        // Get or create guide
                        $guide = $profile->profileGuide;
                        if (!$guide) {
                            $guide = ProfileGuide::create(['profile_id' => $profile->id]);
                        }
                        
                        // Delete old certificat if exists
                        if ($guide->certificat_path && Storage::disk('public')->exists($guide->certificat_path)) {
                            Storage::disk('public')->delete($guide->certificat_path);
                        }
                        
                        // Store new certificat
                        $directory = 'documents/guides';
                        $filename = 'certificat_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                        $path = $file->storeAs($directory, $filename, 'public');
                        $guideData['certificat_path'] = $path;
                        
                        \Log::info('Certificat uploaded:', ['path' => $path]);
                    }
                    
                    // Handle certificat deletion
                    if ($request->has('delete_certificat') && $request->delete_certificat == '1') {
                        $guide = $profile->profileGuide;
                        if ($guide && $guide->certificat_path) {
                            if (Storage::disk('public')->exists($guide->certificat_path)) {
                                Storage::disk('public')->delete($guide->certificat_path);
                            }
                            $guideData['certificat_path'] = null;
                        }
                    }
                    
                    if ($profile->profileGuide) {
                        $profile->profileGuide->update($guideData);
                        \Log::info('Guide updated:', $guideData);
                    } else {
                        $guideData['profile_id'] = $profile->id;
                        ProfileGuide::create($guideData);
                        \Log::info('Guide created:', $guideData);
                    }
                    break;

                case 'centre':
                    $centreData = $request->only([
                        'name',
                        'contact_email',
                        'contact_phone',
                        'manager_name',
                        'capacite',
                        'price_per_night',
                        'category',
                        'disponibilite',
                        'document_legal_type',
                        'document_legal_expiration',
                        'latitude',
                        'longitude',
                        'established_date',
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
                    
                    // Handle document legal upload - USE legal_document NOT document_legal_path
                    if ($request->hasFile('document_legal_file')) {
                        $file = $request->file('document_legal_file');
                        
                        $validator = Validator::make($request->all(), [
                            'document_legal_file' => 'file|mimes:jpeg,png,jpg,pdf|max:5120',
                        ]);
                        
                        if ($validator->fails()) {
                            throw new \Exception('Legal document validation failed: ' . json_encode($validator->errors()));
                        }
                        
                        // Get or create centre
                        $centre = $profile->profileCentre;
                        if (!$centre) {
                            $centre = ProfileCentre::create(['profile_id' => $profile->id]);
                        }
                        
                        // Delete old document if exists (check both fields for backward compatibility)
                        if ($centre->legal_document && Storage::disk('public')->exists($centre->legal_document)) {
                            Storage::disk('public')->delete($centre->legal_document);
                        }
                        
                        // Store new document - USE legal_document field
                        $directory = 'documents/centres';
                        $filename = 'legal_doc_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                        $path = $file->storeAs($directory, $filename, 'public');
                        $centreData['legal_document'] = $path; // Changed from document_legal_path
                        
                        \Log::info('Legal document uploaded:', ['path' => $path]);
                    }
                    
                    // Handle delete document flag - USE legal_document
                    if ($request->has('delete_document') && $request->delete_document == '1') {
                        $centre = $profile->profileCentre;
                        if ($centre) {
                            if ($centre->legal_document && Storage::disk('public')->exists($centre->legal_document)) {
                                Storage::disk('public')->delete($centre->legal_document);
                            }
                            $centreData['legal_document'] = null; // Changed from document_legal_path
                            $centreData['document_legal_type'] = null;
                            $centreData['document_legal_expiration'] = null;
                        }
                    }
                    
                    if ($profile->profileCentre) {
                        $profile->profileCentre->update($centreData);
                        \Log::info('Centre updated:', $centreData);
                    } else {
                        $centreData['profile_id'] = $profile->id;
                        ProfileCentre::create($centreData);
                        \Log::info('Centre created:', $centreData);
                    }
                    break;

                case 'groupe':
                    $groupeData = $request->only([
                        'nom_groupe',
                        'id_album_photo', // Added missing field
                        'id_annonce',      // Added missing field
                    ]);
                    
                    // Handle patente upload
                    if ($request->hasFile('patente_file')) {
                        $file = $request->file('patente_file');
                        
                        $validator = Validator::make($request->all(), [
                            'patente_file' => 'file|mimes:jpeg,png,jpg,pdf|max:5120',
                        ]);
                        
                        if ($validator->fails()) {
                            throw new \Exception('Patente validation failed: ' . json_encode($validator->errors()));
                        }
                        
                        // Get or create groupe
                        $groupe = $profile->profileGroupe;
                        if (!$groupe) {
                            $groupe = ProfileGroupe::create(['profile_id' => $profile->id]);
                        }
                        
                        // Delete old patente if exists
                        if ($groupe->patente_path && Storage::disk('public')->exists($groupe->patente_path)) {
                            Storage::disk('public')->delete($groupe->patente_path);
                        }
                        
                        // Store new patente
                        $directory = 'documents/groupes';
                        $filename = 'patente_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                        $path = $file->storeAs($directory, $filename, 'public');
                        $groupeData['patente_path'] = $path;
                        
                        \Log::info('Patente uploaded:', ['path' => $path]);
                    }
                    
                    // Handle patente deletion
                    if ($request->has('delete_patente') && $request->delete_patente == '1') {
                        $groupe = $profile->profileGroupe;
                        if ($groupe && $groupe->patente_path) {
                            if (Storage::disk('public')->exists($groupe->patente_path)) {
                                Storage::disk('public')->delete($groupe->patente_path);
                            }
                            $groupeData['patente_path'] = null;
                        }
                    }
                    
                    if ($profile->profileGroupe) {
                        $profile->profileGroupe->update($groupeData);
                        \Log::info('Groupe updated:', $groupeData);
                    } else {
                        $groupeData['profile_id'] = $profile->id;
                        ProfileGroupe::create($groupeData);
                        \Log::info('Groupe created:', $groupeData);
                    }
                    break;

                case 'fournisseur':
                    $fournisseurData = $request->only([
                        'intervale_prix',
                        'product_category',
                    ]);
                    
                    if ($profile->profileFournisseur) {
                        $profile->profileFournisseur->update($fournisseurData);
                        \Log::info('Fournisseur updated:', $fournisseurData);
                    } else {
                        $fournisseurData['profile_id'] = $profile->id;
                        ProfileFournisseur::create($fournisseurData);
                        \Log::info('Fournisseur created:', $fournisseurData);
                    }
                    break;
            }

            // Handle CIN document upload (common for all profiles)
            if ($request->hasFile('cin_file')) {
                $file = $request->file('cin_file');
                
                $validator = Validator::make($request->all(), [
                    'cin_file' => 'file|mimes:jpeg,png,jpg,pdf|max:5120',
                ]);
                
                if ($validator->fails()) {
                    throw new \Exception('CIN validation failed: ' . json_encode($validator->errors()));
                }
                
                // Delete old CIN if exists
                if ($profile->cin_path && Storage::disk('public')->exists($profile->cin_path)) {
                    Storage::disk('public')->delete($profile->cin_path);
                }
                
                // Store new CIN
                $directory = 'documents/cin';
                $filename = 'cin_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs($directory, $filename, 'public');
                $profile->update(['cin_path' => $path]);
                
                \Log::info('CIN uploaded:', ['path' => $path]);
            }
            
            // Handle CIN deletion
            if ($request->has('delete_cin') && $request->delete_cin == '1') {
                if ($profile->cin_path) {
                    if (Storage::disk('public')->exists($profile->cin_path)) {
                        Storage::disk('public')->delete($profile->cin_path);
                    }
                    $profile->update(['cin_path' => null]);
                    \Log::info('CIN deleted');
                }
            }
            
            DB::commit();
            
            \Log::info('Profile updated successfully for user: ' . $user->id);
            
            return response()->json([
                'message' => 'Profile updated successfully',
                'user' => $user->fresh(),
                'profile' => $profile->fresh(),
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Profile update error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'message' => 'Failed to update profile',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            \Log::info('=== PROFILE UPDATE END ===');
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
            'message' => 'Avatar updated successfully.',
            'avatar_url' => asset('storage/' . $avatarPath),
        ]);
    }

    public function updateInfo(Request $request)
    {
        $user = Auth::user();
        
        $request->validate([
            'album_title' => 'required|string|max:255',
            'album_description' => 'nullable|string',
        ]);

        try {
            $albumTitle = $request->input('album_title');
            $albumDescription = $request->input('album_description', null);
            
            $album = Album::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'titre' => $albumTitle,
                    'description' => $albumDescription,
                ]
            );

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
        
        $request->validate([
            'photos' => 'required|array|min:1',
            'photos.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:25600',
            'album_title' => 'nullable|string|max:255',
            'album_description' => 'nullable|string',
        ]);

        DB::beginTransaction();
        
        try {
            $albumTitle = $request->input('album_title', 'Profile Gallery');
            $albumDescription = $request->input('album_description', null);
            
            $album = Album::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'titre' => 'Profile Gallery',
                ],
                [
                    'titre' => $albumTitle,
                    'description' => $albumDescription,
                ]
            );

            $updateData = [
                'titre' => $albumTitle,
                'updated_at' => now(),
            ];
            
            if ($request->has('album_description')) {
                $updateData['description'] = $albumDescription;
            }
            
            $album->update($updateData);

            $uploadedPhotos = [];
            $order = $album->photos()->max('order') ?? 0;

            foreach ($request->file('photos') as $index => $photo) {
                $originalName = pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $photo->getClientOriginalExtension();
                $filename = $originalName . '_' . time() . '_' . uniqid() . '.' . $extension;
                
                $path = $photo->storeAs('profile_photos', $filename, 'public');
                
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

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload photos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getProfilePhotos()
    {
        try {
            $user = Auth::user();
            
            $album = Album::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'titre' => 'Profile Gallery',
                ],
                [
                    'titre' => 'Profile Gallery',
                    'description' => 'User profile gallery images',
                ]
            );

            $photos = Photo::where('user_id', $user->id)
                ->where('album_id', $album->id)
                ->orderBy('order', 'asc')
                ->orderBy('created_at', 'desc')
                ->get();

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
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile photos',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function deletePhoto($photoId)
    {
        $user = Auth::user();
        
        $album = Album::where('user_id', $user->id)
            ->where('titre', 'Profile Gallery')
            ->first();
        
        if (!$album) {
            return response()->json([
                'success' => false,
                'message' => 'Profile album not found',
            ], 404);
        }
        
        $photo = Photo::where('id', $photoId)
            ->where('user_id', $user->id)
            ->where('album_id', $album->id)
            ->firstOrFail();

        DB::beginTransaction();
        
        try {
            if (Storage::disk('public')->exists($photo->path_to_img)) {
                Storage::disk('public')->delete($photo->path_to_img);
            }

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

    public function setCoverPhoto($photoId)
    {
        $user = Auth::user();
        
        $album = Album::where('user_id', $user->id)
            ->where('titre', 'Profile Gallery')
            ->first();
        
        if (!$album) {
            return response()->json([
                'success' => false,
                'message' => 'Profile album not found',
            ], 404);
        }
        
        $photo = Photo::where('id', $photoId)
            ->where('user_id', $user->id)
            ->where('album_id', $album->id)
            ->firstOrFail();

        DB::beginTransaction();
        
        try {
            Photo::where('album_id', $album->id)
                ->where('user_id', $user->id)
                ->update(['is_cover' => 0]);

            $photo->update(['is_cover' => 1]);

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

    public function reorderPhotos(Request $request)
    {
        $user = Auth::user();
        
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

    public function showById($id)
    {
        $user = User::findOrFail($id);
        
        $userData = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'ville' => $user->ville,
            'avatar' => $user->avatar,
            'role_id' => $user->role_id,
        ];
        
        if ($user->profile) {
            $profile = $user->profile;
            
            $data = [
                'user' => $userData,
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

                    $data['feedback_summary'] = [
                        'average_note' => round($average, 2),
                        'feedback_count' => \App\Models\Feedbacks::where('target_id', $user->id)
                            ->where('type', 'groupe')
                            ->where('status', 'approved')
                            ->count(),
                        'latest_feedbacks' => $feedbacks,
                    ];
                    break;
                case 'fournisseur':
                    $data['details'] = $profile->profileFournisseur;
                    break;
            }

            return response()->json($data);
        }

        return response()->json(['user' => $userData]);
    }

    public function addCustomService(Request $request, $centerId)
    {
        try {
            $user = Auth::user();
            $center = ProfileCentre::findOrFail($centerId);

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
                'service_category_id' => null,
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

    public function getCenterServices($centerId)
    {
        try {
            $center = ProfileCentre::find($centerId);
            
            if (!$center) {
                return response()->json([
                    'success' => false,
                    'message' => 'Center not found'
                ], 404);
            }

            $services = ProfileCenterService::with('serviceCategory')
                ->where('profile_center_id', $centerId)
                ->where('is_available', true)
                ->orderBy('is_standard', 'desc')
                ->orderBy('created_at', 'asc')
                ->get();

            $formattedServices = $services->map(function($service) {
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
                        'is_custom' => true,
                    ];
                }
                
                return [
                    'id' => $service->id,
                    'service_category_id' => $service->service_category_id,
                    'name' => $service->serviceCategory ? $service->serviceCategory->name : 'Service',
                    'description' => $service->description ?? ($service->serviceCategory ? $service->serviceCategory->description : ''),
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

            $isCustomService = empty($request->service_category_id);
            
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

            if ($isCustomService) {
                $serviceData['service_category_id'] = null;
                $serviceData['name'] = $request->name;
                $serviceData['is_standard'] = false;
            } else {
                $serviceData['service_category_id'] = $request->service_category_id;
                $serviceData['is_standard'] = false;
                
                $serviceCategory = ServiceCategory::find($request->service_category_id);
                if ($serviceCategory) {
                    $serviceData['name'] = $serviceCategory->name;
                    
                    if ($serviceCategory->is_standard && $request->price < $serviceCategory->min_price) {
                        return response()->json([
                            'success' => false,
                            'message' => "Price must be at least {$serviceCategory->min_price} TND for this service"
                        ], 422);
                    }
                }
            }

            if ($serviceId) {
                $service = ProfileCenterService::where('id', $serviceId)
                    ->where('profile_center_id', $centerId)
                    ->firstOrFail();
                    
                $service->update($serviceData);
            } else {
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
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update service',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    public function deleteCenterService($centerId, $serviceId)
    {
        try {
            $user = Auth::user();
            $center = ProfileCentre::findOrFail($centerId);

            if ($center->profile->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $service = ProfileCenterService::where('id', $serviceId)
                ->where('profile_center_id', $centerId)
                ->firstOrFail();

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

    public function getCenterEquipment($centerId)
    {
        try {
            $user = Auth::user();
            $center = ProfileCentre::findOrFail($centerId);

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

    public function updateCenterEquipment(Request $request, $centerId)
    {
        try {
            $user = Auth::user();
            $center = ProfileCentre::findOrFail($centerId);

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