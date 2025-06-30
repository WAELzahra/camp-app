<?php

namespace App\Policies;

use App\Models\Events;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Events $events): bool
    {
        //
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user)
{
    return $user->profile->type === 'groupe';
}

public function update(User $user, Events $event)
{
    return $user->id === $event->group_id || $user->role->name === 'admin';
}

public function validate(User $user)
{
    return $user->role->name === 'admin';
}


    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Events $events): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Events $events): bool
    {
        //
    }
}
