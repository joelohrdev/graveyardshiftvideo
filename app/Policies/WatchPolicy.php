<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Watch;

class WatchPolicy
{
    /**
     * Determine whether the user can update the watch.
     */
    public function update(User $user, Watch $watch): bool
    {
        return $watch->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the watch.
     */
    public function delete(User $user, Watch $watch): bool
    {
        return $watch->user_id === $user->id;
    }
}
