<?php
// app/Policies/HelpCategoryPolicy.php

namespace App\Policies;

use App\Models\User;
use App\Models\HelpCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class HelpCategoryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any help categories.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view help categories
        return true;
    }

    /**
     * Determine whether the user can view the help category.
     */
    public function view(User $user, HelpCategory $helpCategory): bool
    {
        // Students and counselors can only view active categories
        if (in_array($user->role, ['student', 'counselor'])) {
            return $helpCategory->is_active;
        }

        // Admins can view all categories
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can create help categories.
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can update the help category.
     */
    public function update(User $user, HelpCategory $helpCategory): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can delete the help category.
     */
    public function delete(User $user, HelpCategory $helpCategory): bool
    {
        return $user->role === 'admin';
    }
}