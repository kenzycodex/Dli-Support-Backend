<?php
// Step 4: Create app/Policies/TicketPolicy.php

namespace App\Policies;

use App\Models\User;
use App\Models\Ticket;
use Illuminate\Auth\Access\HandlesAuthorization;

class TicketPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view tickets
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'student') {
            return $ticket->user_id === $user->id;
        }

        if (in_array($user->role, ['counselor', 'advisor'])) {
            return $ticket->assigned_to === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['student', 'admin']);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if (in_array($user->role, ['counselor', 'advisor'])) {
            return $ticket->assigned_to === $user->id;
        }

        return false;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->role === 'admin';
    }
}