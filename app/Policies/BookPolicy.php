<?php

namespace App\Policies;

use App\Models\User;

class BookPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function admin(User $user)
    {
        return $user->role === 'admin';
    }
}
