<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\LegalConsentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisteredUserController extends Controller
{
    /**
     * Affiche la vue d'inscription avec les rôles.
     */
    public function create(): \Illuminate\View\View
    {
        $roles = Role::where('name', '!=', 'admin')->get();

        return view('auth.register', compact('roles'));
    }

    public function store(RegisterRequest $request)
    {
        // Validation
        $validated = $request->validated();

        if (strtolower($validated['role']) === 'admin') {
            return response()->json(['error' => 'Ce rôle n\'est pas autorisé à l\'inscription.'], 403);
        }

        $role = Role::where('name', $validated['role'])->first();
        if (!$role) {
            return response()->json(['error' => 'Rôle invalide.'], 422);
        }

        $isActive = $role->name === 'campeur' ? 1 : 0;

        DB::beginTransaction();
        try {
            // 1️⃣ Créer l'utilisateur
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'ville' => $validated['ville'] ?? null,
                'date_naissance' => $validated['date_naissance'] ?? null,
                'sexe' => $validated['sexe'] ?? null,
                'langue' => $validated['langue'] ?? null,
                'password' => Hash::make($validated['password']),
                'role_id' => $role->id,
                'is_active' => $isActive,
                'first_login' => true,
                'nombre_signalement' => 0,
                // KYC (Task A-01): providers start blocked until admin verification;
                // campers stay active — their KYC only triggers on withdrawal/equipment rental.
                'kyc_status'     => $role->name === 'campeur' ? 'not_required' : 'pending',
                'account_status' => $role->name === 'campeur' ? 'active' : 'pending_kyc',
            ]);

            // 2️⃣ Créer le profile général
            // Map role name → profiles.type ENUM value
            // (roles.name = 'organizer' maps to profiles.type = 'groupe')
            $profileTypeMap = [
                'campeur' => 'campeur',
                'guide' => 'guide',
                'centre' => 'centre',
                'fournisseur' => 'fournisseur',
                'organizer' => 'groupe',
                'groupe' => 'groupe',
            ];
            $profileType = $profileTypeMap[$role->name] ?? $role->name;

            // Engagement mode (Task A-02): always starts in commission mode;
            // providers can switch it later from their profile settings.
            DB::table('profiles')->insert([
                'user_id' => $user->id,
                'bio' => null,
                'cover_image' => null,
                'type' => $profileType,
                'engagement_mode' => 'commission',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3️⃣ For centre role: create ProfileCentre + CampingCentre immediately
            // so the admin can find and approve the center from the dashboard
            // without waiting for the auto-sync to run.
            if ($role->name === 'centre') {
                $profile = \App\Models\Profile::where('user_id', $user->id)->first();

                $pc = \App\Models\ProfileCentre::create([
                    'profile_id' => $profile->id,
                    'name' => trim($user->first_name.' '.$user->last_name).' Center',
                    'disponibilite' => false,
                ]);

                \App\Models\CampingCentre::create([
                    'nom' => $pc->name,
                    'type' => 'centre',
                    'adresse' => $validated['adresse'] ?? $user->adresse ?? '',
                    'lat' => 0,
                    'lng' => 0,
                    'status' => false,
                    'validation_status' => 'pending',
                    'is_partner' => true,
                    'user_id' => $user->id,
                    'profile_centre_id' => $pc->id,
                ]);
            }

            // 4️⃣ Handle supplier invitation token
            if (!empty($validated['invitation_token']) && $role->name === 'fournisseur') {
                $invitation = \App\Models\SupplierInvitation::where('token', $validated['invitation_token'])
                    ->where('status', 'pending')
                    ->where('email', $validated['email'])
                    ->first();

                if ($invitation && !\Carbon\Carbon::parse($invitation->expires_at)->isPast()) {
                    $invitation->update([
                        'status' => 'registered',
                        'registered_at' => now(),
                        'supplier_id' => $user->id,
                    ]);

                    \App\Models\OrganizerSupplierLink::create([
                        'organizer_id' => $invitation->organizer_id,
                        'supplier_id' => $user->id,
                        'status' => 'accepted',
                        'responded_at' => now(),
                    ]);
                }
            }

            // 5️⃣ Record acceptance of all currently active legal documents.
            // IP + user-agent captured here (server-side) for legal proof.
            $activeDocIds = LegalConsentService::getActiveDocuments()->pluck('id')->toArray();
            if (!empty($activeDocIds)) {
                LegalConsentService::recordAcceptances(
                    user:        $user,
                    documentIds: $activeDocIds,
                    ipAddress:   $request->ip(),
                    userAgent:   $request->userAgent() ?? '',
                    method:      'registration'
                );
            }

            DB::commit();

            $verificationService = new \App\Services\EmailVerificationService('both');
            $verificationService->sendVerification($user, 'both');
            event(new UserRegistered($user));

            return response()->json([
                'message' => 'Registration successful! Please verify your email.',
                'user' => [
                    'uuid' => $user->uuid,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                ],
                'requires_verification' => true,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Registration failed: '.$e->getMessage());
            \Log::error($e->getTraceAsString());

            return response()->json([
                'error' => 'Une erreur est survenue lors de l\'inscription.',
            ], 500);
        }
    }

    // Les fonctions handleCenterServices, handleCenterEquipment et handleCenterImages restent identiques
}
