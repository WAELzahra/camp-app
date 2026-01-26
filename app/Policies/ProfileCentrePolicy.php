<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ProfileCentre;
use Illuminate\Auth\Access\Response;

class ProfileCentrePolicy
{
    /**
     * Determine if the user can manage the center.
     */
    public function manage(User $user, ProfileCentre $profileCentre): bool
    {
        // Check if user owns this center
        return $user->id === $profileCentre->profile->user_id || 
               $user->hasRole('admin');
    }

    /**
     * Determine if the user can update the center.
     */
    public function update(User $user, ProfileCentre $profileCentre): bool
    {
        return $this->manage($user, $profileCentre);
    }

    /**
     * Determine if the user can delete the center.
     */
    public function delete(User $user, ProfileCentre $profileCentre): bool
    {
        return $this->manage($user, $profileCentre);
    }
}