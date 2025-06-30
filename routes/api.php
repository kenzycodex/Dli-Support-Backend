<?php
// routes/api.php (Enhanced with role-based ticketing system)

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\UserManagementController;
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
        
        // Download attachments
        Route::get('/attachments/{attachment}/download', [TicketController::class, 'downloadAttachment'])
             ->middleware('throttle:120,1');
        
        // Get analytics (role-filtered)
        Route::get('/analytics', [TicketController::class, 'getAnalytics'])
             ->middleware('throttle:30,1');

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
            
            // Delete ticket (with reason)
            Route::delete('/{ticket}', [TicketController::class, 'destroy'])
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

    // ========== ADMIN ROUTES ==========
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

        // Get system-wide analytics
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

        // Bulk assign tickets
        Route::post('/bulk-assign', function (Request $request) {
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

        // Export tickets data
        Route::get('/export-tickets', function (Request $request) {
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
        ],
        'roles' => [
            'student' => 'Can create and view own tickets',
            'counselor' => 'Can handle mental health and crisis tickets',
            'advisor' => 'Can handle academic and general tickets',
            'admin' => 'Full system access and management',
        ],
        'performance' => [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => round((microtime(true) - LARAVEL_START) * 1000, 2) . 'ms'
        ]
    ]);
});

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
        'rate_limits' => [
            'notifications' => '60-120 per minute',
            'ticket_operations' => '100-200 per minute',
            'user_management' => '20-50 per minute',
            'bulk_operations' => '10-20 per minute',
        ],
        'ticket_categories' => [
            'general' => 'General inquiries (Advisor)',
            'academic' => 'Academic support (Advisor)',
            'mental-health' => 'Mental health support (Counselor)',
            'crisis' => 'Crisis support (Counselor)',
            'technical' => 'Technical issues (Admin)',
            'other' => 'Other issues (Advisor/Counselor)',
        ],
        'auto_assignment_rules' => [
            'crisis_tickets' => 'Automatically assigned to available counselors',
            'mental_health' => 'Assigned to counselors',
            'academic_general' => 'Assigned to advisors',
            'load_balancing' => 'Based on current workload',
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
            'GET /api/user/permissions' => 'Get user permissions',
        ],
        'role_specific_endpoints' => [
            'students' => '/api/student/*',
            'counselors' => '/api/counselor/*',
            'advisors' => '/api/advisor/*',
            'admins' => '/api/admin/*',
            'all_staff' => '/api/staff/*',
        ]
    ], 404);
});