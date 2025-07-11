<?php
// app/Policies/FAQPolicy.php

namespace App\Policies;

use App\Models\User;
use App\Models\FAQ;
use Illuminate\Auth\Access\HandlesAuthorization;

class FAQPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any FAQs.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view FAQs
        return true;
    }

    /**
     * Determine whether the user can view the FAQ.
     */
    public function view(User $user, FAQ $faq): bool
    {
        // Students and counselors can only view published FAQs
        if (in_array($user->role, ['student', 'counselor'])) {
            return $faq->is_published;
        }

        // Admins can view all FAQs
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can create FAQs.
     */
    public function create(User $user): bool
    {
        // Only admins can create FAQs directly
        // Counselors can suggest content (handled separately)
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can suggest FAQ content.
     */
    public function suggest(User $user): bool
    {
        // Counselors and admins can suggest content
        return in_array($user->role, ['counselor', 'admin']);
    }

    /**
     * Determine whether the user can update the FAQ.
     */
    public function update(User $user, FAQ $faq): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can delete the FAQ.
     */
    public function delete(User $user, FAQ $faq): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can provide feedback on FAQs.
     */
    public function provideFeedback(User $user, FAQ $faq): bool
    {
        // All users can provide feedback on published FAQs
        return $faq->is_published;
    }

    /**
     * Determine whether the user can view FAQ analytics.
     */
    public function viewAnalytics(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can publish/unpublish FAQs.
     */
    public function publish(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can feature/unfeature FAQs.
     */
    public function feature(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can perform bulk actions on FAQs.
     */
    public function bulkAction(User $user): bool
    {
        return $user->role === 'admin';
    }
}