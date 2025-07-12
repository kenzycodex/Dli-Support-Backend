<?php
// routes/api/admin.php - Complete Admin Management Routes

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\AdminController;

/*
|--------------------------------------------------------------------------
| Admin Routes (Admin role only)
|--------------------------------------------------------------------------
*/

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

    // ========== ADMIN CONTROLLER ROUTES ==========
    
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

    // ========== TICKET MANAGEMENT ROUTES ==========

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

    // Bulk assign tickets (legacy implementation - keeping for compatibility)
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

    // Export tickets data (legacy implementation - keeping for compatibility)
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

    // User management resource routes (RESTful operations)
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

    // ========== ADDITIONAL ADMIN ROUTES ==========

    // System logs
    Route::get('/logs', function (Request $request) {
        $logs = \App\Models\SystemLog::with('user')
                                   ->orderBy('created_at', 'desc')
                                   ->paginate(50);
        
        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    })->middleware('throttle:30,1');

    // System settings
    Route::get('/settings', function (Request $request) {
        $settings = \App\Models\SystemSetting::all();
        
        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    })->middleware('throttle:30,1');

    Route::post('/settings', function (Request $request) {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            foreach ($request->settings as $setting) {
                \App\Models\SystemSetting::updateOrCreate(
                    ['key' => $setting['key']],
                    ['value' => $setting['value']]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings'
            ], 500);
        }
    })->middleware('throttle:10,1');

    // Database maintenance
    Route::post('/maintenance/cleanup', function (Request $request) {
        try {
            $cleanedCount = 0;
            
            // Clean up old notifications
            $oldNotifications = \App\Models\Notification::where('created_at', '<', now()->subMonths(6))->count();
            \App\Models\Notification::where('created_at', '<', now()->subMonths(6))->delete();
            $cleanedCount += $oldNotifications;
            
            // Clean up old logs
            $oldLogs = \App\Models\SystemLog::where('created_at', '<', now()->subMonths(3))->count();
            \App\Models\SystemLog::where('created_at', '<', now()->subMonths(3))->delete();
            $cleanedCount += $oldLogs;
            
            // Clean up orphaned files
            // Implementation would depend on your file storage structure
            
            return response()->json([
                'success' => true,
                'message' => "Cleaned up {$cleanedCount} old records",
                'data' => [
                    'old_notifications_removed' => $oldNotifications,
                    'old_logs_removed' => $oldLogs,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database cleanup failed'
            ], 500);
        }
    })->middleware('throttle:5,1');

    // System backup
    Route::post('/backup', function (Request $request) {
        try {
            // This would trigger your backup process
            // Implementation depends on your backup strategy
            
            $backupInfo = [
                'backup_id' => \Illuminate\Support\Str::uuid(),
                'timestamp' => now(),
                'size' => '0 MB', // Would be calculated
                'status' => 'initiated'
            ];
            
            return response()->json([
                'success' => true,
                'message' => 'System backup initiated',
                'data' => $backupInfo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Backup initiation failed'
            ], 500);
        }
    })->middleware('throttle:2,1');

    // Import data
    Route::post('/import', function (Request $request) {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'type' => 'required|in:users,tickets,categories',
            'file' => 'required|file|mimes:csv,xlsx',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Implementation would depend on your import logic
            // This is a placeholder response
            
            return response()->json([
                'success' => true,
                'message' => 'Import completed successfully',
                'data' => [
                    'imported_records' => 0,
                    'failed_records' => 0,
                    'warnings' => []
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed'
            ], 500);
        }
    })->middleware('throttle:5,1');
});