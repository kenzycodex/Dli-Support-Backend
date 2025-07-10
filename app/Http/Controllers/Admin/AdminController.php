<?php
// app/Http/Controllers/Admin/AdminController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AdminController extends Controller
{
    /**
     * Export tickets to CSV/Excel/JSON
     */
    public function exportTickets(Request $request): JsonResponse
    {
        Log::info('=== EXPORTING TICKETS ===');
        Log::info('User: ' . $request->user()->id . ' (' . $request->user()->role . ')');

        $user = $request->user();

        // Only admins can export
        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can export tickets.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'format' => 'sometimes|in:csv,excel,json',
            'ticket_ids' => 'sometimes|array',
            'ticket_ids.*' => 'exists:tickets,id',
            'status' => 'sometimes|string',
            'category' => 'sometimes|string',
            'priority' => 'sometimes|string',
            'assigned' => 'sometimes|string',
            'search' => 'sometimes|string',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $format = $request->get('format', 'csv');
            $ticketIds = $request->get('ticket_ids');

            // Build query
            $query = Ticket::with(['user:id,name,email,role', 'assignedTo:id,name,email,role']);

            // If specific ticket IDs provided, use them
            if ($ticketIds && count($ticketIds) > 0) {
                $query->whereIn('id', $ticketIds);
            } else {
                // Apply filters for general export
                if ($request->has('status') && $request->status !== 'all') {
                    $query->where('status', $request->status);
                }

                if ($request->has('category') && $request->category !== 'all') {
                    $query->where('category', $request->category);
                }

                if ($request->has('priority') && $request->priority !== 'all') {
                    $query->where('priority', $request->priority);
                }

                if ($request->has('assigned') && $request->assigned !== 'all') {
                    if ($request->assigned === 'unassigned') {
                        $query->whereNull('assigned_to');
                    } else {
                        $query->whereNotNull('assigned_to');
                    }
                }

                if ($request->has('search') && !empty($request->search)) {
                    $search = $request->search;
                    $query->where(function ($q) use ($search) {
                        $q->where('ticket_number', 'LIKE', "%{$search}%")
                          ->orWhere('subject', 'LIKE', "%{$search}%")
                          ->orWhere('description', 'LIKE', "%{$search}%");
                    });
                }

                if ($request->has('date_from')) {
                    $query->where('created_at', '>=', $request->date_from);
                }

                if ($request->has('date_to')) {
                    $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
                }
            }

            $tickets = $query->orderBy('created_at', 'desc')->get();

            if ($tickets->count() === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tickets found to export.'
                ], 404);
            }

            // Prepare export data
            $exportData = $tickets->map(function ($ticket) {
                return [
                    'ticket_number' => $ticket->ticket_number,
                    'subject' => $ticket->subject,
                    'description' => $ticket->description,
                    'category' => $ticket->category,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                    'crisis_flag' => $ticket->crisis_flag ? 'Yes' : 'No',
                    'created_by' => $ticket->user ? $ticket->user->name : 'Unknown',
                    'created_by_email' => $ticket->user ? $ticket->user->email : 'Unknown',
                    'assigned_to' => $ticket->assignedTo ? $ticket->assignedTo->name : 'Unassigned',
                    'assigned_to_email' => $ticket->assignedTo ? $ticket->assignedTo->email : '',
                    'tags' => $ticket->tags ? implode(', ', $ticket->tags) : '',
                    'created_at' => $ticket->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $ticket->updated_at->format('Y-m-d H:i:s'),
                    'resolved_at' => $ticket->resolved_at ? $ticket->resolved_at->format('Y-m-d H:i:s') : '',
                    'closed_at' => $ticket->closed_at ? $ticket->closed_at->format('Y-m-d H:i:s') : '',
                ];
            });

            // Generate filename
            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "tickets_export_{$timestamp}.{$format}";

            Log::info("✅ Exporting {$tickets->count()} tickets in {$format} format");

            // Return the data for frontend to handle download
            return response()->json([
                'success' => true,
                'message' => "Successfully exported {$tickets->count()} tickets",
                'data' => [
                    'tickets' => $exportData,
                    'filename' => $filename,
                    'format' => $format,
                    'count' => $tickets->count()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('=== TICKET EXPORT FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export tickets.',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk assign tickets
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        Log::info('=== BULK ASSIGNING TICKETS ===');
        Log::info('User: ' . $request->user()->id . ' (' . $request->user()->role . ')');

        $user = $request->user();

        // Only admins can bulk assign
        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can bulk assign tickets.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ticket_ids' => 'required|array|min:1',
            'ticket_ids.*' => 'exists:tickets,id',
            'assigned_to' => 'required|exists:users,id',
            'reason' => 'sometimes|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $ticketIds = $request->input('ticket_ids');
            $assignedTo = $request->input('assigned_to');
            $reason = $request->input('reason', 'Bulk assignment by administrator');

            // Validate assigned user role
            $assignedUser = User::find($assignedTo);
            if (!$assignedUser || !in_array($assignedUser->role, ['counselor', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only assign tickets to counselors or administrators.'
                ], 422);
            }

            DB::beginTransaction();

            $assignedCount = 0;
            $tickets = Ticket::whereIn('id', $ticketIds)->get();

            foreach ($tickets as $ticket) {
                $oldAssignment = $ticket->assigned_to;
                
                // Update assignment
                $ticket->update([
                    'assigned_to' => $assignedTo,
                    'status' => 'In Progress'
                ]);

                $assignedCount++;

                // Create notification for newly assigned user (avoid duplicates)
                if ($assignedTo !== $oldAssignment) {
                    try {
                        \App\Models\Notification::create([
                            'user_id' => $assignedTo,
                            'type' => 'ticket',
                            'title' => 'Tickets Bulk Assigned',
                            'message' => "You have been assigned ticket #{$ticket->ticket_number} via bulk assignment.",
                            'priority' => $ticket->crisis_flag ? 'high' : 'medium',
                            'data' => json_encode(['ticket_id' => $ticket->id, 'bulk_assignment' => true]),
                        ]);
                    } catch (Exception $e) {
                        Log::warning('Failed to create notification for ticket: ' . $ticket->id);
                    }
                }
            }

            // Create summary notification for assigned user
            if ($assignedCount > 0) {
                try {
                    \App\Models\Notification::create([
                        'user_id' => $assignedTo,
                        'type' => 'ticket',
                        'title' => 'Bulk Assignment Summary',
                        'message' => "You have been assigned {$assignedCount} tickets via bulk assignment. Reason: {$reason}",
                        'priority' => 'medium',
                        'data' => json_encode(['assigned_count' => $assignedCount, 'reason' => $reason]),
                    ]);
                } catch (Exception $e) {
                    Log::warning('Failed to create summary notification');
                }
            }

            DB::commit();

            Log::info("✅ Bulk assigned {$assignedCount} tickets to user: {$assignedUser->name}");

            return response()->json([
                'success' => true,
                'message' => "Successfully assigned {$assignedCount} tickets to {$assignedUser->name}",
                'data' => [
                    'assigned_count' => $assignedCount,
                    'assigned_to' => [
                        'id' => $assignedUser->id,
                        'name' => $assignedUser->name,
                        'email' => $assignedUser->email,
                        'role' => $assignedUser->role
                    ]
                ]
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('=== BULK ASSIGNMENT FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk assign tickets.',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get system statistics for admin dashboard
     */
    public function getSystemStats(Request $request): JsonResponse
    {
        Log::info('=== FETCHING SYSTEM STATS ===');

        $user = $request->user();

        // Only admins can view system stats
        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can view system statistics.'
            ], 403);
        }

        try {
            $stats = [
                'tickets' => [
                    'total' => Ticket::count(),
                    'open' => Ticket::where('status', 'Open')->count(),
                    'in_progress' => Ticket::where('status', 'In Progress')->count(),
                    'resolved' => Ticket::where('status', 'Resolved')->count(),
                    'closed' => Ticket::where('status', 'Closed')->count(),
                    'crisis' => Ticket::where('crisis_flag', true)->count(),
                    'unassigned' => Ticket::whereNull('assigned_to')->count(),
                    'created_today' => Ticket::whereDate('created_at', today())->count(),
                    'created_this_week' => Ticket::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                    'created_this_month' => Ticket::whereMonth('created_at', now()->month)->count(),
                ],
                'users' => [
                    'total' => User::count(),
                    'students' => User::where('role', 'student')->count(),
                    'counselors' => User::where('role', 'counselor')->count(),
                    'admins' => User::where('role', 'admin')->count(),
                    'active' => User::where('status', 'active')->count(),
                    'inactive' => User::where('status', 'inactive')->count(),
                ],
                'performance' => [
                    'avg_response_time' => '2.3 hours', // This would be calculated from actual data
                    'avg_resolution_time' => '1.2 days', // This would be calculated from actual data
                    'satisfaction_score' => '4.8/5.0', // This would come from surveys
                    'first_response_rate' => '94%', // This would be calculated
                ]
            ];

            Log::info('✅ System stats retrieved successfully');

            return response()->json([
                'success' => true,
                'data' => ['stats' => $stats]
            ]);

        } catch (Exception $e) {
            Log::error('=== SYSTEM STATS FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve system statistics.',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get available staff for assignment
     */
    public function getAvailableStaff(Request $request): JsonResponse
    {
        Log::info('=== FETCHING AVAILABLE STAFF ===');

        $user = $request->user();

        // Only admins can view available staff
        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can view available staff.'
            ], 403);
        }

        try {
            // Get counselors and admins with their current workload
            $staff = User::whereIn('role', ['counselor', 'admin'])
                        ->where('status', 'active')
                        ->withCount(['assignedTickets' => function ($query) {
                            $query->whereIn('status', ['Open', 'In Progress']);
                        }])
                        ->orderBy('assigned_tickets_count', 'asc')
                        ->get(['id', 'name', 'email', 'role']);

            Log::info('✅ Available staff retrieved successfully');

            return response()->json([
                'success' => true,
                'data' => ['staff' => $staff]
            ]);

        } catch (Exception $e) {
            Log::error('=== AVAILABLE STAFF FETCH FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available staff.',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get ticket analytics for reports
     */
    public function getTicketAnalytics(Request $request): JsonResponse
    {
        Log::info('=== FETCHING TICKET ANALYTICS ===');

        $user = $request->user();

        // Only admins can view analytics
        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can view analytics.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'timeframe' => 'sometimes|in:7,30,90,365',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $timeframe = $request->get('timeframe', '30');
            $dateFrom = $request->get('date_from', now()->subDays($timeframe));
            $dateTo = $request->get('date_to', now());

            // Base query for timeframe
            $baseQuery = Ticket::whereBetween('created_at', [$dateFrom, $dateTo]);

            $analytics = [
                'overview' => [
                    'total_tickets' => (clone $baseQuery)->count(),
                    'open_tickets' => (clone $baseQuery)->where('status', 'Open')->count(),
                    'in_progress_tickets' => (clone $baseQuery)->where('status', 'In Progress')->count(),
                    'resolved_tickets' => (clone $baseQuery)->where('status', 'Resolved')->count(),
                    'closed_tickets' => (clone $baseQuery)->where('status', 'Closed')->count(),
                    'crisis_tickets' => (clone $baseQuery)->where('crisis_flag', true)->count(),
                    'unassigned_tickets' => (clone $baseQuery)->whereNull('assigned_to')->count(),
                ],
                'trends' => [
                    'created_this_period' => (clone $baseQuery)->count(),
                    'resolved_this_period' => (clone $baseQuery)->where('status', 'Resolved')->count(),
                    'average_resolution_time' => '1.2 days', // Would be calculated from actual data
                ],
                'by_category' => (clone $baseQuery)
                    ->selectRaw('category, COUNT(*) as count')
                    ->groupBy('category')
                    ->pluck('count', 'category')
                    ->toArray(),
                'by_priority' => (clone $baseQuery)
                    ->selectRaw('priority, COUNT(*) as count')
                    ->groupBy('priority')
                    ->pluck('count', 'priority')
                    ->toArray(),
                'staff_performance' => User::where('role', 'counselor')
                    ->where('status', 'active')
                    ->withCount([
                        'assignedTickets as total_assigned' => function ($query) use ($dateFrom, $dateTo) {
                            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
                        },
                        'assignedTickets as resolved_count' => function ($query) use ($dateFrom, $dateTo) {
                            $query->where('status', 'Resolved')
                                  ->whereBetween('created_at', [$dateFrom, $dateTo]);
                        }
                    ])
                    ->get(['id', 'name', 'role'])
                    ->toArray(),
            ];

            Log::info('✅ Ticket analytics retrieved successfully');

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (Exception $e) {
            Log::error('=== TICKET ANALYTICS FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ticket analytics.',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }
}