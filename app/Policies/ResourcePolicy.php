<?php
// app/Policies/ResourcePolicy.php

namespace App\Policies;

use App\Models\User;
use App\Models\Resource;
use Illuminate\Auth\Access\HandlesAuthorization;

class ResourcePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any resources.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view resources
        return true;
    }

    /**
     * Determine whether the user can view the resource.
     */
    public function view(User $user, Resource $resource): bool
    {
        // Students and counselors can only view published resources
        if (in_array($user->role, ['student', 'counselor'])) {
            return $resource->is_published;
        }

        // Admins can view all resources
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can create resources.
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can update the resource.
     */
    public function update(User $user, Resource $resource): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can delete the resource.
     */
    public function delete(User $user, Resource $resource): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can access the resource content.
     */
    public function access(User $user, Resource $resource): bool
    {
        // All users can access published resources
        return $resource->is_published;
    }

    /**
     * Determine whether the user can provide feedback on resources.
     */
    public function provideFeedback(User $user, Resource $resource): bool
    {
        // All users can provide feedback on published resources
        return $resource->is_published;
    }

    /**
     * Determine whether the user can bookmark resources.
     */
    public function bookmark(User $user, Resource $resource): bool
    {
        // All users can bookmark published resources
        return $resource->is_published;
    }

    /**
     * Determine whether the user can view resource analytics.
     */
    public function viewAnalytics(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can publish/unpublish resources.
     */
    public function publish(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can feature/unfeature resources.
     */
    public function feature(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can perform bulk actions on resources.
     */
    public function bulkAction(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can export resources.
     */
    public function export(User $user): bool
    {
        return $user->role === 'admin';
    }
}