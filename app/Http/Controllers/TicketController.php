<?php
// app/Http/Controllers/TicketController.php - FIXED: Response integration and download issues

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketResponse;
use App\Models\TicketAttachment;
use App\Models\Notification;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Exception;

class TicketController extends Controller
{
    use ApiResponseTrait;

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
            $sortBy = $request->get('sort_by', 'updated_at');
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
        } catch (Exception $e) {
            Log::error('=== TICKETS FETCH FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tickets.',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Create new ticket with FIXED validation and response handling
     */
    public function store(Request $request): JsonResponse
    {
        $this->logRequestDetails('Ticket Creation');

        Log::info('=== CREATING TICKET ===', [
            'user_id' => $request->user()->id,
            'user_role' => $request->user()->role,
            'has_files' => $request->hasFile('attachments'),
        ]);

        try {
            $user = $request->user();

            // Check permissions
            if (!in_array($user->role, ['student', 'admin'])) {
                return $this->forbiddenResponse('Only students and administrators can create tickets');
            }

            // Enhanced validation with better error messages
            $validator = Validator::make($request->all(), [
                'subject' => 'required|string|min:5|max:255',
                'description' => 'required|string|min:20|max:5000',
                'category' => [
                    'required',
                    'string',
                    Rule::in(['general', 'academic', 'mental-health', 'crisis', 'technical', 'other'])
                ],
                'priority' => [
                    'sometimes',
                    'string',
                    Rule::in(['Low', 'Medium', 'High', 'Urgent'])
                ],
                'attachments' => 'sometimes|array|max:5',
                'attachments.*' => 'file|max:10240|mimes:pdf,png,jpg,jpeg,gif,doc,docx,txt',
                'created_for' => 'sometimes|exists:users,id',
            ], [
                'subject.required' => 'Subject is required',
                'subject.min' => 'Subject must be at least 5 characters long',
                'subject.max' => 'Subject cannot exceed 255 characters',
                'description.required' => 'Description is required',
                'description.min' => 'Description must be at least 20 characters long',
                'description.max' => 'Description cannot exceed 5000 characters',
                'category.required' => 'Category is required',
                'category.in' => 'Please select a valid category',
                'priority.in' => 'Please select a valid priority',
                'attachments.max' => 'Maximum 5 attachments allowed',
                'attachments.*.max' => 'Each file must not exceed 10MB',
                'attachments.*.mimes' => 'Only PDF, images, and document files are allowed',
                'created_for.exists' => 'Invalid user specified for ticket creation',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Ticket validation failed', [
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check your input and try again');
            }

            DB::beginTransaction();

            try {
                // Determine the ticket owner
                $ticketUserId = $user->role === 'admin' && $request->has('created_for') 
                    ? $request->created_for 
                    : $user->id;

                // Validate created_for user if specified
                if ($request->has('created_for') && $user->role === 'admin') {
                    $targetUser = User::find($request->created_for);
                    if (!$targetUser || $targetUser->role !== 'student') {
                        throw new Exception('Can only create tickets for students');
                    }
                }

                // Detect crisis keywords and auto-set priority/category
                $description = trim($request->description);
                $crisisDetected = $this->detectCrisisKeywords($description);
                
                // Prepare ticket data
                $ticketData = [
                    'user_id' => $ticketUserId,
                    'subject' => trim($request->subject),
                    'description' => $description,
                    'category' => $request->category,
                    'priority' => $request->get('priority', 'Medium'),
                    'status' => 'Open',
                    'crisis_flag' => $crisisDetected,
                ];

                // Auto-escalate crisis cases
                if ($crisisDetected) {
                    $ticketData['priority'] = 'Urgent';
                    if ($request->category !== 'crisis') {
                        $ticketData['category'] = 'crisis';
                    }
                    Log::warning('🚨 Crisis ticket detected - auto-escalated');
                }

                // Create the ticket
                $ticket = Ticket::create($ticketData);

                if (!$ticket) {
                    throw new Exception('Failed to create ticket record');
                }

                Log::info('✅ Ticket created', [
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                ]);

                // Handle file attachments
                if ($request->hasFile('attachments')) {
                    $attachmentCount = $this->handleTicketAttachments($ticket, $request->file('attachments'));
                    Log::info("✅ Processed {$attachmentCount} attachments");
                }

                // Auto-assign ticket
                $this->autoAssignTicket($ticket);

                // Create notifications
                $this->createTicketNotifications($ticket);

                DB::commit();

                // Load relationships for response
                $ticket->load([
                    'user:id,name,email,role', 
                    'assignedTo:id,name,email,role', 
                    'attachments'
                ]);

                Log::info('✅ Ticket creation completed successfully');

                return $this->successResponse([
                    'ticket' => $ticket
                ], 'Ticket created successfully. You will receive a confirmation email shortly.', 201);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('🚨 Ticket creation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'Ticket creation');
        }
    }

    // ENHANCED: Get single ticket with full conversation - FIXED: Complete data loading
    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        try {
            Log::info('=== FETCHING TICKET DETAILS ===');
            Log::info('Ticket ID: ' . $ticket->id);
            
            $user = $request->user();

            // Check role-based access
            if (!$this->canUserViewTicket($user, $ticket)) {
                Log::warning('Unauthorized access attempt to ticket: ' . $ticket->id . ' by user: ' . $user->id);
                return $this->forbiddenResponse('You do not have permission to view this ticket.');
            }

            // FIXED: Load ALL responses properly with attachments
            $responseQuery = function ($query) use ($user) {
                if ($user->role === 'student') {
                    // Students only see public responses
                    $query->where('is_internal', false);
                } else {
                    // Staff see all responses
                    $query->orderBy('is_internal', 'asc'); // Public first, then internal
                }
                
                return $query->with([
                    'user:id,name,email,role', 
                    'attachments' => function($q) {
                        $q->select('id', 'response_id', 'ticket_id', 'original_name', 'file_path', 'file_type', 'file_size', 'created_at');
                    }
                ])->orderBy('created_at', 'asc');
            };

            // FIXED: Load complete ticket data with all relationships
            $ticket->load([
                'user:id,name,email,role',
                'assignedTo:id,name,email,role',
                'responses' => $responseQuery,
                'attachments' => function($query) {
                    $query->whereNull('response_id') // Only ticket-level attachments
                          ->select('id', 'ticket_id', 'original_name', 'file_path', 'file_type', 'file_size', 'created_at');
                }
            ]);

            // FIXED: Ensure we have complete response data
            if ($ticket->responses) {
                foreach ($ticket->responses as $response) {
                    // Ensure each response has its attachments loaded
                    if (!$response->relationLoaded('attachments')) {
                        $response->load(['attachments' => function($query) {
                            $query->select('id', 'response_id', 'ticket_id', 'original_name', 'file_path', 'file_type', 'file_size', 'created_at');
                        }]);
                    }
                }
            }

            Log::info('✅ Ticket details loaded successfully with ' . ($ticket->responses ? $ticket->responses->count() : 0) . ' responses');

            return $this->successResponse([
                'ticket' => $ticket,
                'permissions' => [
                    'can_modify' => $this->canUserModifyTicket($user, $ticket),
                    'can_assign' => $this->canUserAssignTicket($user, $ticket),
                    'can_view_internal' => in_array($user->role, ['counselor', 'admin']),
                    'can_add_tags' => in_array($user->role, ['counselor', 'admin']),
                    'can_delete' => $user->role === 'admin',
                    'can_download_attachments' => true, // All users who can view ticket can download attachments
                ]
            ]);
        } catch (Exception $e) {
            Log::error('=== TICKET DETAILS FETCH FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return $this->serverErrorResponse('Failed to fetch ticket details.');
        }
    }

    /**
     * UPDATE TICKET - FIXED: Added missing update method
     */
    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        Log::info('=== UPDATING TICKET ===');
        Log::info('Ticket ID: ' . $ticket->id);
        Log::info('User: ' . $request->user()->id . ' (' . $request->user()->role . ')');

        $user = $request->user();

        // Check if user can modify the ticket
        if (!$this->canUserModifyTicket($user, $ticket)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to modify this ticket.'
            ], 403);
        }

        // Validation rules
        $rules = [
            'subject' => 'sometimes|string|min:5|max:255',
            'description' => 'sometimes|string|min:20|max:5000',
            'category' => [
                'sometimes',
                'string',
                Rule::in(['general', 'academic', 'mental-health', 'crisis', 'technical', 'other'])
            ],
            'priority' => [
                'sometimes',
                'string',
                Rule::in(['Low', 'Medium', 'High', 'Urgent'])
            ],
            'status' => [
                'sometimes',
                'string',
                Rule::in(['Open', 'In Progress', 'Resolved', 'Closed'])
            ],
            'assigned_to' => 'sometimes|nullable|exists:users,id',
            'crisis_flag' => 'sometimes|boolean',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:50',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            Log::warning('Update validation failed: ' . json_encode($validator->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $updateData = $request->only([
                'subject', 'description', 'category', 'priority', 'status', 
                'assigned_to', 'crisis_flag', 'tags'
            ]);

            // Filter out null values
            $updateData = array_filter($updateData, function($value) {
                return $value !== null;
            });

            Log::info('Updating ticket with data: ' . json_encode($updateData));

            // Update the ticket
            $ticket->update($updateData);

            // If status is being updated to resolved/closed, set timestamps
            if (isset($updateData['status'])) {
                if ($updateData['status'] === 'Resolved' && !$ticket->resolved_at) {
                    $ticket->update(['resolved_at' => now()]);
                }
                if ($updateData['status'] === 'Closed' && !$ticket->closed_at) {
                    $ticket->update(['closed_at' => now()]);
                }
            }

            DB::commit();

            // Load relationships for response
            $ticket->load([
                'user:id,name,email,role',
                'assignedTo:id,name,email,role',
                'responses',
                'attachments'
            ]);

            Log::info('✅ Ticket updated successfully');

            return response()->json([
                'success' => true,
                'message' => 'Ticket updated successfully',
                'data' => ['ticket' => $ticket]
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('=== TICKET UPDATE FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket.',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * FIXED: Delete ticket with proper response handling
     */
    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        $this->logRequestDetails('Ticket Deletion');
        
        Log::info('=== DELETING TICKET ===', [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'user_id' => $request->user()->id,
            'user_role' => $request->user()->role,
            'request_method' => $request->method(),
        ]);

        try {
            $user = $request->user();

            // Only admins can delete tickets
            if ($user->role !== 'admin') {
                Log::warning('❌ Unauthorized delete attempt', [
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'ticket_id' => $ticket->id,
                ]);
                return $this->forbiddenResponse('Only administrators can delete tickets');
            }

            // Handle both JSON body (DELETE) and form data (POST)
            $inputData = $request->method() === 'DELETE' 
                ? $request->all() 
                : $request->only(['reason', 'notify_user']);

            $validator = Validator::make($inputData, [
                'reason' => 'required|string|min:10|max:500',
                'notify_user' => 'sometimes|boolean'
            ], [
                'reason.required' => 'Deletion reason is required',
                'reason.min' => 'Deletion reason must be at least 10 characters',
                'reason.max' => 'Deletion reason cannot exceed 500 characters',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Delete validation failed', [
                    'ticket_id' => $ticket->id,
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please provide a valid deletion reason');
            }

            // Start transaction
            DB::beginTransaction();

            try {
                $reason = $inputData['reason'];
                $notifyUser = $inputData['notify_user'] ?? false;
                $ticketNumber = $ticket->ticket_number;
                $ticketId = $ticket->id;
                $userId = $ticket->user_id;
                $ticketSubject = $ticket->subject;

                Log::info('🗑️ Processing ticket deletion', [
                    'ticket_id' => $ticketId,
                    'ticket_number' => $ticketNumber,
                    'reason' => $reason,
                    'notify_user' => $notifyUser,
                ]);

                // Create notification for user if requested
                if ($notifyUser && $userId) {
                    try {
                        Notification::create([
                            'user_id' => $userId,
                            'type' => 'ticket',
                            'title' => 'Ticket Deleted',
                            'message' => "Your ticket #{$ticketNumber} \"{$ticketSubject}\" has been deleted. Reason: {$reason}",
                            'priority' => 'medium',
                            'data' => json_encode([
                                'ticket_id' => $ticketId,
                                'ticket_number' => $ticketNumber,
                                'reason' => $reason,
                                'deleted_by' => $user->name,
                            ]),
                        ]);
                        Log::info('✅ User notification created for deletion');
                    } catch (Exception $e) {
                        Log::warning('⚠️ Failed to create user notification', [
                            'error' => $e->getMessage(),
                        ]);
                        // Don't fail the deletion for notification issues
                    }
                }

                // Delete associated files from storage
                try {
                    $attachments = TicketAttachment::where('ticket_id', $ticketId)->get();
                    $deletedFiles = 0;
                    
                    foreach ($attachments as $attachment) {
                        if ($attachment->file_path && Storage::disk('private')->exists($attachment->file_path)) {
                            if (Storage::disk('private')->delete($attachment->file_path)) {
                                $deletedFiles++;
                            }
                        }
                    }
                    
                    Log::info("🗑️ Cleaned up {$deletedFiles} attachment files");
                } catch (Exception $e) {
                    Log::warning('⚠️ File cleanup error', [
                        'error' => $e->getMessage(),
                    ]);
                    // Don't fail the deletion for file cleanup issues
                }

                // Force delete to ensure immediate removal
                $deleted = $ticket->forceDelete();

                if (!$deleted) {
                    throw new Exception('Failed to delete ticket from database');
                }

                // Commit transaction
                DB::commit();

                Log::info('✅ Ticket deleted successfully', [
                    'ticket_id' => $ticketId,
                    'ticket_number' => $ticketNumber,
                    'deleted_by' => $user->name,
                ]);

                // Return consistent success response
                return $this->deleteSuccessResponse('Ticket', $ticketNumber)
                    ->setData(array_merge($this->deleteSuccessResponse('Ticket', $ticketNumber)->getData(true), [
                        'data' => array_merge($this->deleteSuccessResponse('Ticket', $ticketNumber)->getData(true)['data'], [
                            'ticket_number' => $ticketNumber,
                            'deletion_reason' => $reason,
                            'user_notified' => $notifyUser,
                            'deleted_by' => [
                                'id' => $user->id,
                                'name' => $user->name,
                            ],
                        ])
                    ]));

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('🚨 Ticket deletion failed', [
                'ticket_id' => $ticket->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'Ticket deletion');
        }
    }

    /**
     * ASSIGN TICKET - FIXED: Enhanced assignment method
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        Log::info('=== ASSIGNING TICKET ===');
        Log::info('Ticket ID: ' . $ticket->id);
        Log::info('User: ' . $request->user()->id . ' (' . $request->user()->role . ')');

        $user = $request->user();

        // Only admins can assign tickets
        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators can assign tickets.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'assigned_to' => 'nullable|exists:users,id',
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
            DB::beginTransaction();

            $assignedTo = $request->input('assigned_to');
            $reason = $request->input('reason', '');

            // Validate assigned user role if not null
            if ($assignedTo) {
                $assignedUser = User::find($assignedTo);
                if (!$assignedUser || !in_array($assignedUser->role, ['counselor', 'admin'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Can only assign tickets to counselors or administrators.'
                    ], 422);
                }
            }

            $oldAssignment = $ticket->assigned_to;
            
            // Update assignment
            $ticket->update([
                'assigned_to' => $assignedTo,
                'status' => $assignedTo ? 'In Progress' : 'Open'
            ]);

            // Create notifications
            if ($assignedTo && $assignedTo !== $oldAssignment) {
                // Notify the newly assigned user
                Notification::create([
                    'user_id' => $assignedTo,
                    'type' => 'ticket',
                    'title' => 'Ticket Assigned',
                    'message' => "You have been assigned ticket #{$ticket->ticket_number}: {$ticket->subject}",
                    'priority' => $ticket->crisis_flag ? 'high' : 'medium',
                    'data' => json_encode(['ticket_id' => $ticket->id]),
                ]);

                // Notify the ticket owner
                Notification::create([
                    'user_id' => $ticket->user_id,
                    'type' => 'ticket',
                    'title' => 'Ticket Assigned',
                    'message' => "Your ticket #{$ticket->ticket_number} has been assigned to a counselor.",
                    'priority' => 'medium',
                    'data' => json_encode(['ticket_id' => $ticket->id]),
                ]);
            }

            DB::commit();

            // Load relationships for response
            $ticket->load([
                'user:id,name,email,role',
                'assignedTo:id,name,email,role'
            ]);

            Log::info('✅ Ticket assignment updated successfully');

            return response()->json([
                'success' => true,
                'message' => $assignedTo ? 'Ticket assigned successfully' : 'Ticket unassigned successfully',
                'data' => ['ticket' => $ticket]
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('=== TICKET ASSIGNMENT FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign ticket.',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * MANAGE TAGS - FIXED: Added missing tag management method
     */
    public function manageTags(Request $request, Ticket $ticket): JsonResponse
    {
        Log::info('=== MANAGING TICKET TAGS ===');
        Log::info('Ticket ID: ' . $ticket->id);
        Log::info('User: ' . $request->user()->id . ' (' . $request->user()->role . ')');

        $user = $request->user();

        // Check permissions - only counselors and admins can manage tags
        if (!in_array($user->role, ['counselor', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to manage tags.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:add,remove,set',
            'tags' => 'required|array',
            'tags.*' => 'string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $action = $request->input('action');
            $newTags = $request->input('tags');
            $currentTags = $ticket->tags ?? [];

            switch ($action) {
                case 'add':
                    $updatedTags = array_unique(array_merge($currentTags, $newTags));
                    break;
                
                case 'remove':
                    $updatedTags = array_diff($currentTags, $newTags);
                    break;
                
                case 'set':
                    $updatedTags = array_unique($newTags);
                    break;
                
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid action specified.'
                    ], 422);
            }

            // Update the ticket
            $ticket->update(['tags' => array_values($updatedTags)]);

            // Load relationships for response
            $ticket->load([
                'user:id,name,email,role',
                'assignedTo:id,name,email,role'
            ]);

            Log::info('✅ Ticket tags updated successfully');

            return response()->json([
                'success' => true,
                'message' => 'Tags updated successfully',
                'data' => ['ticket' => $ticket]
            ]);

        } catch (Exception $e) {
            Log::error('=== TAG MANAGEMENT FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tags.',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Add response to ticket - FIXED: Complete integration with frontend
     */
    public function addResponse(Request $request, Ticket $ticket): JsonResponse
    {
        Log::info('=== ADDING TICKET RESPONSE ===');
        Log::info('Ticket ID: ' . $ticket->id);
        Log::info('User: ' . $request->user()->id . ' (' . $request->user()->role . ')');

        $user = $request->user();

        // Check if user can view the ticket first
        if (!$this->canUserViewTicket($user, $ticket)) {
            return $this->forbiddenResponse('You do not have permission to access this ticket.');
        }

        // Build validation rules based on role
        $rules = [
            'message' => 'required|string|min:5|max:5000',
            'attachments' => 'sometimes|array|max:3',
            'attachments.*' => 'file|max:10240|mimes:pdf,png,jpg,jpeg,gif,doc,docx,txt',
        ];

        // Staff can add internal responses
        if (in_array($user->role, ['counselor', 'admin'])) {
            $rules = array_merge($rules, [
                'is_internal' => 'sometimes|boolean',
                'visibility' => 'sometimes|in:all,counselors,admins',
                'is_urgent' => 'sometimes|boolean',
            ]);
        }

        $validator = Validator::make($request->all(), $rules, [
            'message.required' => 'Response message is required',
            'message.min' => 'Response must be at least 5 characters long',
            'message.max' => 'Response cannot exceed 5000 characters',
            'attachments.max' => 'Maximum 3 attachments allowed per response',
            'attachments.*.max' => 'Each file must not exceed 10MB',
            'attachments.*.mimes' => 'Only PDF, images, and document files are allowed',
        ]);

        if ($validator->fails()) {
            Log::warning('Response validation failed: ' . json_encode($validator->errors()));
            return $this->validationErrorResponse($validator, 'Please check your input and try again');
        }

        try {
            // Students cannot add internal responses
            $isInternal = in_array($user->role, ['counselor', 'admin']) && $request->get('is_internal', false);
            
            if ($user->role === 'student' && $request->get('is_internal')) {
                return $this->forbiddenResponse('Students cannot create internal responses');
            }

            DB::beginTransaction();

            // FIXED: Create response with proper data structure
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
            
            if (!$response) {
                throw new Exception('Failed to create response record');
            }
            
            Log::info('✅ Response created with ID: ' . $response->id);

            // FIXED: Handle attachments with proper error handling
            $attachmentCount = 0;
            if ($request->hasFile('attachments')) {
                $attachmentCount = $this->handleResponseAttachments($ticket, $response->id, $request->file('attachments'));
                Log::info("✅ Processed {$attachmentCount} response attachments");
            }

            // Update ticket status if needed
            if ($ticket->status === 'Open' && in_array($user->role, ['counselor', 'admin'])) {
                $ticket->update(['status' => 'In Progress']);
                Log::info('✅ Ticket status updated to In Progress');
            }

            // Update ticket timestamp to show recent activity
            $ticket->touch();

            // Add auto-tags based on content and role
            $this->addAutoTags($ticket, $response, $user);

            // FIXED: Create notifications for response
            $this->createResponseNotifications($ticket, $response, $user);

            DB::commit();

            // FIXED: Load complete response data with relationships
            $response->load([
                'user:id,name,email,role', 
                'attachments' => function($query) {
                    $query->select('id', 'response_id', 'ticket_id', 'original_name', 'file_path', 'file_type', 'file_size', 'created_at');
                }
            ]);

            Log::info('=== RESPONSE ADDED SUCCESS ===');

            return $this->successResponse([
                'response' => $response,
                'ticket_id' => $ticket->id,
                'attachment_count' => $attachmentCount
            ], 'Response added successfully', 201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('=== RESPONSE CREATION FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return $this->serverErrorResponse('Failed to add response. Please try again.');
        }
    }

    /**
     * FIXED: Create proper notifications for responses
     */
    private function createResponseNotifications(Ticket $ticket, TicketResponse $response, User $user): void
    {
        try {
            // Don't notify for internal responses
            if ($response->is_internal) {
                Log::info('Skipping notifications for internal response');
                return;
            }

            // Notify the ticket creator if response is from staff
            if (in_array($user->role, ['counselor', 'admin']) && $user->id !== $ticket->user_id) {
                Notification::create([
                    'user_id' => $ticket->user_id,
                    'type' => 'ticket',
                    'title' => 'New Response',
                    'message' => "You have a new response on ticket #{$ticket->ticket_number}",
                    'priority' => $response->is_urgent ? 'high' : 'medium',
                    'data' => json_encode([
                        'ticket_id' => $ticket->id,
                        'response_id' => $response->id,
                        'response_urgent' => $response->is_urgent
                    ]),
                ]);
                Log::info('✅ Notification sent to ticket creator');
            }
            
            // Notify assigned staff if response is from student
            if ($user->role === 'student' && $ticket->assigned_to && $ticket->assigned_to !== $user->id) {
                Notification::create([
                    'user_id' => $ticket->assigned_to,
                    'type' => 'ticket',
                    'title' => 'Student Response',
                    'message' => "Student responded to ticket #{$ticket->ticket_number}",
                    'priority' => $ticket->crisis_flag ? 'high' : 'medium',
                    'data' => json_encode([
                        'ticket_id' => $ticket->id,
                        'response_id' => $response->id,
                        'crisis' => $ticket->crisis_flag
                    ]),
                ]);
                Log::info('✅ Notification sent to assigned staff');
            }
            
        } catch (Exception $e) {
            Log::error('Failed to create response notifications: ' . $e->getMessage());
            // Don't throw - notification failure shouldn't prevent response creation
        }
    }

    /**
     * Handle ticket attachments with enhanced error handling
     */
    private function handleTicketAttachments(Ticket $ticket, array $files): int
    {
        $successCount = 0;
        
        foreach ($files as $file) {
            try {
                if ($file && $file->isValid()) {
                    $this->storeAttachment($ticket, $file);
                    $successCount++;
                } else {
                    Log::warning('Invalid file uploaded: ' . ($file ? $file->getClientOriginalName() : 'null'));
                }
            } catch (Exception $e) {
                Log::error('Failed to store attachment: ' . ($file ? $file->getClientOriginalName() : 'unknown') . ' - ' . $e->getMessage());
                // Continue with other files instead of failing completely
            }
        }
        
        return $successCount;
    }

    /**
     * FIXED: Handle response attachments with proper storage
     */
    private function handleResponseAttachments(Ticket $ticket, int $responseId, array $files): int
    {
        $successCount = 0;
        
        foreach ($files as $file) {
            try {
                if ($file && $file->isValid()) {
                    $this->storeAttachment($ticket, $file, $responseId);
                    $successCount++;
                } else {
                    Log::warning('Invalid response file uploaded: ' . ($file ? $file->getClientOriginalName() : 'null'));
                }
            } catch (Exception $e) {
                Log::error('Failed to store response attachment: ' . ($file ? $file->getClientOriginalName() : 'unknown') . ' - ' . $e->getMessage());
                // Continue with other files instead of failing completely
            }
        }
        
        return $successCount;
    }

    /**
     * FIXED: Enhanced attachment storage with multiple disk support
     */
    private function storeAttachment(Ticket $ticket, $file, $responseId = null): void
    {
        try {
            // Validate file
            if (!$file || !$file->isValid()) {
                throw new Exception('Invalid file provided');
            }

            // Generate safe filename with timestamp and random string
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $timestamp = time();
            $random = uniqid();
            $safeName = "{$timestamp}_{$random}.{$extension}";
            
            // Create directory path with year/month structure
            $directory = 'ticket-attachments/' . date('Y/m');
            
            // FIXED: Try multiple storage strategies
            $path = null;
            
            try {
                // First try: Use private disk for security
                $path = $file->storeAs($directory, $safeName, 'private');
                Log::info('✅ File stored on private disk: ' . $path);
            } catch (Exception $e) {
                Log::warning('Failed to store on private disk, trying local: ' . $e->getMessage());
                
                try {
                    // Fallback: Use local disk
                    $path = $file->storeAs($directory, $safeName, 'local');
                    Log::info('✅ File stored on local disk: ' . $path);
                } catch (Exception $e2) {
                    Log::warning('Failed to store on local disk, trying public: ' . $e2->getMessage());
                    
                    // Last resort: Use public disk
                    $path = $file->storeAs($directory, $safeName, 'public');
                    Log::info('✅ File stored on public disk: ' . $path);
                }
            }
            
            if (!$path) {
                throw new Exception('Failed to store file to any disk');
            }
            
            // FIXED: Create database record with proper file info
            $attachmentData = [
                'ticket_id' => $ticket->id,
                'response_id' => $responseId,
                'original_name' => $originalName,
                'file_path' => $path,
                'file_type' => $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => $file->getSize(),
            ];
            
            $attachment = TicketAttachment::create($attachmentData);
            
            if (!$attachment) {
                // Clean up file if database record failed
                try {
                    Storage::disk('private')->delete($path);
                    Storage::disk('local')->delete($path);
                    Storage::disk('public')->delete($path);
                } catch (Exception $cleanupError) {
                    Log::warning('Failed to cleanup file after DB error: ' . $cleanupError->getMessage());
                }
                throw new Exception('Failed to create attachment record');
            }
            
            Log::info('✅ Attachment stored successfully', [
                'original_name' => $originalName,
                'stored_path' => $path,
                'attachment_id' => $attachment->id,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);
            
        } catch (Exception $e) {
            Log::error("Failed to store attachment: " . $e->getMessage(), [
                'file_name' => $file ? $file->getClientOriginalName() : 'unknown',
                'file_size' => $file ? $file->getSize() : 0,
                'error_trace' => $e->getTraceAsString(),
            ]);
            throw new Exception("Failed to store attachment: {$file->getClientOriginalName()} - {$e->getMessage()}");
        }
    }

    /**
     * Auto-assign ticket based on category and workload (only to counselors)
     */
    private function autoAssignTicket(Ticket $ticket): void
    {
        try {
            $roleMap = [
                'mental-health' => 'counselor',
                'crisis' => 'counselor',
                'academic' => 'counselor',
                'general' => 'counselor',
                'technical' => 'admin',
                'other' => 'counselor',
            ];

            $targetRole = $roleMap[$ticket->category] ?? 'counselor';
            
            // Find available staff with least workload
            $availableStaff = User::where('role', $targetRole)
                                 ->where('status', 'active')
                                 ->withCount(['assignedTickets' => function ($query) {
                                     $query->whereIn('status', ['Open', 'In Progress']);
                                 }])
                                 ->orderBy('assigned_tickets_count', 'asc')
                                 ->first();

            if ($availableStaff) {
                $ticket->update(['assigned_to' => $availableStaff->id]);
                Log::info('✅ Ticket auto-assigned to: ' . $availableStaff->name . ' (' . $availableStaff->role . ')');
            } else {
                Log::warning('⚠️ No available staff found for auto-assignment (role: ' . $targetRole . ')');
            }
        } catch (Exception $e) {
            Log::error('Auto-assignment failed: ' . $e->getMessage());
            // Don't throw - assignment failure shouldn't prevent ticket creation
        }
    }

    /**
     * FIXED: Download ticket attachment with proper permission handling
     */
    public function downloadAttachment(Request $request, TicketAttachment $attachment): Response
    {
        Log::info('=== DOWNLOADING ATTACHMENT ===', [
            'attachment_id' => $attachment->id,
            'user_id' => $request->user()->id,
            'user_role' => $request->user()->role,
            'file_name' => $attachment->original_name,
            'file_path' => $attachment->file_path,
            'ticket_id' => $attachment->ticket_id,
        ]);

        try {
            $user = $request->user();

            // FIXED: Load the ticket relationship to check permissions
            $ticket = $attachment->ticket;
            if (!$ticket) {
                Log::error('❌ Attachment has no associated ticket', [
                    'attachment_id' => $attachment->id,
                    'ticket_id' => $attachment->ticket_id,
                ]);
                
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Associated ticket not found',
                    'timestamp' => now()->toISOString()
                ], 404);
            }

            // FIXED: Enhanced permission checking - be more permissive for authenticated users
            $canAccess = false;

            // Admin can access all attachments
            if ($user->role === 'admin') {
                $canAccess = true;
                Log::info('✅ Admin access granted');
            }
            // Ticket owner can access their ticket's attachments
            elseif ($ticket->user_id === $user->id) {
                $canAccess = true;
                Log::info('✅ Ticket owner access granted');
            }
            // Assigned staff can access the ticket's attachments
            elseif ($ticket->assigned_to === $user->id) {
                $canAccess = true;
                Log::info('✅ Assigned staff access granted');
            }
            // Counselors can access mental health and crisis category attachments
            elseif ($user->role === 'counselor' && in_array($ticket->category, ['mental-health', 'crisis', 'academic', 'general', 'other'])) {
                $canAccess = true;
                Log::info('✅ Counselor category access granted');
            }
            // For response attachments, check if user was involved in the conversation
            elseif ($attachment->response_id) {
                $response = TicketResponse::find($attachment->response_id);
                if ($response && ($response->user_id === $user->id || !$response->is_internal)) {
                    $canAccess = true;
                    Log::info('✅ Response participant access granted');
                }
            }

            if (!$canAccess) {
                Log::warning('❌ Unauthorized attachment access attempt', [
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'attachment_id' => $attachment->id,
                    'ticket_id' => $attachment->ticket_id,
                    'ticket_user_id' => $ticket->user_id,
                    'ticket_assigned_to' => $ticket->assigned_to,
                    'ticket_category' => $ticket->category,
                ]);
                
                return response()->json([
                    'success' => false,
                    'status' => 403,
                    'message' => 'You do not have permission to access this attachment',
                    'timestamp' => now()->toISOString()
                ], 403);
            }

            // FIXED: Enhanced file path resolution with multiple strategies
            $possiblePaths = [
                // Strategy 1: Private disk path
                storage_path('app/private/' . $attachment->file_path),
                // Strategy 2: Local disk path
                storage_path('app/' . $attachment->file_path),
                // Strategy 3: Direct path (in case file_path is already absolute)
                $attachment->file_path,
                // Strategy 4: Public disk path (fallback)
                storage_path('app/public/' . $attachment->file_path),
                // Strategy 5: Public web path (last resort)
                public_path('storage/' . $attachment->file_path),
            ];

            $filePath = null;
            $foundStrategy = null;
            
            foreach ($possiblePaths as $index => $path) {
                if (file_exists($path) && is_readable($path)) {
                    $filePath = $path;
                    $foundStrategy = $index + 1;
                    Log::info("✅ File found using strategy {$foundStrategy}: " . $path);
                    break;
                }
            }

            if (!$filePath) {
                Log::error('❌ Attachment file not found in any location', [
                    'attachment_id' => $attachment->id,
                    'attempted_paths' => $possiblePaths,
                    'stored_path' => $attachment->file_path,
                ]);
                
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Attachment file not found on server',
                    'debug' => app()->environment('local') ? [
                        'stored_path' => $attachment->file_path,
                        'attempted_paths' => $possiblePaths
                    ] : null,
                    'timestamp' => now()->toISOString()
                ], 404);
            }

            // FIXED: Get proper file information and validate
            $fileName = $attachment->original_name;
            $fileSize = filesize($filePath);
            $mimeType = $attachment->file_type ?: mime_content_type($filePath) ?: 'application/octet-stream';

            // Validate file integrity
            if ($fileSize === false || $fileSize === 0) {
                Log::error('❌ File exists but is empty or unreadable', [
                    'file_path' => $filePath,
                    'file_size' => $fileSize,
                ]);
                
                return response()->json([
                    'success' => false,
                    'status' => 500,
                    'message' => 'File is corrupted or unreadable',
                    'timestamp' => now()->toISOString()
                ], 500);
            }

            Log::info('✅ Serving attachment download', [
                'file' => $fileName,
                'size' => $fileSize,
                'mime_type' => $mimeType,
                'path' => $filePath,
                'strategy' => $foundStrategy,
                'user_id' => $user->id,
            ]);

            // FIXED: Return proper file download response with enhanced headers
            return response()->download($filePath, $fileName, [
                'Content-Type' => $mimeType,
                'Content-Length' => (string)$fileSize,
                'Content-Disposition' => 'attachment; filename="' . addslashes($fileName) . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'X-XSS-Protection' => '1; mode=block',
                // Additional headers for better compatibility
                'Accept-Ranges' => 'bytes',
                'Access-Control-Allow-Origin' => request()->header('Origin', '*'),
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Expose-Headers' => 'Content-Disposition, Content-Length, Content-Type',
            ])->deleteFileAfterSend(false); // Keep the file after sending

        } catch (Exception $e) {
            Log::error('🚨 Attachment download failed', [
                'attachment_id' => $attachment->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()->id ?? 'unknown',
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to download attachment. Please try again.',
                'error' => app()->environment('local') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Create notifications for ticket creation
     */
    private function createTicketNotifications(Ticket $ticket): void
    {
        try {
            // Notify admins about new tickets
            $admins = User::where('role', 'admin')
                         ->where('status', 'active')
                         ->get();
            
            foreach ($admins as $admin) {
                try {
                    Notification::create([
                        'user_id' => $admin->id,
                        'type' => 'ticket',
                        'title' => $ticket->crisis_flag ? '🚨 CRISIS TICKET CREATED' : 'New Support Ticket',
                        'message' => $ticket->crisis_flag 
                            ? "URGENT: Crisis ticket #{$ticket->ticket_number} created. Immediate attention required!"
                            : "New ticket #{$ticket->ticket_number} created by {$ticket->user->name}",
                        'priority' => $ticket->crisis_flag ? 'high' : 'medium',
                        'data' => json_encode(['ticket_id' => $ticket->id, 'crisis' => $ticket->crisis_flag]),
                    ]);
                } catch (Exception $e) {
                    Log::error('Failed to create admin notification: ' . $e->getMessage());
                }
            }

            // Notify assigned staff if auto-assigned
            if ($ticket->assigned_to) {
                try {
                    Notification::create([
                        'user_id' => $ticket->assigned_to,
                        'type' => 'ticket',
                        'title' => 'New Ticket Assigned',
                        'message' => "You have been assigned ticket #{$ticket->ticket_number}: {$ticket->subject}",
                        'priority' => $ticket->crisis_flag ? 'high' : 'medium',
                        'data' => json_encode(['ticket_id' => $ticket->id]),
                    ]);
                } catch (Exception $e) {
                    Log::error('Failed to create assignment notification: ' . $e->getMessage());
                }
            }
            
            Log::info('✅ Notifications created for ticket: ' . $ticket->ticket_number);
        } catch (Exception $e) {
            Log::error('Failed to create notifications: ' . $e->getMessage());
            // Don't throw - notification failure shouldn't prevent ticket creation
        }
    }

    /**
     * ENHANCED: Permission check methods with more granular access
     */
    private function canUserViewTicket($user, $ticket): bool
    {
        // Admin can view all tickets
        if ($user->role === 'admin') {
            return true;
        }

        // Student can view their own tickets
        if ($user->role === 'student') {
            return $ticket->user_id === $user->id;
        }

        // Counselor can view assigned tickets or tickets in their categories
        if ($user->role === 'counselor') {
            return $ticket->assigned_to === $user->id || 
                   in_array($ticket->category, ['mental-health', 'crisis', 'academic', 'general', 'other']);
        }

        return false;
    }

    private function canUserModifyTicket($user, $ticket): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'student') {
            return $ticket->user_id === $user->id && in_array($ticket->status, ['Open', 'In Progress']);
        }

        if ($user->role === 'counselor') {
            return $ticket->assigned_to === $user->id;
        }

        return false;
    }

    private function canUserAssignTicket($user, $ticket): bool
    {
        // Only admins can assign tickets
        return $user->role === 'admin';
    }

    private function canUserHandleCategory(User $user, string $category): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        // All categories now go to counselors (removed advisor role)
        $categoryRoleMap = [
            'mental-health' => ['counselor'],
            'crisis' => ['counselor'],
            'academic' => ['counselor'],
            'general' => ['counselor'],
            'technical' => ['admin'],
            'other' => ['counselor'],
        ];

        return isset($categoryRoleMap[$category]) && in_array($user->role, $categoryRoleMap[$category]);
    }

    /**
     * Detect crisis keywords in text
     */
    private function detectCrisisKeywords(string $text): bool
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

        $text = strtolower(trim($text));
        foreach ($crisisKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                Log::warning("🚨 CRISIS KEYWORD DETECTED: '{$keyword}' in ticket content");
                return true;
            }
        }

        return false;
    }

    /**
     * Add automatic tags based on content and context
     */
    private function addAutoTags(Ticket $ticket, TicketResponse $response, User $user): void
    {
        try {
            $message = strtolower($response->message);
            $tagsToAdd = [];
            
            // Auto-tag based on keywords
            if (strpos($message, 'urgent') !== false || strpos($message, 'asap') !== false) {
                $tagsToAdd[] = 'urgent';
            }
            
            if (strpos($message, 'follow up') !== false || strpos($message, 'follow-up') !== false) {
                $tagsToAdd[] = 'follow-up';
            }
            
            // Auto-tag if response is from staff
            if (in_array($user->role, ['counselor', 'admin']) && !$response->is_internal) {
                $tagsToAdd[] = 'reviewed';
            }

            // Add tags to ticket
            foreach ($tagsToAdd as $tag) {
                $currentTags = $ticket->tags ?? [];
                if (!in_array($tag, $currentTags)) {
                    $currentTags[] = $tag;
                    $ticket->update(['tags' => $currentTags]);
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to add auto tags: ' . $e->getMessage());
        }
    }

    /**
     * Build role-based query
     */
    private function buildRoleBasedQuery($user)
    {
        $query = Ticket::query();

        switch ($user->role) {
            case 'student':
                $query->where('user_id', $user->id);
                break;
            case 'counselor':
                $query->where(function($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                      ->orWhereIn('category', ['mental-health', 'crisis', 'academic', 'general', 'other']);
                });
                break;
            case 'admin':
                // Admin sees all tickets
                break;
        }

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
}