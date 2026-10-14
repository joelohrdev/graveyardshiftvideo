<?php

namespace App\Policies;

use App\Models\Copy;
use App\Models\User;

class CopyPolicy
{
    /**
     * Determine whether the user can update the copy.
     */
    public function update(User $user, Copy $copy): bool
    {
        return $copy->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the copy.
     */
    public function delete(User $user, Copy $copy): bool
    {
        return $copy->user_id === $user->id;
    }
}
