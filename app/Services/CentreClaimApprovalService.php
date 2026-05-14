<?php

namespace App\Services;

use App\Models\Album;
use App\Models\CampingCentre;
use App\Models\CentreClaim;
use App\Models\Photo;

class CentreClaimApprovalService
{
    public function approve(CentreClaim $claim, int $reviewerId, ?string $adminNote = null): void
    {
        $campingCentre = $claim->centre;
        $claimUser = \App\Models\User::with('profile.profileCentre')->find($claim->user_id);

        if ($claimUser) {
            $claimUser->update(['is_active' => 1]);
        }

        $profile = $claimUser?->profile;
        // Use a direct query instead of the hasOne relationship to avoid returning
        // null when the relation is not eager-loaded, which would cause a duplicate
        // ProfileCentre to be created.
        $pc = $profile
            ? \App\Models\ProfileCentre::where('profile_id', $profile->id)->orderBy('price_per_night', 'desc')->first()
            : null;

        if ($campingCentre) {
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

            if ($profile && !$pc) {
                $pc = \App\Models\ProfileCentre::create([
                    'profile_id'    => $profile->id,
                    'name'          => $campingCentre->nom ?? null,
                    'latitude'      => $campingCentre->lat ?? null,
                    'longitude'     => $campingCentre->lng ?? null,
                    'disponibilite' => false,
                ]);
            } elseif ($pc) {
                $pcUpdates = [];
                if ($campingCentre->nom && !$pc->name)  $pcUpdates['name']      = $campingCentre->nom;
                if ($campingCentre->lat && !$pc->latitude)  $pcUpdates['latitude']  = $campingCentre->lat;
                if ($campingCentre->lng && !$pc->longitude) $pcUpdates['longitude'] = $campingCentre->lng;
                if (!empty($pcUpdates)) $pc->update($pcUpdates);
            }

            if ($profile && !$profile->cover_image && $campingCentre->image) {
                $profile->update(['cover_image' => $campingCentre->image]);
            }
        }

        if ($claimUser && $campingCentre) {
            Photo::where('camping_centre_id', $campingCentre->id)
                ->whereNull('user_id')
                ->update(['user_id' => $claimUser->id]);

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
                    if (!$albumHasCover && $i === 0) {
                        $photo->is_cover   = true;
                        $albumHasCover     = true;
                    }
                    $photo->save();
                }

                if ($profile && !$profile->cover_image) {
                    $first = $centrePhotos->first();
                    if ($first) {
                        $profile->update(['cover_image' => $first->path_to_img]);
                    }
                }
            }
        }

        if ($campingCentre) {
            $campingCentre->update([
                'user_id'           => $claim->user_id,
                'profile_centre_id' => $pc?->id,
                'validation_status' => 'approved',
            ]);

            // Unlink any auto-synced placeholder camping_centres that were created for
            // this user (by the admin auto-sync) before this real claim was approved.
            // Only removes dummy entries with no zones and no photos to prevent data loss.
            CampingCentre::where('user_id', $claim->user_id)
                ->where('id', '!=', $campingCentre->id)
                ->whereDoesntHave('zones')
                ->whereDoesntHave('photos')
                ->update([
                    'user_id'           => null,
                    'profile_centre_id' => null,
                    'validation_status' => 'pending',
                    'status'            => false,
                ]);
        }

        $claim->update([
            'status'      => 'approved',
            'admin_note'  => $adminNote,
            'reviewer_id' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        CentreClaim::where('centre_id', $claim->centre_id)
            ->where('id', '!=', $claim->id)
            ->where('status', 'pending')
            ->update([
                'status'      => 'rejected',
                'admin_note'  => 'Une autre demande a été approuvée pour ce centre.',
                'reviewer_id' => $reviewerId,
                'reviewed_at' => now(),
            ]);
    }
}
