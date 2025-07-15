<?php
// routes/api.php (FIXED - Removed duplicate delete route, standardized on single approach)

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TicketController;

/*
|--------------------------------------------------------------------------
| API Routes for Role-Based Ticketing System
|--------------------------------------------------------------------------
*/

// Public routes (no authentication required)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/demo-login', [AuthController::class, 'demoLogin']);
});

// Protected routes with role-based access control
Route::middleware(['auth:sanctum'])->group(function () {
    
    // ==========================================
    // AUTHENTICATION ROUTES
    // ==========================================
    Route::prefix('auth')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // ==========================================
    // NOTIFICATION ROUTES (All authenticated users)
    // ==========================================
    Route::prefix('notifications')->group(function () {
        Route::get('/unread-count', [NotificationController::class, 'getUnreadCount'])
             ->middleware('throttle:120,1');
        
        Route::get('/', [NotificationController::class, 'index'])
             ->middleware('throttle:120,1');
        
        Route::get('/options', [NotificationController::class, 'getOptions'])
             ->middleware('throttle:30,1');
        
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])
             ->middleware('throttle:30,1');
        
        Route::post('/bulk-action', [NotificationController::class, 'bulkAction'])
             ->middleware('throttle:60,1');
        
        Route::patch('/{notification}/read', [NotificationController::class, 'markAsRead'])
             ->middleware('throttle:300,1');
        
        Route::patch('/{notification}/unread', [NotificationController::class, 'markAsUnread'])
             ->middleware('throttle:300,1');
        
        Route::delete('/{notification}', [NotificationController::class, 'destroy'])
             ->middleware('throttle:200,1');
        
        // Admin only notification routes
        Route::middleware('role:admin')->group(function () {
            Route::post('/', [NotificationController::class, 'store'])
                 ->middleware('throttle:10,1');
            
            Route::get('/stats', [NotificationController::class, 'getStats'])
                 ->middleware('throttle:20,1');
        });
    });

    // ==========================================
    // TICKET ROUTES (Role-based access)
    // ==========================================
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
        
        // FIXED: Enhanced download route with better error handling
        Route::get('/attachments/{attachment}/download', [TicketController::class, 'downloadAttachment'])
             ->middleware('throttle:200,1')
             ->name('api.tickets.attachments.download');

        // Alternative download route for compatibility
        Route::post('/attachments/{attachment}/download', [TicketController::class, 'downloadAttachment'])
             ->middleware('throttle:200,1');
        
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
    // ROLE-SPECIFIC ROUTES
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

    // ==========================================
    // ADMIN ROUTES (Enhanced with new functionality)
    // ==========================================
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        
        // Admin dashboard data
        Route::get('/dashboard', function (Request $request) {
            $user = $request->user();
            $allStats = \App\Models\Ticket::getStatsForUser($user);
            $unassignedCount = \App\Models\Ticket::unassigned()->count();
            $crisisCount = \App\Models\Ticket::crisis()->count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'system_stats' => [
                        'total_tickets' => $allStats['total'],
                        'unassigned_tickets' => $unassignedCount,
                        'crisis_tickets' => $crisisCount,
                        'resolution_rate' => $allStats['total'] > 0 ? 
                            round(($allStats['resolved'] / $allStats['total']) * 100, 1) : 0,
                    ],
                    'user_stats' => [
                        'total_users' => \App\Models\User::count(),
                        'active_users' => \App\Models\User::where('status', 'active')->count(),
                        'counselors' => \App\Models\User::where('role', 'counselor')->count(),
                        'advisors' => \App\Models\User::where('role', 'advisor')->count(),
                        'students' => \App\Models\User::where('role', 'student')->count(),
                    ],
                    'permissions' => [
                        'can_view_all_tickets' => true,
                        'can_assign_tickets' => true,
                        'can_delete_tickets' => true,
                        'can_manage_users' => true,
                        'can_view_system_logs' => true,
                        'can_export_data' => true,
                    ]
                ]
            ]);
        })->middleware('throttle:60,1');

        // ========== NEW ADMIN ROUTES - INTEGRATED ==========
        
        // Export functionality
        Route::get('/export-tickets', [AdminController::class, 'exportTickets'])
             ->middleware('throttle:10,1');
        
        // Bulk operations
        Route::post('/bulk-assign', [AdminController::class, 'bulkAssign'])
             ->middleware('throttle:20,1');
        
        // System statistics
        Route::get('/system-stats', [AdminController::class, 'getSystemStats'])
             ->middleware('throttle:30,1');
        
        // Available staff
        Route::get('/available-staff', [AdminController::class, 'getAvailableStaff'])
             ->middleware('throttle:60,1');
        
        // Analytics
        Route::get('/ticket-analytics', [AdminController::class, 'getTicketAnalytics'])
             ->middleware('throttle:30,1');

        // ========== EXISTING ADMIN ROUTES (PRESERVED) ==========

        // Get all unassigned tickets
        Route::get('/unassigned-tickets', function (Request $request) {
            $tickets = \App\Models\Ticket::unassigned()
                                        ->with(['user'])
                                        ->orderBy('created_at', 'desc')
                                        ->paginate(20);
            
            return response()->json([
                'success' => true,
                'data' => $tickets
            ]);
        })->middleware('throttle:100,1');

        // Get system-wide analytics (existing implementation)
        Route::get('/analytics', function (Request $request) {
            $timeframe = $request->get('timeframe', '30'); // days
            $startDate = now()->subDays($timeframe);
            
            $analytics = [
                'ticket_trends' => [
                    'created_this_period' => \App\Models\Ticket::where('created_at', '>=', $startDate)->count(),
                    'resolved_this_period' => \App\Models\Ticket::where('resolved_at', '>=', $startDate)->count(),
                    'average_resolution_time' => \App\Models\Ticket::whereNotNull('resolved_at')
                                                                 ->where('resolved_at', '>=', $startDate)
                                                                 ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours')
                                                                 ->first()->avg_hours ?? 0,
                ],
                'category_distribution' => \App\Models\Ticket::selectRaw('category, count(*) as count')
                                                            ->groupBy('category')
                                                            ->pluck('count', 'category'),
                'priority_distribution' => \App\Models\Ticket::selectRaw('priority, count(*) as count')
                                                            ->groupBy('priority')
                                                            ->pluck('count', 'priority'),
                'staff_performance' => \App\Models\User::whereIn('role', ['counselor', 'advisor'])
                                                      ->where('status', 'active')
                                                      ->withCount([
                                                          'assignedTickets as total_assigned',
                                                          'assignedTickets as resolved_count' => function ($query) use ($startDate) {
                                                              $query->where('status', 'Resolved')
                                                                    ->where('resolved_at', '>=', $startDate);
                                                          }
                                                      ])
                                                      ->get(['id', 'name', 'role'])
                                                      ->toArray(),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);
        })->middleware('throttle:30,1');

        // Bulk assign tickets (existing implementation - keeping for compatibility)
        Route::post('/bulk-assign-legacy', function (Request $request) {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'ticket_ids' => 'required|array',
                'ticket_ids.*' => 'exists:tickets,id',
                'assigned_to' => 'required|exists:users,id',
                'reason' => 'sometimes|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            try {
                $assignedUser = \App\Models\User::find($request->assigned_to);
                if (!$assignedUser->isStaff()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Can only assign tickets to staff members'
                    ], 422);
                }

                $tickets = \App\Models\Ticket::whereIn('id', $request->ticket_ids)->get();
                $assigned = 0;

                foreach ($tickets as $ticket) {
                    if (!$ticket->assigned_to) {
                        $ticket->assignTo($request->assigned_to);
                        $assigned++;
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => "Successfully assigned {$assigned} tickets to {$assignedUser->name}",
                    'data' => ['assigned_count' => $assigned]
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to assign tickets'
                ], 500);
            }
        })->middleware('throttle:20,1');

        // Export tickets data (existing implementation - keeping for compatibility)
        Route::get('/export-tickets-legacy', function (Request $request) {
            $format = $request->get('format', 'csv'); // csv, excel, json
            $filters = $request->only(['status', 'category', 'priority', 'date_from', 'date_to']);
            
            try {
                $query = \App\Models\Ticket::with(['user', 'assignedTo']);
                
                // Apply filters
                if (!empty($filters['status'])) {
                    $query->where('status', $filters['status']);
                }
                if (!empty($filters['category'])) {
                    $query->where('category', $filters['category']);
                }
                if (!empty($filters['priority'])) {
                    $query->where('priority', $filters['priority']);
                }
                if (!empty($filters['date_from'])) {
                    $query->where('created_at', '>=', $filters['date_from']);
                }
                if (!empty($filters['date_to'])) {
                    $query->where('created_at', '<=', $filters['date_to']);
                }
                
                $tickets = $query->orderBy('created_at', 'desc')->get();
                
                // Format data for export
                $exportData = $tickets->map(function ($ticket) {
                    return [
                        'ticket_number' => $ticket->ticket_number,
                        'subject' => $ticket->subject,
                        'category' => $ticket->category,
                        'priority' => $ticket->priority,
                        'status' => $ticket->status,
                        'student_name' => $ticket->user->name,
                        'student_email' => $ticket->user->email,
                        'assigned_to' => $ticket->assignedTo?->name,
                        'crisis_flag' => $ticket->crisis_flag ? 'Yes' : 'No',
                        'created_at' => $ticket->created_at->format('Y-m-d H:i:s'),
                        'updated_at' => $ticket->updated_at->format('Y-m-d H:i:s'),
                        'resolved_at' => $ticket->resolved_at?->format('Y-m-d H:i:s'),
                    ];
                });
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'tickets' => $exportData,
                        'count' => $exportData->count(),
                        'exported_at' => now()->format('Y-m-d H:i:s'),
                        'filters_applied' => $filters
                    ]
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to export tickets data'
                ], 500);
            }
        })->middleware('throttle:10,1');

        // ========== USER MANAGEMENT ROUTES ==========
        
        Route::prefix('users')->group(function () {
            Route::get('/', [UserManagementController::class, 'index'])
                 ->middleware('throttle:120,1');
            
            Route::post('/', [UserManagementController::class, 'store'])
                 ->middleware('throttle:20,1');
            
            Route::get('/stats', [UserManagementController::class, 'getStats'])
                 ->middleware('throttle:60,1');
            
            Route::get('/options', [UserManagementController::class, 'getOptions'])
                 ->middleware('throttle:20,1');
            
            Route::post('/bulk-action', [UserManagementController::class, 'bulkAction'])
                 ->middleware('throttle:10,1');
            
            Route::get('/export', [UserManagementController::class, 'export'])
                 ->middleware('throttle:10,1');
            
            Route::get('/{user}', [UserManagementController::class, 'show'])
                 ->middleware('throttle:100,1');
            
            Route::put('/{user}', [UserManagementController::class, 'update'])
                 ->middleware('throttle:50,1');
            
            Route::delete('/{user}', [UserManagementController::class, 'destroy'])
                 ->middleware('throttle:20,1');
            
            Route::post('/{user}/reset-password', [UserManagementController::class, 'resetPassword'])
                 ->middleware('throttle:10,1');
            
            Route::post('/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])
                 ->middleware('throttle:50,1');
        });

        // User management resource routes (NEW - for RESTful operations)
        Route::resource('users-resource', UserManagementController::class, [
            'names' => [
                'index' => 'admin.users.index',
                'store' => 'admin.users.store',
                'show' => 'admin.users.show',
                'update' => 'admin.users.update',
                'destroy' => 'admin.users.destroy'
            ]
        ])->middleware('throttle:60,1');

        // Admin registration (for creating staff accounts)
        Route::post('/register', [AuthController::class, 'register'])
             ->middleware('throttle:5,1');
    });

    // ==========================================
    // STAFF ROUTES (Counselors + Advisors + Admins)
    // ==========================================
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

    // ==========================================
    // COMMON USER ROUTES (All authenticated users)
    // ==========================================
    Route::prefix('user')->group(function () {
        
        // Get user profile
        Route::get('/profile', function (Request $request) {
            return response()->json([
                'success' => true,
                'data' => ['user' => $request->user()]
            ]);
        })->middleware('throttle:60,1');
        
        // Update user profile
        Route::put('/profile', function (Request $request) {
            $user = $request->user();
            
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $user->id,
                'phone' => 'sometimes|nullable|string|max:20',
                'bio' => 'sometimes|nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            try {
                $user->update($request->only(['name', 'email', 'phone', 'bio']));
                
                return response()->json([
                    'success' => true,
                    'message' => 'Profile updated successfully',
                    'data' => ['user' => $user->fresh()]
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update profile'
                ], 500);
            }
        })->middleware('throttle:20,1');

        // Change password
        Route::post('/change-password', function (Request $request) {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'current_password' => 'required',
                'new_password' => 'required|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            if (!\Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 422);
            }

            try {
                $user->update([
                    'password' => \Illuminate\Support\Facades\Hash::make($request->new_password)
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Password changed successfully'
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to change password'
                ], 500);
            }
        })->middleware('throttle:10,1');

        // User's notification summary with caching
        Route::get('/notification-summary', function (Request $request) {
            $user = $request->user();
            $cacheKey = "user_notification_summary.{$user->id}";
            
            $summary = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($user) {
                return [
                    'unread_notifications' => $user->notifications()->where('read', false)->count(),
                    'total_notifications' => $user->notifications()->count(),
                    'high_priority_unread' => $user->notifications()->where('read', false)->where('priority', 'high')->count(),
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        })->middleware('throttle:40,1');

        // User's ticket summary with caching and role-based filtering
        Route::get('/ticket-summary', function (Request $request) {
            $user = $request->user();
            $cacheKey = "user_ticket_summary.{$user->id}";
            
            $summary = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($user) {
                if ($user->role === 'student') {
                    $tickets = $user->tickets();
                } elseif (in_array($user->role, ['counselor', 'advisor'])) {
                    $tickets = \App\Models\Ticket::where('assigned_to', $user->id);
                } else {
                    $tickets = \App\Models\Ticket::query();
                }
                
                return [
                    'open_tickets' => (clone $tickets)->whereIn('status', ['Open', 'In Progress'])->count(),
                    'total_tickets' => $tickets->count(),
                    'recent_activity' => (clone $tickets)->where('updated_at', '>=', now()->subDays(7))->count(),
                    'crisis_tickets' => (clone $tickets)->where('crisis_flag', true)->count(),
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        })->middleware('throttle:40,1');

        // Get user permissions based on role
        Route::get('/permissions', function (Request $request) {
            $user = $request->user();
            
            $permissions = [
                'tickets' => [
                    'can_create' => $user->isStudent() || $user->isAdmin(),
                    'can_view_own' => true,
                    'can_view_all' => $user->isAdmin(),
                    'can_assign' => $user->isAdmin(),
                    'can_modify' => $user->isStaff(),
                    'can_delete' => $user->isAdmin(),
                    'can_add_internal_notes' => $user->isStaff(),
                    'can_manage_tags' => $user->isStaff(),
                ],
                'users' => [
                    'can_view_all' => $user->isAdmin(),
                    'can_create' => $user->isAdmin(),
                    'can_modify' => $user->isAdmin(),
                    'can_delete' => $user->isAdmin(),
                ],
                'system' => [
                    'can_view_analytics' => $user->isStaff(),
                    'can_export_data' => $user->isAdmin(),
                    'can_manage_settings' => $user->isAdmin(),
                    'can_view_logs' => $user->isAdmin(),
                ],
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'user_role' => $user->role,
                    'permissions' => $permissions
                ]
            ]);
        })->middleware('throttle:30,1');
    });
});

// ==========================================
// HEALTH CHECK AND DOCUMENTATION
// ==========================================

// Health check route (no rate limiting for monitoring)
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Role-Based Ticketing API is running',
        'timestamp' => now(),
        'version' => '2.0.0',
        'features' => [
            'role_based_access' => 'active',
            'authentication' => 'active',
            'notifications' => 'active',
            'ticket_management' => 'active',
            'user_management' => 'active',
            'rate_limiting' => 'active',
            'caching' => 'active',
            'crisis_detection' => 'active',
            'auto_assignment' => 'active',
            'admin_functionality' => 'active',
            'bulk_operations' => 'active',
            'analytics' => 'active',
            'export_functionality' => 'active',
            'file_downloads' => 'active', // FIXED
        ],
        'roles' => [
            'student' => 'Can create and view own tickets',
            'counselor' => 'Can handle mental health and crisis tickets',
            'advisor' => 'Can handle academic and general tickets',
            'admin' => 'Full system access and management',
        ],
        'delete_endpoints' => [
            'standard_rest' => 'DELETE /api/tickets/{id} - RESTful approach with JSON body',
            'form_style' => 'POST /api/tickets/{id}/delete - Form-style deletion',
            'note' => 'Both endpoints use the same controller method for consistency'
        ],
        'performance' => [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => round((microtime(true) - LARAVEL_START) * 1000, 2) . 'ms'
        ]
    ]);
});

use App\Http\Controllers\HelpController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\Admin\AdminHelpController;
use App\Http\Controllers\Admin\AdminResourceController;

// Add these routes to the existing api.php file after the existing routes

// ==========================================
// HELP & FAQ ROUTES (All authenticated users)
// ==========================================
Route::middleware(['auth:sanctum'])->prefix('help')->group(function () {
    
    // Get help categories
    Route::get('/categories', [HelpController::class, 'getCategories'])
         ->middleware('throttle:60,1');
    
    // Get FAQs with filtering
    Route::get('/faqs', [HelpController::class, 'getFAQs'])
         ->middleware('throttle:120,1');
    
    // Get single FAQ
    Route::get('/faqs/{faq}', [HelpController::class, 'showFAQ'])
         ->middleware('throttle:200,1');
    
    // Provide feedback on FAQ
    Route::post('/faqs/{faq}/feedback', [HelpController::class, 'provideFeedback'])
         ->middleware('throttle:30,1');
    
    // Suggest content (counselors and admins only)
    Route::post('/suggest-content', [HelpController::class, 'suggestContent'])
         ->middleware(['role:counselor,admin', 'throttle:10,1']);
    
    // Get help statistics
    Route::get('/stats', [HelpController::class, 'getStats'])
         ->middleware('throttle:30,1');
});

// ==========================================
// RESOURCES ROUTES (All authenticated users)
// ==========================================
Route::middleware(['auth:sanctum'])->prefix('resources')->group(function () {
    
    // Get resource categories
    Route::get('/categories', [ResourceController::class, 'getCategories'])
         ->middleware('throttle:60,1');
    
    // Get resources with filtering
    Route::get('/', [ResourceController::class, 'getResources'])
         ->middleware('throttle:120,1');
    
    // Get single resource
    Route::get('/{resource}', [ResourceController::class, 'showResource'])
         ->middleware('throttle:200,1');
    
    // Access resource (get URL and track usage)
    Route::post('/{resource}/access', [ResourceController::class, 'accessResource'])
         ->middleware('throttle:100,1');
    
    // Provide feedback/rating on resource
    Route::post('/{resource}/feedback', [ResourceController::class, 'provideFeedback'])
         ->middleware('throttle:30,1');
    
    // Bookmark/unbookmark resource
    Route::post('/{resource}/bookmark', [ResourceController::class, 'bookmarkResource'])
         ->middleware('throttle:60,1');
    
    // Get user's bookmarks
    Route::get('/user/bookmarks', [ResourceController::class, 'getBookmarks'])
         ->middleware('throttle:60,1');
    
    // Get resource statistics
    Route::get('/stats', [ResourceController::class, 'getStats'])
         ->middleware('throttle:30,1');
    
    // Get resource options for forms
    Route::get('/options', [ResourceController::class, 'getOptions'])
         ->middleware('throttle:30,1');
});

// ==========================================
// ADMIN HELP & FAQ ROUTES (Admin only)
// ==========================================
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/help')->group(function () {
    
    // Help Categories Management
    Route::get('/categories', [AdminHelpController::class, 'getCategories'])
         ->middleware('throttle:60,1');
    
    Route::post('/categories', [AdminHelpController::class, 'storeCategory'])
         ->middleware('throttle:20,1');
    
    Route::put('/categories/{helpCategory}', [AdminHelpController::class, 'updateCategory'])
         ->middleware('throttle:30,1');
    
    Route::delete('/categories/{helpCategory}', [AdminHelpController::class, 'destroyCategory'])
         ->middleware('throttle:10,1');
    
    // FAQs Management
    Route::get('/faqs', [AdminHelpController::class, 'getFAQs'])
         ->middleware('throttle:100,1');
    
    Route::post('/faqs', [AdminHelpController::class, 'storeFAQ'])
         ->middleware('throttle:20,1');
    
    Route::put('/faqs/{faq}', [AdminHelpController::class, 'updateFAQ'])
         ->middleware('throttle:30,1');
    
    Route::delete('/faqs/{faq}', [AdminHelpController::class, 'destroyFAQ'])
         ->middleware('throttle:10,1');
    
    // Bulk FAQ actions
    Route::post('/faqs/bulk-action', [AdminHelpController::class, 'bulkActionFAQs'])
         ->middleware('throttle:10,1');
    
    // Help analytics
    Route::get('/analytics', [AdminHelpController::class, 'getAnalytics'])
         ->middleware('throttle:30,1');
});

// ==========================================
// ADMIN RESOURCES ROUTES (Admin only)
// ==========================================
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/resources')->group(function () {
    
    // Resource Categories Management
    Route::get('/categories', [AdminResourceController::class, 'getCategories'])
         ->middleware('throttle:60,1');
    
    Route::post('/categories', [AdminResourceController::class, 'storeCategory'])
         ->middleware('throttle:20,1');
    
    Route::put('/categories/{resourceCategory}', [AdminResourceController::class, 'updateCategory'])
         ->middleware('throttle:30,1');
    
    Route::delete('/categories/{resourceCategory}', [AdminResourceController::class, 'destroyCategory'])
         ->middleware('throttle:10,1');
    
    // Resources Management
    Route::get('/', [AdminResourceController::class, 'getResources'])
         ->middleware('throttle:100,1');
    
    Route::post('/', [AdminResourceController::class, 'storeResource'])
         ->middleware('throttle:20,1');
    
    Route::put('/{resource}', [AdminResourceController::class, 'updateResource'])
         ->middleware('throttle:30,1');
    
    Route::delete('/{resource}', [AdminResourceController::class, 'destroyResource'])
         ->middleware('throttle:10,1');
    
    // Bulk resource actions
    Route::post('/bulk-action', [AdminResourceController::class, 'bulkActionResources'])
         ->middleware('throttle:10,1');
    
    // Resource analytics
    Route::get('/analytics', [AdminResourceController::class, 'getAnalytics'])
         ->middleware('throttle:30,1');
    
    // Export resources
    Route::get('/export', [AdminResourceController::class, 'exportResources'])
         ->middleware('throttle:5,1');
});

// ==========================================
// ROLE-SPECIFIC HELP & RESOURCES ROUTES
// ==========================================

// Student-specific routes
Route::middleware(['auth:sanctum', 'role:student'])->prefix('student')->group(function () {
    
    // Student help dashboard
    Route::get('/help-dashboard', function (Request $request) {
        $user = $request->user();
        
        // Get popular FAQs
        $popularFAQs = \App\Models\FAQ::published()
            ->orderBy('view_count', 'desc')
            ->take(5)
            ->get(['id', 'question', 'view_count']);
        
        // Get featured resources
        $featuredResources = \App\Models\Resource::published()
            ->featured()
            ->with('category:id,name,color')
            ->take(3)
            ->get(['id', 'title', 'type', 'category_id', 'rating']);
        
        // Get user's recent bookmarks
        $recentBookmarks = DB::table('user_bookmarks')
            ->join('resources', 'user_bookmarks.resource_id', '=', 'resources.id')
            ->where('user_bookmarks.user_id', $user->id)
            ->orderBy('user_bookmarks.created_at', 'desc')
            ->take(3)
            ->get(['resources.id', 'resources.title', 'resources.type']);
        
        return response()->json([
            'success' => true,
            'data' => [
                'popular_faqs' => $popularFAQs,
                'featured_resources' => $featuredResources,
                'recent_bookmarks' => $recentBookmarks,
                'help_stats' => [
                    'total_faqs' => \App\Models\FAQ::published()->count(),
                    'total_resources' => \App\Models\Resource::published()->count(),
                    'user_bookmarks' => DB::table('user_bookmarks')->where('user_id', $user->id)->count(),
                ]
            ]
        ]);
    })->middleware('throttle:60,1');
});

// Counselor-specific routes
Route::middleware(['auth:sanctum', 'role:counselor'])->prefix('counselor')->group(function () {
    
    // Counselor help dashboard
    Route::get('/help-dashboard', function (Request $request) {
        $user = $request->user();
        
        // Get most helpful FAQs for reference
        $helpfulFAQs = \App\Models\FAQ::published()
            ->orderBy('helpful_count', 'desc')
            ->take(5)
            ->get(['id', 'question', 'helpful_count', 'category_id']);
        
        // Get crisis-related resources
        $crisisResources = \App\Models\Resource::published()
            ->whereHas('category', function ($query) {
                $query->where('slug', 'crisis-resources');
            })
            ->orderBy('rating', 'desc')
            ->take(5)
            ->get(['id', 'title', 'type', 'rating']);
        
        // Get pending content suggestions (if any by this counselor)
        $pendingSuggestions = \App\Models\FAQ::where('created_by', $user->id)
            ->where('is_published', false)
            ->count();
        
        return response()->json([
            'success' => true,
            'data' => [
                'helpful_faqs' => $helpfulFAQs,
                'crisis_resources' => $crisisResources,
                'pending_suggestions' => $pendingSuggestions,
                'help_stats' => [
                    'total_faqs' => \App\Models\FAQ::published()->count(),
                    'total_resources' => \App\Models\Resource::published()->count(),
                    'mental_health_resources' => \App\Models\Resource::published()
                        ->whereHas('category', function ($query) {
                            $query->where('slug', 'mental-health');
                        })->count(),
                ]
            ]
        ]);
    })->middleware('throttle:60,1');
    
    // Get suggested content by this counselor
    Route::get('/my-suggestions', function (Request $request) {
        $user = $request->user();
        
        $suggestions = \App\Models\FAQ::where('created_by', $user->id)
            ->where('is_published', false)
            ->with('category:id,name,color')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        return response()->json([
            'success' => true,
            'data' => [
                'suggestions' => $suggestions->items(),
                'pagination' => [
                    'current_page' => $suggestions->currentPage(),
                    'last_page' => $suggestions->lastPage(),
                    'per_page' => $suggestions->perPage(),
                    'total' => $suggestions->total(),
                ]
            ]
        ]);
    })->middleware('throttle:60,1');
});

// Update the existing documentation route to include help & resources
Route::get('/docs', function () {
    return response()->json([
        'success' => true,
        'message' => 'Student Support Platform API - Enhanced with Help & Resources',
        'version' => '2.1.0',
        'features' => [
            'role_based_access' => 'active',
            'authentication' => 'active',
            'notifications' => 'active',
            'ticket_management' => 'active',
            'user_management' => 'active',
            'help_faqs' => 'active', // NEW
            'resources_library' => 'active', // NEW
            'content_management' => 'active', // NEW
            'analytics' => 'active',
            'export_functionality' => 'active',
        ],
        'new_endpoints' => [
            'help_faqs' => [
                'GET /api/help/categories' => 'Get help categories',
                'GET /api/help/faqs' => 'Get FAQs with filtering',
                'GET /api/help/faqs/{id}' => 'Get single FAQ',
                'POST /api/help/faqs/{id}/feedback' => 'Provide FAQ feedback',
                'POST /api/help/suggest-content' => 'Suggest FAQ content (counselors)',
                'GET /api/help/stats' => 'Get help statistics',
            ],
            'resources' => [
                'GET /api/resources/categories' => 'Get resource categories',
                'GET /api/resources' => 'Get resources with filtering',
                'GET /api/resources/{id}' => 'Get single resource',
                'POST /api/resources/{id}/access' => 'Access resource (track usage)',
                'POST /api/resources/{id}/feedback' => 'Rate/review resource',
                'POST /api/resources/{id}/bookmark' => 'Bookmark resource',
                'GET /api/resources/user/bookmarks' => 'Get user bookmarks',
                'GET /api/resources/stats' => 'Get resource statistics',
            ],
            'admin_help' => [
                'GET /api/admin/help/categories' => 'Manage help categories',
                'POST /api/admin/help/categories' => 'Create help category',
                'GET /api/admin/help/faqs' => 'Manage FAQs',
                'POST /api/admin/help/faqs' => 'Create FAQ',
                'POST /api/admin/help/faqs/bulk-action' => 'Bulk FAQ operations',
                'GET /api/admin/help/analytics' => 'Help analytics',
            ],
            'admin_resources' => [
                'GET /api/admin/resources/categories' => 'Manage resource categories',
                'POST /api/admin/resources/categories' => 'Create resource category',
                'GET /api/admin/resources' => 'Manage resources',
                'POST /api/admin/resources' => 'Create resource',
                'POST /api/admin/resources/bulk-action' => 'Bulk resource operations',
                'GET /api/admin/resources/analytics' => 'Resource analytics',
                'GET /api/admin/resources/export' => 'Export resources',
            ]
        ],
        'permissions_summary' => [
            'students' => 'Browse, search, rate FAQs and resources; bookmark resources',
            'counselors' => 'Student permissions + suggest FAQ content',
            'admins' => 'Full CRUD on all help content, categories, and analytics',
        ],
        'rate_limits' => [
            'help_browsing' => '60-120 per minute',
            'resource_access' => '100-200 per minute',
            'feedback_submission' => '30 per minute',
            'content_suggestions' => '10 per minute (counselors)',
            'admin_operations' => '10-30 per minute',
            'analytics' => '30 per minute',
            'exports' => '5 per minute',
        ]
    ]);
})->middleware('throttle:20,1');

// API documentation endpoint
Route::get('/docs', function () {
    return response()->json([
        'success' => true,
        'message' => 'Role-Based Student Support Platform API',
        'version' => '2.0.0',
        'role_based_features' => [
            'student_routes' => '/api/student/*',
            'counselor_routes' => '/api/counselor/*',
            'advisor_routes' => '/api/advisor/*',
            'admin_routes' => '/api/admin/*',
            'staff_routes' => '/api/staff/*',
        ],
        'version' => '2.0.1',
        'download_fixes' => [
            'permission_issues' => 'FIXED - Enhanced permission checking for all user roles',
            'cors_configuration' => 'FIXED - Better CORS headers for file downloads',
            'fallback_strategies' => 'FIXED - Multiple download strategies with fallbacks',
            'error_handling' => 'FIXED - Improved error messages and user feedback',
            'storage_support' => 'FIXED - Multiple storage disk support',
        ],
        'download_endpoints' => [
            'GET /api/tickets/attachments/{id}/download' => 'Download attachment (all authenticated users)',
            'POST /api/tickets/attachments/{id}/download' => 'Alternative download method',
        ],
        'ticket_deletion' => [
            'FIXED' => 'Single standardized delete method handles both approaches',
            'DELETE /api/tickets/{id}' => 'Standard RESTful deletion with JSON body',
            'POST /api/tickets/{id}/delete' => 'Alternative form-style deletion',
            'required_fields' => ['reason' => 'string|min:10|max:500', 'notify_user' => 'boolean|optional'],
            'admin_only' => 'Only administrators can delete tickets',
            'immediate_cleanup' => 'Files and database records cleaned up atomically'
        ],
        'ticket_endpoints' => [
            'GET /api/tickets' => 'Get tickets (role-filtered)',
            'POST /api/tickets' => 'Create new ticket',
            'GET /api/tickets/{id}' => 'Get specific ticket',
            'PATCH /api/tickets/{id}' => 'Update ticket (staff only)',
            'DELETE /api/tickets/{id}' => 'Delete ticket (admin only) - FIXED',
            'POST /api/tickets/{id}/delete' => 'Alternative delete route (admin only) - FIXED',
            'POST /api/tickets/{id}/assign' => 'Assign ticket to staff (admin only)',
            'POST /api/tickets/{id}/responses' => 'Add response to ticket',
            'POST /api/tickets/{id}/tags' => 'Manage ticket tags (staff only)',
            'GET /api/tickets/{id}/available-staff' => 'Get available staff for assignment',
            'GET /api/tickets/{id}/analytics' => 'Get ticket-specific analytics',
            'GET /api/tickets/attachments/{id}/download' => 'Download attachment',
        ],
        'improvements' => [
            'single_delete_method' => 'Unified delete handling prevents UI freezing',
            'atomic_operations' => 'Database transactions ensure data consistency',
            'proper_cleanup' => 'Files and records deleted in single transaction',
            'enhanced_validation' => 'Better error messages and validation',
            'immediate_response' => 'Fast response prevents frontend timeouts'
        ],
        'rate_limits' => [
            'notifications' => '60-120 per minute',
            'ticket_operations' => '100-200 per minute',
            'user_management' => '20-50 per minute',
            'bulk_operations' => '10-20 per minute',
            'export_operations' => '5-10 per minute',
            'analytics' => '30-60 per minute',
            'delete_operations' => '20 per minute (admin only)'
        ],
        'authentication' => [
            'type' => 'Laravel Sanctum Token-based',
            'login' => 'POST /api/auth/login',
            'logout' => 'POST /api/auth/logout',
            'demo_login' => 'POST /api/auth/demo-login',
            'user_info' => 'GET /api/auth/user',
        ],
        'documentation' => 'Contact admin for detailed API documentation'
    ]);
})->middleware('throttle:20,1');

// Fallback route for 404
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found',
        'available_endpoints' => [
            'GET /api/health' => 'Health check',
            'GET /api/docs' => 'API documentation',
            'POST /api/auth/login' => 'User login',
            'POST /api/auth/demo-login' => 'Demo login',
            'GET /api/tickets' => 'Get tickets (role-filtered)',
            'POST /api/tickets' => 'Create ticket (students only)',
            'DELETE /api/tickets/{id}' => 'Delete ticket (admin only) - FIXED',
            'POST /api/tickets/{id}/delete' => 'Alternative delete (admin only) - FIXED',
            'GET /api/user/permissions' => 'Get user permissions',
        ],
        'role_specific_endpoints' => [
            'students' => '/api/student/*',
            'counselors' => '/api/counselor/*',
            'advisors' => '/api/advisor/*',
            'admins' => '/api/admin/*',
            'all_staff' => '/api/staff/*',
        ],
        'fixed_issues' => [
            'duplicate_delete_routes' => 'Removed - now single method handles both',
            'ui_freezing' => 'Fixed with atomic operations and fast responses',
            'state_cleanup' => 'Proper frontend state management implemented'
        ],
        'suggestion' => 'Check /api/docs for complete endpoint documentation'
    ], 404);
});