<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\CampingCentre;
use App\Models\CentreClaim;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CentreClaimController extends Controller
{
    // ─── PUBLIC ──────────────────────────────────────────────────────────────

    /**
     * GET /centres/search-unlinked
     * Retourne les centres sans propriétaire (user_id IS NULL).
     */
    public function searchUnlinked(Request $request)
    {
        $q        = $request->query('q', '');
        $perPage  = (int) $request->query('per_page', 10);

        $centres = CampingCentre::whereNull('user_id')
            ->when($q !== '', fn($query) =>
                $query->where('nom', 'like', "%{$q}%")
                      ->orWhere('adresse', 'like', "%{$q}%")
            )
            ->select('id', 'nom', 'adresse', 'type', 'image', 'lat', 'lng')
            ->with('coverPhoto')
            ->orderBy('nom')
            ->get();

        $centres->transform(function ($c) {
            if (!$c->image && $c->coverPhoto) {
                $c->image = asset('storage/' . $c->coverPhoto->path_to_img);
            } elseif ($c->image && !str_starts_with($c->image, 'http')) {
                $c->image = asset('storage/' . $c->image);
            }
            unset($c->coverPhoto);
            return $c;
        });

        return response()->json([
            'status' => 'success',
            'data'   => $centres,
        ]);
    }

    // ─── AUTH ─────────────────────────────────────────────────────────────────

    /**
     * POST /centres/{centreId}/claim
     * Soumet une demande de partenariat pour un centre existant.
     */
    public function submitClaim(Request $request, int $centreId)
    {
        $request->validate([
            'message'        => 'required|string|min:10|max:2000',
            'proof_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $centre = CampingCentre::findOrFail($centreId);

        if ($centre->isRegistered()) {
            return response()->json([
                'message' => 'Ce centre est déjà lié à un propriétaire.',
            ], 422);
        }

        $userId = Auth::id();

        // Vérifier s'il y a déjà une demande pour cet utilisateur + ce centre
        $existing = CentreClaim::where('centre_id', $centreId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Vous avez déjà soumis une demande pour ce centre.',
                'claim'   => $this->formatClaim($existing),
            ], 422);
        }

        $documentPath = null;
        if ($request->hasFile('proof_document')) {
            $documentPath = $request->file('proof_document')
                ->store('claims/documents', 'public');
        }

        $claim = CentreClaim::create([
            'centre_id'      => $centreId,
            'user_id'        => $userId,
            'message'        => $request->message,
            'proof_document' => $documentPath,
            'status'         => 'pending',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Votre demande a été soumise avec succès.',
            'claim'   => $this->formatClaim($claim->load(['centre', 'user'])),
        ], 201);
    }

    /**
     * GET /my-centre-claim
     * Retourne la (ou les) demande(s) de l'utilisateur connecté.
     */
    public function myClaim()
    {
        $claims = CentreClaim::with(['centre', 'reviewer'])
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($c) => $this->formatClaim($c));

        return response()->json([
            'status' => 'success',
            'data'   => $claims,
        ]);
    }

    // ─── ADMIN ────────────────────────────────────────────────────────────────

    /**
     * GET /admin/claims
     * Liste toutes les demandes (avec filtre optionnel par status).
     */
    public function adminIndex(Request $request)
    {
        $status  = $request->query('status');
        $perPage = (int) $request->query('per_page', 15);

        $query = CentreClaim::with(['centre', 'user', 'reviewer'])
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        $paginated = $query->paginate($perPage);

        $formatted = $paginated->getCollection()
            ->map(fn($c) => $this->formatClaim($c));

        return response()->json([
            'status' => 'success',
            'data'   => [
                'data'         => $formatted,
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * POST /admin/claims/{id}/approve
     *
     * On approval:
     *  1. Activate the claimant account (is_active = 1)
     *  2. Sync camping_centre data → profile + profile_centre (non-destructive)
     *  3. Link camping_centre photos to the user so they appear on their profile
     *  4. Link camping_centre to the user
     *  5. Auto-reject other pending claims for the same centre
     */
    public function adminApprove(Request $request, int $id)
    {
        $request->validate([
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $claim = CentreClaim::with('centre')->findOrFail($id);

        if ($claim->status !== 'pending') {
            return response()->json(['message' => 'Cette demande a déjà été traitée.'], 422);
        }

        $campingCentre = $claim->centre;

        // ── 1. Activate the claimant account ──────────────────────────────────
        $claimUser = \App\Models\User::with('profile.profileCentre')->find($claim->user_id);
        if ($claimUser) {
            $claimUser->update(['is_active' => 1]);
        }

        $profile = $claimUser?->profile;
        $pc      = $profile?->profileCentre;

        // ── 2. Sync camping_centre data → profile + profile_centre ────────────
        if ($campingCentre) {

            // 2a. Sync the parent Profile (bio, address)
            if ($profile) {
                $profileUpdates = [];
                if ($campingCentre->description && !$profile->bio) {
                    $profileUpdates['bio'] = $campingCentre->description;
                }
                if ($campingCentre->adresse && !$profile->address) {
                    $profileUpdates['address'] = $campingCentre->adresse;
                }
                if (!empty($profileUpdates)) {
                    $profile->update($profileUpdates);
                }
            }

            // 2b. Create or update the ProfileCentre record.
            // Centre stays private (disponibilite = false) until the owner
            // completes their profile and manually publishes it.
            if ($profile && !$pc) {
                $pc = \App\Models\ProfileCentre::create([
                    'profile_id'    => $profile->id,
                    'name'          => $campingCentre->nom ?? null,
                    'latitude'      => $campingCentre->lat ?? null,
                    'longitude'     => $campingCentre->lng ?? null,
                    'disponibilite' => false,
                ]);
            } elseif ($pc) {
                // Only fill empty fields — never overwrite user data, never force-publish.
                $pcUpdates = [];
                if ($campingCentre->nom && !$pc->name) {
                    $pcUpdates['name'] = $campingCentre->nom;
                }
                if ($campingCentre->lat && !$pc->latitude) {
                    $pcUpdates['latitude'] = $campingCentre->lat;
                }
                if ($campingCentre->lng && !$pc->longitude) {
                    $pcUpdates['longitude'] = $campingCentre->lng;
                }
                if (!empty($pcUpdates)) {
                    $pc->update($pcUpdates);
                }
            }

            // 2c. Sync cover_image on profile from camping_centre image (if not set)
            if ($profile && !$profile->cover_image && $campingCentre->image) {
                $profile->update(['cover_image' => $campingCentre->image]);
            }
        }

        // ── 3. Link camping_centre photos to the user and into their gallery album ──
        if ($claimUser && $campingCentre) {
            // 3a. Assign user_id to all unowned photos of this camping_centre
            Photo::where('camping_centre_id', $campingCentre->id)
                ->whereNull('user_id')
                ->update(['user_id' => $claimUser->id]);

            // 3b. Move those photos into the 'Profile Gallery' album so they
            //     appear in the public center list and detail page (formatCenter
            //     only reads photos through this album).
            $centrePhotos = Photo::where('camping_centre_id', $campingCentre->id)
                ->where('user_id', $claimUser->id)
                ->whereNull('album_id')
                ->get();

            if ($centrePhotos->isNotEmpty()) {
                $album = Album::firstOrCreate(
                    ['user_id' => $claimUser->id, 'titre' => 'Profile Gallery'],
                    ['path_to_img' => null]
                );

                $albumHasCover = $album->photos()->where('is_cover', true)->exists();

                foreach ($centrePhotos as $i => $photo) {
                    $photo->album_id = $album->id;
                    // Mark first photo as cover if album has none yet
                    if (!$albumHasCover && $i === 0) {
                        $photo->is_cover   = true;
                        $albumHasCover     = true;
                    }
                    $photo->save();
                }

                // Sync profile cover_image from first photo if still empty
                if ($profile && !$profile->cover_image) {
                    $first = $centrePhotos->first();
                    if ($first) {
                        $profile->update(['cover_image' => $first->path_to_img]);
                    }
                }
            }
        }

        // ── 4. Link camping_centre to the user ────────────────────────────────
        $campingCentre->update([
            'user_id'           => $claim->user_id,
            'profile_centre_id' => $pc?->id,
            'validation_status' => 'approved',
        ]);

        $claim->update([
            'status'      => 'approved',
            'admin_note'  => $request->admin_note,
            'reviewer_id' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        // ── 5. Auto-reject other pending claims for the same centre ───────────
        CentreClaim::where('centre_id', $claim->centre_id)
            ->where('id', '!=', $claim->id)
            ->where('status', 'pending')
            ->update([
                'status'      => 'rejected',
                'admin_note'  => 'Une autre demande a été approuvée pour ce centre.',
                'reviewer_id' => Auth::id(),
                'reviewed_at' => now(),
            ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Demande approuvée. Le centre est maintenant lié au partenaire.',
            'claim'   => $this->formatClaim($claim->fresh(['centre', 'user', 'reviewer'])),
        ]);
    }

    /**
     * POST /admin/claims/{id}/reject
     */
    public function adminReject(Request $request, int $id)
    {
        $request->validate([
            'admin_note' => 'required|string|min:5|max:1000',
        ]);

        $claim = CentreClaim::findOrFail($id);

        if ($claim->status !== 'pending') {
            return response()->json(['message' => 'Cette demande a déjà été traitée.'], 422);
        }

        $claim->update([
            'status'      => 'rejected',
            'admin_note'  => $request->admin_note,
            'reviewer_id' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Demande rejetée.',
            'claim'   => $this->formatClaim($claim->fresh(['centre', 'user', 'reviewer'])),
        ]);
    }

    /**
     * POST /admin/claims/{id}/revoke
     * Cancel an approved partnership — unlinks the centre from its owner.
     */
    public function adminRevoke(Request $request, int $id)
    {
        $request->validate([
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $claim = CentreClaim::with('centre')->findOrFail($id);

        if ($claim->status !== 'approved') {
            return response()->json(['message' => 'Only approved partnerships can be revoked.'], 422);
        }

        // Remove the user link from the camping centre
        if ($claim->centre) {
            $claim->centre->update(['user_id' => null]);
        }

        $claim->update([
            'status'      => 'rejected',
            'admin_note'  => $request->admin_note ?? 'Partnership cancelled by admin.',
            'reviewer_id' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Partnership cancelled. The centre is now unlinked.',
            'claim'   => $this->formatClaim($claim->fresh(['centre', 'user', 'reviewer'])),
        ]);
    }

    // ─── HELPER ──────────────────────────────────────────────────────────────

    private function formatClaim(CentreClaim $claim): array
    {
        $docPath = $claim->proof_document;
        $docUrl  = $docPath
            ? (str_starts_with($docPath, 'http')
                ? $docPath
                : asset('storage/' . $docPath))
            : null;

        return [
            'id'                 => $claim->id,
            'status'             => $claim->status,
            'message'            => $claim->message,
            'proof_document'     => $claim->proof_document,
            'proof_document_url' => $docUrl,
            'admin_note'         => $claim->admin_note,
            'reviewed_at'        => $claim->reviewed_at?->toISOString(),
            'created_at'         => $claim->created_at->toISOString(),
            'centre' => $claim->centre ? [
                'id'      => $claim->centre->id,
                'nom'     => $claim->centre->nom,
                'adresse' => $claim->centre->adresse,
                'type'    => $claim->centre->type,
                'image'   => $claim->centre->image,
            ] : null,
            'user' => $claim->user ? [
                'id'           => $claim->user->id,
                'first_name'   => $claim->user->first_name,
                'last_name'    => $claim->user->last_name,
                'email'        => $claim->user->email,
                'phone_number' => $claim->user->phone_number,
            ] : null,
            'reviewer' => $claim->reviewer ? [
                'first_name' => $claim->reviewer->first_name,
                'last_name'  => $claim->reviewer->last_name,
            ] : null,
        ];
    }
}
