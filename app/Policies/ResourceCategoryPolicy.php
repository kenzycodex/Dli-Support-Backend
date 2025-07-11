<?php
// app/Policies/ResourceCategoryPolicy.php

namespace App\Policies;

use App\Models\User;
use App\Models\ResourceCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class ResourceCategoryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any resource categories.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view resource categories
        return true;
    }

    /**
     * Determine whether the user can view the resource category.
     */
    public function view(User $user, ResourceCategory $resourceCategory): bool
    {
        // Students and counselors can only view active categories
        if (in_array($user->role, ['student', 'counselor'])) {
            return $resourceCategory->is_active;
        }

        // Admins can view all categories
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can create resource categories.
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can update the resource category.
     */
    public function update(User $user, ResourceCategory $resourceCategory): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can delete the resource category.
     */
    public function delete(User $user, ResourceCategory $resourceCategory): bool
    {
        return $user->role === 'admin';
    }
}