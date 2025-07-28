<?php

// app/Policies/TicketPolicy.php - FIXED: Major issues causing 500 errors

namespace App\Policies;

use App\Models\User;
use App\Models\Ticket;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Log;

class TicketPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view tickets (filtered by role in controller)
    }

    /**
     * FIXED: The main issue causing 500 errors
     * 
     * The original code was checking $ticket->category as a string array,
     * but tickets have category_id (integer) relationships to TicketCategory model.
     * This was causing database/relationship errors.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        try {
            // Admin can view all tickets
            if ($user->role === 'admin') {
                return true;
            }

            // Student can only view their own tickets
            if ($user->role === 'student') {
                return $ticket->user_id === $user->id;
            }

            // FIXED: Counselors can view tickets assigned to them OR in their specialization categories
            if ($user->role === 'counselor') {
                // Can view if assigned to them
                if ($ticket->assigned_to === $user->id) {
                    return true;
                }
                
                // FIXED: Check if counselor has specialization in this ticket's category
                // This replaces the problematic string array check
                if ($ticket->category_id) {
                    $hasSpecialization = $user->counselorSpecializations()
                        ->where('category_id', $ticket->category_id)
                        ->where('is_available', true)
                        ->exists();
                    
                    if ($hasSpecialization) {
                        return true;
                    }
                }
                
                // Fallback: Allow viewing if no specific category restrictions
                return false;
            }

            // Advisors can view assigned tickets or tickets in their categories
            if ($user->role === 'advisor') {
                // Can view if assigned to them
                if ($ticket->assigned_to === $user->id) {
                    return true;
                }
                
                // FIXED: Check advisor specializations similar to counselors
                if ($ticket->category_id) {
                    $hasSpecialization = $user->counselorSpecializations()
                        ->where('category_id', $ticket->category_id)
                        ->where('is_available', true)
                        ->exists();
                    
                    return $hasSpecialization;
                }
                
                return false;
            }

            return false;
            
        } catch (\Exception $e) {
            // FIXED: Log the error instead of letting it crash
            Log::error('TicketPolicy view() error', [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'ticket_id' => $ticket->id,
                'ticket_category_id' => $ticket->category_id ?? 'null',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // For admins, allow access even if there's an error
            if ($user->role === 'admin') {
                return true;
            }
            
            // For students, allow access to their own tickets
            if ($user->role === 'student' && $ticket->user_id === $user->id) {
                return true;
            }
            
            // Otherwise, deny access when there's an error
            return false;
        }
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['student', 'admin']);
    }

    /**
     * FIXED: Similar fixes for update method
     */
    public function update(User $user, Ticket $ticket): bool
    {
        try {
            // Admin can update any ticket
            if ($user->role === 'admin') {
                return true;
            }

            // FIXED: Students can update their own tickets if not closed/resolved
            if ($user->role === 'student' && $ticket->user_id === $user->id) {
                return !in_array($ticket->status, ['Closed', 'Resolved']);
            }

            // FIXED: Staff can update assigned tickets
            if (in_array($user->role, ['counselor', 'advisor'])) {
                return $ticket->assigned_to === $user->id;
            }

            return false;
            
        } catch (\Exception $e) {
            Log::error('TicketPolicy update() error', [
                'user_id' => $user->id,
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage()
            ]);
            
            // Safe fallback
            return $user->role === 'admin';
        }
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

    /**
     * FIXED: Add response permission with proper error handling
     */
    public function addResponse(User $user, Ticket $ticket): bool
    {
        try {
            // Admin can respond to any ticket
            if ($user->role === 'admin') {
                return true;
            }

            // Student can respond to their own tickets if not closed
            if ($user->role === 'student' && $ticket->user_id === $user->id) {
                return !in_array($ticket->status, ['Closed']);
            }

            // FIXED: Staff can respond to assigned tickets
            if (in_array($user->role, ['counselor', 'advisor'])) {
                return $ticket->assigned_to === $user->id;
            }

            return false;
            
        } catch (\Exception $e) {
            Log::error('TicketPolicy addResponse() error', [
                'user_id' => $user->id,
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage()
            ]);
            
            // Safe fallback for response permissions
            if ($user->role === 'admin') {
                return true;
            }
            
            if ($user->role === 'student' && $ticket->user_id === $user->id) {
                return true;
            }
            
            return false;
        }
    }

    /**
     * FIXED: Download attachment permission using same logic as view
     */
    public function downloadAttachment(User $user, Ticket $ticket): bool
    {
        // Use same logic as view - if you can view the ticket, you can download attachments
        return $this->view($user, $ticket);
    }

    public function manageTags(User $user, Ticket $ticket): bool
    {
        try {
            return in_array($user->role, ['counselor', 'admin']) && 
                   ($user->role === 'admin' || $ticket->assigned_to === $user->id);
        } catch (\Exception $e) {
            Log::error('TicketPolicy manageTags() error', [
                'user_id' => $user->id,
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage()
            ]);
            
            return $user->role === 'admin';
        }
    }

    /**
     * HELPER: Check if user has category specialization (for reusability)
     */
    private function hasSpecializationInCategory(User $user, int $categoryId): bool
    {
        try {
            return $user->counselorSpecializations()
                ->where('category_id', $categoryId)
                ->where('is_available', true)
                ->exists();
        } catch (\Exception $e) {
            Log::error('TicketPolicy hasSpecializationInCategory() error', [
                'user_id' => $user->id,
                'category_id' => $categoryId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * ENHANCED: Check if user can view tickets in a specific category
     */
    public function viewCategory(User $user, int $categoryId): bool
    {
        try {
            if ($user->role === 'admin') {
                return true;
            }
            
            if (in_array($user->role, ['counselor', 'advisor'])) {
                return $this->hasSpecializationInCategory($user, $categoryId);
            }
            
            // Students can view all categories when creating tickets
            if ($user->role === 'student') {
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('TicketPolicy viewCategory() error', [
                'user_id' => $user->id,
                'category_id' => $categoryId,
                'error' => $e->getMessage()
            ]);
            
            return $user->role === 'admin' || $user->role === 'student';
        }
    }
}
