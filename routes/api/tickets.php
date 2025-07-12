<?php
// routes/api/tickets.php - Ticket Management Routes

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketController;

/*
|--------------------------------------------------------------------------
| Ticket Routes (Role-based access)
|--------------------------------------------------------------------------
*/

Route::prefix('tickets')->group(function () {
    
    // ========== ALL AUTHENTICATED USERS ==========
    
    // Get tickets (filtered by role automatically)
    Route::get('/', [TicketController::class, 'index'])
         ->middleware('throttle:200,1');
    
    // Get single ticket (with permission check)
    Route::get('/{ticket}', [TicketController::class, 'show'])
         ->middleware('throttle:300,1');
    
    // Get ticket options/metadata
    Route::get('/options', [TicketController::class, 'getOptions'])
         ->middleware('throttle:60,1');
    
    // Download attachments
    Route::get('/attachments/{attachment}/download', [TicketController::class, 'downloadAttachment'])
         ->middleware('throttle:120,1');
    
    // Get analytics (role-filtered)
    Route::get('/analytics', [TicketController::class, 'getAnalytics'])
         ->middleware('throttle:30,1');

    // Get analytics for specific ticket
    Route::get('/{ticket}/analytics', [TicketController::class, 'getTicketAnalytics'])
         ->middleware('throttle:60,1');

    // ========== STUDENTS ONLY ==========
    
    // Create new ticket (students + admin on behalf)
    Route::post('/', [TicketController::class, 'store'])
         ->middleware(['role:student,admin', 'throttle:30,1']);

    // ========== STUDENTS + STAFF ==========
    
    // Add response to ticket
    Route::post('/{ticket}/responses', [TicketController::class, 'addResponse'])
         ->middleware('throttle:100,1');

    // ========== STAFF ONLY (Counselors, Advisors, Admins) ==========
    
    Route::middleware('role:counselor,advisor,admin')->group(function () {
        
        // Update ticket status, priority, etc.
        Route::patch('/{ticket}', [TicketController::class, 'update'])
             ->middleware('throttle:200,1');
        
        // Get available staff for assignment
        Route::get('/{ticket}/available-staff', [TicketController::class, 'getAvailableStaff'])
             ->middleware('throttle:60,1');
        
        // Manage ticket tags
        Route::post('/{ticket}/tags', [TicketController::class, 'manageTags'])
             ->middleware('throttle:100,1');
    });

    // ========== ADMIN ONLY ==========
    
    Route::middleware('role:admin')->group(function () {
        
        // Assign ticket to staff
        Route::post('/{ticket}/assign', [TicketController::class, 'assign'])
             ->middleware('throttle:100,1');
        
        // FIXED: Single delete route - supports both DELETE method and POST with reason
        // Standard RESTful DELETE route that accepts JSON body with reason
        Route::delete('/{ticket}', [TicketController::class, 'destroy'])
             ->middleware('throttle:20,1');
        
        // Alternative POST route for clients that prefer form-style deletion
        Route::post('/{ticket}/delete', [TicketController::class, 'destroy'])
             ->middleware('throttle:20,1');
        
        // Reassign between staff members
        Route::post('/{ticket}/reassign', [TicketController::class, 'assign'])
             ->middleware('throttle:100,1');
    });
});

// ==========================================
// ROLE-SPECIFIC TICKET ROUTES
// ==========================================

// ========== STUDENT ROUTES ==========
Route::middleware('role:student')->prefix('student')->group(function () {
    
    // Student dashboard data
    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();
        $ticketStats = \App\Models\Ticket::getStatsForUser($user);
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'ticket_stats' => $ticketStats,
                'permissions' => [
                    'can_create_tickets' => true,
                    'can_view_own_tickets' => true,
                    'can_respond_to_tickets' => true,
                    'can_close_own_tickets' => false, // Only staff can close
                ]
            ]
        ]);
    })->middleware('throttle:60,1');

    // Get student's tickets only
    Route::get('/tickets', [TicketController::class, 'index'])
         ->middleware('throttle:100,1');
    
    // Get student's ticket statistics
    Route::get('/stats', function (Request $request) {
        $user = $request->user();
        $stats = \App\Models\Ticket::getStatsForUser($user);
        
        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    })->middleware('throttle:30,1');
});

// ========== COUNSELOR ROUTES ==========
Route::middleware('role:counselor')->prefix('counselor')->group(function () {
    
    // Counselor dashboard data
    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();
        $ticketStats = \App\Models\Ticket::getStatsForUser($user);
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'ticket_stats' => $ticketStats,
                'assigned_tickets' => $user->assignedTickets()
                                          ->whereIn('status', ['Open', 'In Progress'])
                                          ->count(),
                'crisis_tickets' => $user->assignedTickets()
                                        ->where('crisis_flag', true)
                                        ->count(),
                'permissions' => [
                    'can_view_assigned_tickets' => true,
                    'can_modify_tickets' => true,
                    'can_add_internal_notes' => true,
                    'can_reassign_tickets' => false, // Only admin
                    'specialization' => ['mental-health', 'crisis']
                ]
            ]
        ]);
    })->middleware('throttle:60,1');

    // Get counselor's assigned tickets (mental health & crisis)
    Route::get('/assigned-tickets', function (Request $request) {
        $user = $request->user();
        $tickets = \App\Models\Ticket::forCounselor($user->id)
                                    ->with(['user', 'responses'])
                                    ->orderBy('updated_at', 'desc')
                                    ->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $tickets
        ]);
    })->middleware('throttle:100,1');
    
    // Get crisis tickets requiring immediate attention
    Route::get('/crisis-tickets', function (Request $request) {
        $user = $request->user();
        $crisisTickets = \App\Models\Ticket::forCounselor($user->id)
                                          ->crisis()
                                          ->with(['user'])
                                          ->orderBy('created_at', 'desc')
                                          ->get();
        
        return response()->json([
            'success' => true,
            'data' => $crisisTickets
        ]);
    })->middleware('throttle:60,1');
});

// ========== ADVISOR ROUTES ==========
Route::middleware('role:advisor')->prefix('advisor')->group(function () {
    
    // Advisor dashboard data
    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();
        $ticketStats = \App\Models\Ticket::getStatsForUser($user);
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'ticket_stats' => $ticketStats,
                'assigned_tickets' => $user->assignedTickets()
                                          ->whereIn('status', ['Open', 'In Progress'])
                                          ->count(),
                'permissions' => [
                    'can_view_assigned_tickets' => true,
                    'can_modify_tickets' => true,
                    'can_add_internal_notes' => true,
                    'can_reassign_tickets' => false, // Only admin
                    'specialization' => ['academic', 'general', 'technical']
                ]
            ]
        ]);
    })->middleware('throttle:60,1');

    // Get advisor's assigned tickets (academic & general)
    Route::get('/assigned-tickets', function (Request $request) {
        $user = $request->user();
        $tickets = \App\Models\Ticket::forAdvisor($user->id)
                                    ->with(['user', 'responses'])
                                    ->orderBy('updated_at', 'desc')
                                    ->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $tickets
        ]);
    })->middleware('throttle:100,1');
});

// ========== STAFF ROUTES (Counselors + Advisors + Admins) ==========
Route::middleware('role:counselor,advisor,admin')->prefix('staff')->group(function () {
    
    // Staff dashboard data
    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();
        $ticketStats = \App\Models\Ticket::getStatsForUser($user);
        
        return response()->json([
            'success' => true,
            'message' => 'Staff dashboard access granted',
            'data' => [
                'user' => $user,
                'ticket_stats' => $ticketStats,
                'permissions' => [
                    'can_view_assigned_tickets' => true,
                    'can_modify_tickets' => true,
                    'can_add_internal_notes' => true,
                    'can_manage_tags' => true,
                    'can_reassign' => $user->isAdmin(),
                ]
            ]
        ]);
    })->middleware('throttle:60,1');

    // Get assigned tickets for staff
    Route::get('/assigned-tickets', function (Request $request) {
        $user = $request->user();
        $tickets = $user->assignedTickets()
                       ->with(['user', 'responses'])
                       ->orderBy('updated_at', 'desc')
                       ->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $tickets
        ]);
    })->middleware('throttle:100,1');
    
    // Get staff ticket statistics
    Route::get('/stats', function (Request $request) {
        $user = $request->user();
        $stats = \App\Models\Ticket::getStatsForUser($user);
        
        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    })->middleware('throttle:30,1');

    // Get workload distribution
    Route::get('/workload', function (Request $request) {
        $user = $request->user();
        $assignedTickets = $user->assignedTickets();
        
        $workload = [
            'total_assigned' => $assignedTickets->count(),
            'open_tickets' => (clone $assignedTickets)->where('status', 'Open')->count(),
            'in_progress' => (clone $assignedTickets)->where('status', 'In Progress')->count(),
            'high_priority' => (clone $assignedTickets)->where('priority', 'High')->count(),
            'crisis_cases' => (clone $assignedTickets)->where('crisis_flag', true)->count(),
            'this_week' => (clone $assignedTickets)->where('created_at', '>=', now()->startOfWeek())->count(),
            'avg_response_time' => '2.3 hours', // This would be calculated from actual data
        ];
        
        return response()->json([
            'success' => true,
            'data' => $workload
        ]);
    })->middleware('throttle:30,1');
});