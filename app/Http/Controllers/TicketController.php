<?php
// app/Http/Controllers/TicketController.php (Enhanced with role-based features)

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketResponse;
use App\Models\TicketAttachment;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    /**
     * Get tickets based on user role with enhanced filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            Log::info('=== FETCHING TICKETS (ROLE-BASED) ===');
            Log::info('User: ' . $request->user()->id . ' (' . $request->user()->role . ')');
            
            $user = $request->user();
            
            // Build query based on user role
            $query = $this->buildRoleBasedQuery($user);
            
            // Apply filters
            $this->applyFilters($query, $request);
            
            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);
            
            // Get paginated results with optimized loading
            $tickets = $query->with([
                'user:id,name,email,role',
                'assignedTo:id,name,email,role'
            ])->paginate($request->get('per_page', 15));

            Log::info('Found ' . $tickets->total() . ' tickets for role: ' . $user->role);

            // Get role-specific stats
            $stats = Ticket::getStatsForUser($user);

            // Add response and attachment counts
            $this->addTicketCounts($tickets);

            Log::info('=== TICKETS FETCH SUCCESS ===');

            return response()->json([
                'success' => true,
                'data' => [
                    'tickets' => $tickets->items(),
                    'pagination' => [
                        'current_page' => $tickets->currentPage(),
                        'last_page' => $tickets->lastPage(),
                        'per_page' => $tickets->perPage(),
                        'total' => $tickets->total(),
                    ],
                    'stats' => $stats,
                    'user_role' => $user->role,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('=== TICKETS FETCH FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tickets.',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Create new ticket with enhanced validation and auto-assignment
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('=== TICKET CREATION START ===');
        Log::info('User ID: ' . $request->user()->id);
        Log::info('User Role: ' . $request->user()->role);

        // Only students can create tickets (unless admin creates on behalf)
        $user = $request->user();
        if (!$user->isStudent() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only students can create tickets.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'description' => 'required|string|min:20|max:5000',
            'category' => [
                'required',
                Rule::in(array_keys(Ticket::getAvailableCategories()))
            ],
            'priority' => [
                'sometimes',
                Rule::in(array_keys(Ticket::getAvailablePriorities()))
            ],
            'attachments' => 'sometimes|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:pdf,png,jpg,jpeg,gif,doc,docx,txt',
            'created_for' => 'sometimes|exists:users,id', // Admin creating for student
        ], [
            'description.min' => 'Description must be at least 20 characters long.',
            'attachments.*.max' => 'Each file must not exceed 10MB.',
            'attachments.*.mimes' => 'Only PDF, images, and document files are allowed.',
        ]);

        if ($validator->fails()) {
            Log::warning('=== TICKET VALIDATION FAILED ===');
            Log::warning('Errors: ' . json_encode($validator->errors()));
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Determine the ticket owner
            $ticketUserId = $user->isAdmin() && $request->has('created_for') 
                ? $request->created_for 
                : $user->id;

            // Create ticket with enhanced data
            $ticketData = [
                'user_id' => $ticketUserId,
                'subject' => trim($request->subject),
                'description' => trim($request->description),
                'category' => $request->category,
                'priority' => $request->get('priority', Ticket::PRIORITY_MEDIUM),
                'status' => Ticket::STATUS_OPEN,
                'crisis_flag' => $this->detectCrisisKeywords($request->description),
            ];

            Log::info('Creating ticket with data: ' . json_encode($ticketData));
            $ticket = Ticket::create($ticketData);
            Log::info('✅ Ticket created with ID: ' . $ticket->id);

            // Handle attachments
            if ($request->hasFile('attachments')) {
                $this->handleTicketAttachments($ticket, $request->file('attachments'));
            }

            // Auto-assign ticket
            $ticket->autoAssign();

            // Create initial response
            $this->createInitialResponse($ticket, $request->description, $ticketUserId);

            // Create notifications
            $this->createTicketNotifications($ticket);

            DB::commit();

            // Load relationships for response
            $ticket->load(['user', 'assignedTo', 'responses.user', 'attachments']);

            Log::info('=== TICKET CREATION SUCCESS ===');

            return response()->json([
                'success' => true,
                'message' => 'Ticket created successfully',
                'data' => ['ticket' => $ticket]
            ], 201);

        } catch (\Exception $e) {
            Log::error('=== TICKET CREATION FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket. Please try again.',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get single ticket with role-based access control
     */
    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        try {
            Log::info('=== FETCHING TICKET DETAILS ===');
            Log::info('Ticket ID: ' . $ticket->id);
            
            $user = $request->user();

            // Check role-based access
            if (!$ticket->canBeViewedBy($user)) {
                Log::warning('Unauthorized access attempt to ticket: ' . $ticket->id . ' by user: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view this ticket.'
                ], 403);
            }

            // Load appropriate responses based on role
            $responseQuery = function ($query) use ($user) {
                if ($user->isStudent()) {
                    // Students only see public responses
                    $query->where('is_internal', false);
                } else {
                    // Staff see all responses
                    $query->orderBy('is_internal', 'asc'); // Public first, then internal
                }
                
                return $query->with(['user:id,name,email,role', 'attachments'])
                            ->orderBy('created_at', 'asc');
            };

            $ticket->load([
                'user:id,name,email,role',
                'assignedTo:id,name,email,role',
                'responses' => $responseQuery,
                'attachments'
            ]);

            Log::info('✅ Ticket details loaded successfully');

            return response()->json([
                'success' => true,
                'data' => [
                    'ticket' => $ticket,
                    'permissions' => [
                        'can_modify' => $ticket->canBeModifiedBy($user),
                        'can_assign' => $ticket->canBeAssignedBy($user),
                        'can_view_internal' => $user->isStaff(),
                        'can_add_tags' => $user->isStaff(),
                        'can_delete' => $user->isAdmin(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('=== TICKET DETAILS FETCH FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ticket details.',
            ], 500);
        }
    }

    /**
     * Update ticket with role-based permissions
     */
    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        Log::info('=== UPDATING TICKET ===');
        Log::info('Ticket ID: ' . $ticket->id);
        
        $user = $request->user();

        // Check permissions
        if (!$ticket->canBeModifiedBy($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to modify this ticket.'
            ], 403);
        }

        // Build validation rules based on role
        $rules = [];
        
        if ($user->isStaff() || $user->isAdmin()) {
            $rules = array_merge($rules, [
                'status' => [
                    'sometimes',
                    Rule::in(array_keys(Ticket::getAvailableStatuses()))
                ],
                'priority' => [
                    'sometimes', 
                    Rule::in(array_keys(Ticket::getAvailablePriorities()))
                ],
                'crisis_flag' => 'sometimes|boolean',
                'tags' => 'sometimes|array',
                'tags.*' => Rule::in(array_keys(Ticket::getAvailableTags())),
            ]);
        }

        if ($user->isAdmin()) {
            $rules['assigned_to'] = 'sometimes|nullable|exists:users,id';
        }

        if ($user->isStudent()) {
            // Students can only update their own open tickets (limited fields)
            $rules = [
                'subject' => 'sometimes|string|max:255',
                'description' => 'sometimes|string|min:20|max:5000',
            ];
            
            if (!$ticket->isOpen()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only modify open tickets.'
                ], 403);
            }
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = $request->only(array_keys($rules));
            Log::info('Update data: ' . json_encode($updateData));

            // Handle special cases
            if (isset($updateData['assigned_to'])) {
                // Validate assigned user is staff
                if ($updateData['assigned_to']) {
                    $assignedUser = User::find($updateData['assigned_to']);
                    if (!$assignedUser || !$assignedUser->isStaff()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Can only assign tickets to staff members'
                        ], 422);
                    }
                }
            }

            // Handle tags
            if (isset($updateData['tags'])) {
                // Replace existing tags
                $ticket->tags = $updateData['tags'];
                unset($updateData['tags']);
            }

            // Set resolved/closed timestamps
            if (isset($updateData['status'])) {
                if (in_array($updateData['status'], [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])) {
                    if (!$ticket->resolved_at) {
                        $updateData['resolved_at'] = now();
                    }
                    if ($updateData['status'] === Ticket::STATUS_CLOSED && !$ticket->closed_at) {
                        $updateData['closed_at'] = now();
                    }
                }
            }

            $ticket->update($updateData);
            Log::info('✅ Ticket updated successfully');

            // Reload with relationships
            $ticket->load(['user', 'assignedTo']);

            return response()->json([
                'success' => true,
                'message' => 'Ticket updated successfully',
                'data' => ['ticket' => $ticket]
            ]);
        } catch (\Exception $e) {
            Log::error('=== TICKET UPDATE FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket.',
            ], 500);
        }
    }

    /**
     * Add response with role-based permissions
     */
    public function addResponse(Request $request, Ticket $ticket): JsonResponse
    {
        Log::info('=== ADDING TICKET RESPONSE ===');
        Log::info('Ticket ID: ' . $ticket->id);
        Log::info('User: ' . $request->user()->id . ' (' . $request->user()->role . ')');

        $user = $request->user();

        // Check if user can view the ticket first
        if (!$ticket->canBeViewedBy($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this ticket.'
            ], 403);
        }

        // Build validation rules based on role
        $rules = [
            'message' => 'required|string|min:5|max:5000',
            'attachments' => 'sometimes|array|max:3',
            'attachments.*' => 'file|max:10240|mimes:pdf,png,jpg,jpeg,gif,doc,docx,txt',
        ];

        // Staff can add internal responses
        if ($user->isStaff()) {
            $rules = array_merge($rules, [
                'is_internal' => 'sometimes|boolean',
                'visibility' => 'sometimes|in:all,counselors,admins',
                'is_urgent' => 'sometimes|boolean',
            ]);
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            Log::warning('Response validation failed: ' . json_encode($validator->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Students cannot add internal responses
            $isInternal = $user->isStaff() && $request->get('is_internal', false);
            
            if ($user->isStudent() && $request->get('is_internal')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Students cannot create internal responses'
                ], 403);
            }

            DB::beginTransaction();

            // Create response
            $responseData = [
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'message' => trim($request->message),
                'is_internal' => $isInternal,
                'visibility' => $request->get('visibility', 'all'),
                'is_urgent' => $request->get('is_urgent', false),
            ];
            
            Log::info('Creating response with data: ' . json_encode($responseData));
            $response = TicketResponse::create($responseData);
            Log::info('✅ Response created with ID: ' . $response->id);

            // Handle attachments
            if ($request->hasFile('attachments')) {
                $this->handleResponseAttachments($ticket, $response->id, $request->file('attachments'));
            }

            // Update ticket status if needed
            if ($ticket->status === Ticket::STATUS_OPEN && $user->isStaff()) {
                $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);
                Log::info('✅ Ticket status updated to In Progress');
            }

            // Add auto-tags based on content and role
            $this->addAutoTags($ticket, $response, $user);

            DB::commit();

            // Load relationships for response
            $response->load(['user:id,name,email,role', 'attachments']);

            Log::info('=== RESPONSE ADDED SUCCESS ===');

            return response()->json([
                'success' => true,
                'message' => 'Response added successfully',
                'data' => ['response' => $response]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('=== RESPONSE CREATION FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add response.',
            ], 500);
        }
    }

    /**
     * Assign ticket to staff member (admin and staff reassignment)
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        Log::info('=== ASSIGNING TICKET ===');
        Log::info('Ticket ID: ' . $ticket->id);
        Log::info('User: ' . $request->user()->id . ' (' . $request->user()->role . ')');
        
        $user = $request->user();

        // Check permissions
        if (!$ticket->canBeAssignedBy($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to assign this ticket.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'assigned_to' => 'nullable|exists:users,id',
            'reason' => 'sometimes|string|max:500', // Reason for assignment/reassignment
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $assignedTo = $request->assigned_to;
            
            // Validate assigned user is staff (if not null)
            if ($assignedTo) {
                $assignedUser = User::find($assignedTo);
                if (!$assignedUser->isStaff()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Can only assign tickets to staff members'
                    ], 422);
                }

                // Check if assigned user can handle this category
                $canHandle = $this->canUserHandleCategory($assignedUser, $ticket->category);
                if (!$canHandle) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Selected staff member cannot handle this ticket category'
                    ], 422);
                }

                Log::info('Assigning ticket to: ' . $assignedUser->name . ' (ID: ' . $assignedUser->id . ')');
                $ticket->assignTo($assignedTo);
                
                // Create internal note about assignment
                if ($request->has('reason')) {
                    TicketResponse::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'message' => 'Ticket assigned to ' . $assignedUser->name . '. Reason: ' . $request->reason,
                        'is_internal' => true,
                        'visibility' => 'all',
                    ]);
                }
            } else {
                Log::info('Unassigning ticket');
                $ticket->unassign();
                
                // Create internal note about unassignment
                TicketResponse::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'message' => 'Ticket unassigned' . ($request->has('reason') ? '. Reason: ' . $request->reason : ''),
                    'is_internal' => true,
                    'visibility' => 'all',
                ]);
            }

            Log::info('✅ Ticket assignment updated successfully');

            return response()->json([
                'success' => true,
                'message' => $assignedTo ? 'Ticket assigned successfully' : 'Ticket unassigned successfully',
                'data' => ['ticket' => $ticket->fresh(['user', 'assignedTo'])]
            ]);
        } catch (\Exception $e) {
            Log::error('=== TICKET ASSIGNMENT FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign ticket.',
            ], 500);
        }
    }

    /**
     * Add or remove tags (staff only)
     */
    public function manageTags(Request $request, Ticket $ticket): JsonResponse
    {
        Log::info('=== MANAGING TICKET TAGS ===');
        
        $user = $request->user();

        if (!$user->isStaff()) {
            return response()->json([
                'success' => false,
                'message' => 'Only staff members can manage tags.'
            ], 403);
        }

        if (!$ticket->canBeViewedBy($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this ticket.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:add,remove,set',
            'tags' => 'required|array',
            'tags.*' => 'string|in:' . implode(',', array_keys(Ticket::getAvailableTags())),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $action = $request->action;
            $tags = $request->tags;

            switch ($action) {
                case 'add':
                    foreach ($tags as $tag) {
                        $ticket->addTag($tag);
                    }
                    break;
                
                case 'remove':
                    foreach ($tags as $tag) {
                        $ticket->removeTag($tag);
                    }
                    break;
                
                case 'set':
                    $ticket->update(['tags' => $tags]);
                    break;
            }

            // Create internal note
            TicketResponse::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'message' => "Tags {$action}: " . implode(', ', $tags),
                'is_internal' => true,
                'visibility' => 'all',
            ]);

            Log::info('✅ Ticket tags updated successfully');

            return response()->json([
                'success' => true,
                'message' => 'Tags updated successfully',
                'data' => ['ticket' => $ticket->fresh()]
            ]);
        } catch (\Exception $e) {
            Log::error('=== TAG MANAGEMENT FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tags.',
            ], 500);
        }
    }

    /**
     * Delete ticket (admin only)
     */
    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        Log::info('=== DELETING TICKET ===');
        Log::info('Ticket ID: ' . $ticket->id);
        
        $user = $request->user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can delete tickets.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10|max:500',
            'notify_user' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $ticketNumber = $ticket->ticket_number;
            $ticketUser = $ticket->user;
            $reason = $request->reason;

            // Create deletion log
            Log::info("TICKET DELETION: #{$ticketNumber} by admin {$user->name} (ID: {$user->id}). Reason: {$reason}");

            // Notify user if requested
            if ($request->get('notify_user', false)) {
                Notification::createForUser(
                    $ticketUser->id,
                    Notification::TYPE_TICKET,
                    'Ticket Deleted',
                    "Your ticket #{$ticketNumber} has been deleted by an administrator. Reason: {$reason}",
                    Notification::PRIORITY_MEDIUM
                );
            }

            // Delete ticket (cascades to responses and attachments)
            $ticket->delete();

            Log::info('✅ Ticket deleted successfully');

            return response()->json([
                'success' => true,
                'message' => 'Ticket deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('=== TICKET DELETION FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket.',
            ], 500);
        }
    }

    /**
     * Get available staff for assignment
     */
    public function getAvailableStaff(Request $request, Ticket $ticket): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->isStaff() && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only staff members can view assignment options.'
                ], 403);
            }

            // Get staff who can handle this ticket category
            $availableStaff = User::where('status', User::STATUS_ACTIVE)
                                 ->where(function ($query) use ($ticket) {
                                     if (in_array($ticket->category, ['mental-health', 'crisis'])) {
                                         $query->where('role', User::ROLE_COUNSELOR);
                                     } elseif (in_array($ticket->category, ['academic', 'general'])) {
                                         $query->where('role', User::ROLE_ADVISOR);
                                     } else {
                                         $query->whereIn('role', [User::ROLE_COUNSELOR, User::ROLE_ADVISOR, User::ROLE_ADMIN]);
                                     }
                                 })
                                 ->withCount(['assignedTickets' => function ($query) {
                                     $query->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_IN_PROGRESS]);
                                 }])
                                 ->orderBy('assigned_tickets_count', 'asc')
                                 ->get(['id', 'name', 'email', 'role']);

            return response()->json([
                'success' => true,
                'data' => ['staff' => $availableStaff]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get available staff: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available staff.',
            ], 500);
        }
    }

    /**
     * Get ticket analytics for role
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Build base query for user's accessible tickets
            $query = $this->buildRoleBasedQuery($user);
            
            $timeframe = $request->get('timeframe', '30'); // days
            $startDate = now()->subDays($timeframe);
            
            $analytics = [
                'overview' => Ticket::getStatsForUser($user),
                'trends' => [
                    'created_this_period' => (clone $query)->where('created_at', '>=', $startDate)->count(),
                    'resolved_this_period' => (clone $query)->where('resolved_at', '>=', $startDate)->count(),
                    'average_resolution_time' => $this->getAverageResolutionTime($query, $startDate),
                ],
                'by_category' => (clone $query)->selectRaw('category, count(*) as count')
                                              ->groupBy('category')
                                              ->pluck('count', 'category'),
                'by_priority' => (clone $query)->selectRaw('priority, count(*) as count')
                                              ->groupBy('priority')
                                              ->pluck('count', 'priority'),
            ];

            // Add role-specific analytics
            if ($user->isAdmin()) {
                $analytics['staff_performance'] = $this->getStaffPerformance($startDate);
                $analytics['unassigned_tickets'] = Ticket::unassigned()->count();
            }

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get analytics: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics.',
            ], 500);
        }
    }

    /**
     * Build role-based query
     */
    private function buildRoleBasedQuery($user)
    {
        $query = Ticket::query();

        if ($user->isStudent()) {
            $query->forStudent($user->id);
        } elseif ($user->isCounselor()) {
            $query->forCounselor($user->id);
        } elseif ($user->isAdvisor()) {
            $query->forAdvisor($user->id);
        }
        // Admin sees all tickets

        return $query;
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, $request)
    {
        $filters = [];
        
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
            $filters['status'] = $request->status;
        }

        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
            $filters['category'] = $request->category;
        }

        if ($request->has('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
            $filters['priority'] = $request->priority;
        }

        if ($request->has('assigned') && $request->assigned !== 'all') {
            if ($request->assigned === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->whereNotNull('assigned_to');
            }
            $filters['assigned'] = $request->assigned;
        }

        if ($request->has('tags') && !empty($request->tags)) {
            $tags = is_array($request->tags) ? $request->tags : [$request->tags];
            foreach ($tags as $tag) {
                $query->withTag($tag);
            }
            $filters['tags'] = $tags;
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'LIKE', "%{$search}%")
                  ->orWhere('subject', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
            $filters['search'] = $search;
        }
        
        if (!empty($filters)) {
            Log::info('Applied filters: ' . json_encode($filters));
        }
    }

    /**
     * Add ticket counts efficiently
     */
    private function addTicketCounts($tickets)
    {
        if ($tickets->count() > 0) {
            $ticketIds = $tickets->pluck('id');
            
            $responseCounts = TicketResponse::whereIn('ticket_id', $ticketIds)
                ->selectRaw('ticket_id, count(*) as count')
                ->groupBy('ticket_id')
                ->pluck('count', 'ticket_id');
            
            $attachmentCounts = TicketAttachment::whereIn('ticket_id', $ticketIds)
                ->selectRaw('ticket_id, count(*) as count')
                ->groupBy('ticket_id')
                ->pluck('count', 'ticket_id');

            foreach ($tickets as $ticket) {
                $ticket->response_count = $responseCounts->get($ticket->id, 0);
                $ticket->attachment_count = $attachmentCounts->get($ticket->id, 0);
            }
        }
    }

    /**
     * Check if user can handle ticket category
     */
    private function canUserHandleCategory(User $user, string $category): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $categoryRoleMap = [
            Ticket::CATEGORY_MENTAL_HEALTH => [User::ROLE_COUNSELOR],
            Ticket::CATEGORY_CRISIS => [User::ROLE_COUNSELOR],
            Ticket::CATEGORY_ACADEMIC => [User::ROLE_ADVISOR],
            Ticket::CATEGORY_GENERAL => [User::ROLE_ADVISOR],
            Ticket::CATEGORY_TECHNICAL => [User::ROLE_ADMIN],
            Ticket::CATEGORY_OTHER => [User::ROLE_COUNSELOR, User::ROLE_ADVISOR],
        ];

        return isset($categoryRoleMap[$category]) && in_array($user->role, $categoryRoleMap[$category]);
    }

    /**
     * Handle ticket attachments
     */
    private function handleTicketAttachments(Ticket $ticket, array $files): void
    {
        foreach ($files as $file) {
            if ($file && $file->isValid()) {
                $this->storeAttachment($ticket, $file);
            }
        }
    }

    /**
     * Handle response attachments
     */
    private function handleResponseAttachments(Ticket $ticket, int $responseId, array $files): void
    {
        foreach ($files as $file) {
            if ($file && $file->isValid()) {
                $this->storeAttachment($ticket, $file, $responseId);
            }
        }
    }

    /**
     * Create initial response
     */
    private function createInitialResponse(Ticket $ticket, string $description, int $userId): void
    {
        TicketResponse::create([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'message' => $description,
            'is_internal' => false,
        ]);
    }

    /**
     * Create ticket notifications
     */
    private function createTicketNotifications(Ticket $ticket): void
    {
        // Notify admins
        $admins = User::where('role', User::ROLE_ADMIN)
                     ->where('status', User::STATUS_ACTIVE)
                     ->pluck('id')
                     ->toArray();
        
        if (!empty($admins)) {
            $priority = $ticket->crisis_flag ? Notification::PRIORITY_HIGH : Notification::PRIORITY_MEDIUM;
            $title = $ticket->crisis_flag ? '🚨 CRISIS TICKET CREATED' : 'New Support Ticket';
            $message = $ticket->crisis_flag 
                ? "URGENT: Crisis ticket #{$ticket->ticket_number} created. Immediate attention required!"
                : "New ticket #{$ticket->ticket_number} created by {$ticket->user->name}";
            
            Notification::createForUsers(
                $admins,
                Notification::TYPE_TICKET,
                $title,
                $message,
                $priority,
                ['ticket_id' => $ticket->id, 'crisis' => $ticket->crisis_flag]
            );
        }

        // Notify assigned staff if auto-assigned
        if ($ticket->assigned_to) {
            Notification::createForUser(
                $ticket->assigned_to,
                Notification::TYPE_TICKET,
                'New Ticket Assigned',
                "You have been assigned ticket #{$ticket->ticket_number}: {$ticket->subject}",
                $ticket->crisis_flag ? Notification::PRIORITY_HIGH : Notification::PRIORITY_MEDIUM,
                ['ticket_id' => $ticket->id]
            );
        }
    }

    /**
     * Add automatic tags based on content and context
     */
    private function addAutoTags(Ticket $ticket, TicketResponse $response, User $user): void
    {
        $message = strtolower($response->message);
        
        // Auto-tag based on keywords
        if (strpos($message, 'urgent') !== false || strpos($message, 'asap') !== false) {
            $ticket->addTag(Ticket::TAG_URGENT);
        }
        
        if (strpos($message, 'follow up') !== false || strpos($message, 'follow-up') !== false) {
            $ticket->addTag(Ticket::TAG_FOLLOW_UP);
        }
        
        // Auto-tag if response is from staff
        if ($user->isStaff() && !$response->is_internal) {
            $ticket->addTag(Ticket::TAG_REVIEWED);
        }
    }

    /**
     * Get average resolution time
     */
    private function getAverageResolutionTime($query, $startDate): float
    {
        $resolvedTickets = (clone $query)->whereNotNull('resolved_at')
                                        ->where('resolved_at', '>=', $startDate)
                                        ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours')
                                        ->first();
        
        return round($resolvedTickets->avg_hours ?? 0, 1);
    }

    /**
     * Get staff performance data
     */
    private function getStaffPerformance($startDate): array
    {
        return User::whereIn('role', [User::ROLE_COUNSELOR, User::ROLE_ADVISOR])
                  ->where('status', User::STATUS_ACTIVE)
                  ->withCount([
                      'assignedTickets as total_assigned',
                      'assignedTickets as resolved_count' => function ($query) use ($startDate) {
                          $query->where('status', Ticket::STATUS_RESOLVED)
                                ->where('resolved_at', '>=', $startDate);
                      }
                  ])
                  ->get(['id', 'name', 'role'])
                  ->toArray();
    }

    /**
     * Store attachment with enhanced error handling
     */
    private function storeAttachment($ticket, $file, $responseId = null): void
    {
        try {
            $filename = time() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
            $path = $file->storeAs(
                'ticket-attachments/' . date('Y/m'), 
                $filename, 
                'private'
            );
            
            TicketAttachment::create([
                'ticket_id' => $ticket->id,
                'response_id' => $responseId,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to store attachment: " . $e->getMessage());
            throw new \Exception("Failed to store attachment: {$file->getClientOriginalName()}");
        }
    }

    /**
     * Detect crisis keywords in text
     */
    private function detectCrisisKeywords($text): bool
    {
        $crisisKeywords = [
            'suicide', 'kill myself', 'end my life', 'want to die', 'take my life',
            'suicidal', 'killing myself', 'ending it all', 'better off dead',
            'self harm', 'hurt myself', 'cutting', 'cut myself', 'self injury',
            'crisis', 'emergency', 'urgent help', 'immediate help', 'desperate',
            'can\'t cope', 'overwhelmed', 'breakdown', 'mental breakdown',
            'overdose', 'too many pills', 'drink to death',
            'hopeless', 'worthless', 'no point', 'give up', 'can\'t go on'
        ];

        $text = strtolower($text);
        foreach ($crisisKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                Log::warning("🚨 CRISIS KEYWORD DETECTED: '{$keyword}' in content");
                return true;
            }
        }

        return false;
    }
}