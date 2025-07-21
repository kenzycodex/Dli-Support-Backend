<?php

// app/Policies/TicketController.php

namespace App\Policies;

use App\Models\User;
use App\Models\Ticket;
use Illuminate\Auth\Access\HandlesAuthorization;

class TicketPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view tickets (filtered by role in controller)
    }

    public function view(User $user, Ticket $ticket): bool
    {
        // Admin can view all tickets
        if ($user->role === 'admin') {
            return true;
        }

        // Student can only view their own tickets
        if ($user->role === 'student') {
            return $ticket->user_id === $user->id;
        }

        // ENHANCED: Counselors can view tickets in their categories OR assigned to them
        if ($user->role === 'counselor') {
            return $ticket->assigned_to === $user->id || 
                   in_array($ticket->category, ['mental-health', 'crisis', 'academic', 'general', 'other']);
        }

        // Advisors can view assigned tickets
        if ($user->role === 'advisor') {
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
        // Admin can update any ticket
        if ($user->role === 'admin') {
            return true;
        }

        // ENHANCED: Students can update their own tickets if not closed/resolved
        if ($user->role === 'student' && $ticket->user_id === $user->id) {
            return !in_array($ticket->status, ['Closed', 'Resolved']);
        }

        // Staff can update assigned tickets
        if (in_array($user->role, ['counselor', 'advisor'])) {
            return $ticket->assigned_to === $user->id;
        }

        return false;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->role === 'admin';
    }

    // ENHANCED: Additional permission methods for better granular control
    
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->role === 'admin';
    }

    public function addResponse(User $user, Ticket $ticket): bool
    {
        // Admin can respond to any ticket
        if ($user->role === 'admin') {
            return true;
        }

        // Student can respond to their own tickets if not closed
        if ($user->role === 'student' && $ticket->user_id === $user->id) {
            return !in_array($ticket->status, ['Closed']);
        }

        // Staff can respond to assigned tickets
        if (in_array($user->role, ['counselor', 'advisor'])) {
            return $ticket->assigned_to === $user->id;
        }

        return false;
    }

    public function downloadAttachment(User $user, Ticket $ticket): bool
    {
        // Use same logic as view - if you can view the ticket, you can download attachments
        return $this->view($user, $ticket);
    }

    public function manageTags(User $user, Ticket $ticket): bool
    {
        return in_array($user->role, ['counselor', 'admin']);
    }
}