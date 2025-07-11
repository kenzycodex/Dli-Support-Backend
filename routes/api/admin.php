<?php
// routes/api/admin.php - Admin-Specific Routes

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| Admin-Specific API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('role:admin')->prefix('admin')->group(function () {
    
    // ========== ADMIN DASHBOARD ==========
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
                'help_stats' => [
                    'total_faqs' => \App\Models\FAQ::count(),
                    'published_faqs' => \App\Models\FAQ::published()->count(),
                    'pending_suggestions' => \App\Models\FAQ::where('is_published', false)->whereNotNull('created_by')->count(),
                    'total_categories' => \App\Models\HelpCategory::count(),
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

    // ========== TICKET MANAGEMENT ==========
    Route::get('/export-tickets', [AdminController::class, 'exportTickets'])
         ->middleware('throttle:10,1');
    
    Route::post('/bulk-assign', [AdminController::class, 'bulkAssign'])
         ->middleware('throttle:20,1');
    
    Route::get('/system-stats', [AdminController::class, 'getSystemStats'])
         ->middleware('throttle:30,1');
    
    Route::get('/available-staff', [AdminController::class, 'getAvailableStaff'])
         ->middleware('throttle:60,1');
    
    Route::get('/ticket-analytics', [AdminController::class, 'getTicketAnalytics'])
         ->middleware('throttle:30,1');

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
        $timeframe = $request->get('timeframe', '30');
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

    // Legacy bulk assign for compatibility
    Route::post('/bulk-assign-legacy', function (Request $request) {
        $validator = Validator::make($request->all(), [
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

    // Legacy export tickets for compatibility
    Route::get('/export-tickets-legacy', function (Request $request) {
        $format = $request->get('format', 'csv');
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

    // ========== USER MANAGEMENT ==========
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

    // User management resource routes
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

    // ========== SYSTEM MANAGEMENT ==========
    Route::get('/system-health', function (Request $request) {
        try {
            $health = [
                'status' => 'healthy',
                'database' => \Illuminate\Support\Facades\DB::connection()->getPdo() ? true : false,
                'cache' => \Illuminate\Support\Facades\Cache::store()->getStore() ? true : false,
                'storage' => \Illuminate\Support\Facades\Storage::disk('public')->exists('') ? true : false,
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'last_check' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $health
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'System health check failed',
                'data' => [
                    'status' => 'critical',
                    'error' => $e->getMessage()
                ]
            ], 500);
        }
    })->middleware('throttle:30,1');

    // Clear application cache
    Route::post('/clear-cache', function (Request $request) {
        try {
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            \Illuminate\Support\Facades\Artisan::call('route:clear');
            \Illuminate\Support\Facades\Artisan::call('view:clear');

            return response()->json([
                'success' => true,
                'message' => 'Application cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage()
            ], 500);
        }
    })->middleware('throttle:5,1');
});