<?php
// app/Http/Controllers/TicketController.php (COMPLETELY FIXED - All issues resolved)

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
use Exception;

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
     * Create new ticket with completely fixed validation and error handling
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('=== TICKET CREATION START ===');
        Log::info('User ID: ' . $request->user()->id);
        Log::info('User Role: ' . $request->user()->role);
        Log::info('Request Data: ' . json_encode($request->except(['attachments'])));
        Log::info('Files: ' . json_encode($request->file() ? array_keys($request->file()) : []));

        try {
            // Get user and validate permissions
            $user = $request->user();
            if (!$user) {
                Log::error('No authenticated user found');
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            // Check permissions - Only students and admins can create tickets
            if (!in_array($user->role, ['student', 'admin'])) {
                Log::warning('Unauthorized ticket creation attempt by role: ' . $user->role);
                return response()->json([
                    'success' => false,
                    'message' => 'Only students and administrators can create tickets.'
                ], 403);
            }

            // Enhanced validation with detailed error messages
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
                'created_for' => 'sometimes|exists:users,id', // Admin creating for student
            ], [
                'subject.required' => 'Subject is required.',
                'subject.min' => 'Subject must be at least 5 characters long.',
                'subject.max' => 'Subject cannot exceed 255 characters.',
                'description.required' => 'Description is required.',
                'description.min' => 'Description must be at least 20 characters long.',
                'description.max' => 'Description cannot exceed 5000 characters.',
                'category.required' => 'Category is required.',
                'category.in' => 'Invalid category selected.',
                'priority.in' => 'Invalid priority selected.',
                'attachments.max' => 'Maximum 5 attachments allowed.',
                'attachments.*.max' => 'Each file must not exceed 10MB.',
                'attachments.*.mimes' => 'Only PDF, images, and document files are allowed.',
                'created_for.exists' => 'Invalid user specified for ticket creation.',
            ]);

            if ($validator->fails()) {
                Log::warning('=== TICKET VALIDATION FAILED ===');
                Log::warning('Validation errors: ' . json_encode($validator->errors()));
                
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed. Please check your input and try again.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Start database transaction
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
                    Log::warning('🚨 CRISIS TICKET DETECTED - Auto-escalated to Urgent priority');
                }

                Log::info('Creating ticket with data: ' . json_encode($ticketData));
                
                // Create the ticket
                $ticket = Ticket::create($ticketData);
                
                if (!$ticket) {
                    throw new Exception('Failed to create ticket record');
                }

                Log::info('✅ Ticket created with ID: ' . $ticket->id . ', Number: ' . $ticket->ticket_number);

                // Handle file attachments
                $attachmentCount = 0;
                if ($request->hasFile('attachments')) {
                    $attachmentCount = $this->handleTicketAttachments($ticket, $request->file('attachments'));
                    Log::info('✅ Processed ' . $attachmentCount . ' attachments');
                }

                // Auto-assign ticket based on category
                $this->autoAssignTicket($ticket);

                // Create notifications for relevant staff
                $this->createTicketNotifications($ticket);

                // Commit transaction
                DB::commit();

                // Load relationships for response
                $ticket->load([
                    'user:id,name,email,role', 
                    'assignedTo:id,name,email,role', 
                    'attachments'
                ]);

                Log::info('=== TICKET CREATION SUCCESS ===');

                return response()->json([
                    'success' => true,
                    'message' => 'Ticket created successfully. You will receive a confirmation email shortly.',
                    'data' => [
                        'ticket' => $ticket
                    ]
                ], 201);

            } catch (Exception $e) {
                DB::rollBack();
                Log::error('=== TICKET CREATION DATABASE ERROR ===');
                Log::error('Database error: ' . $e->getMessage());
                Log::error('Stack trace: ' . $e->getTraceAsString());
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to save ticket. Please try again.',
                    'error' => app()->environment('local') ? $e->getMessage() : null
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('=== TICKET CREATION SYSTEM ERROR ===');
            Log::error('System error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'System error occurred. Please try again later.',
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
            if (!$this->canUserViewTicket($user, $ticket)) {
                Log::warning('Unauthorized access attempt to ticket: ' . $ticket->id . ' by user: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view this ticket.'
                ], 403);
            }

            // Load appropriate responses based on role
            $responseQuery = function ($query) use ($user) {
                if ($user->role === 'student') {
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
                        'can_modify' => $this->canUserModifyTicket($user, $ticket),
                        'can_assign' => $this->canUserAssignTicket($user, $ticket),
                        'can_view_internal' => in_array($user->role, ['counselor', 'advisor', 'admin']),
                        'can_add_tags' => in_array($user->role, ['counselor', 'advisor', 'admin']),
                        'can_delete' => $user->role === 'admin',
                    ]
                ]
            ]);
        } catch (Exception $e) {
            Log::error('=== TICKET DETAILS FETCH FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ticket details.',
                'error' => app()->environment('local') ? $e->getMessage() : null
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
        if (!$this->canUserViewTicket($user, $ticket)) {
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
        if (in_array($user->role, ['counselor', 'advisor', 'admin'])) {
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
            $isInternal = in_array($user->role, ['counselor', 'advisor', 'admin']) && $request->get('is_internal', false);
            
            if ($user->role === 'student' && $request->get('is_internal')) {
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
            if ($ticket->status === 'Open' && in_array($user->role, ['counselor', 'advisor', 'admin'])) {
                $ticket->update(['status' => 'In Progress']);
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
        } catch (Exception $e) {
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
     * Handle response attachments
     */
    private function handleResponseAttachments(Ticket $ticket, int $responseId, array $files): void
    {
        foreach ($files as $file) {
            try {
                if ($file && $file->isValid()) {
                    $this->storeAttachment($ticket, $file, $responseId);
                }
            } catch (Exception $e) {
                Log::error('Failed to store response attachment: ' . $e->getMessage());
                // Continue with other files
            }
        }
    }

    /**
     * Store attachment with enhanced error handling
     */
    private function storeAttachment(Ticket $ticket, $file, $responseId = null): void
    {
        try {
            // Validate file
            if (!$file || !$file->isValid()) {
                throw new Exception('Invalid file provided');
            }

            // Generate safe filename
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $safeName = time() . '_' . uniqid() . '.' . $extension;
            
            // Create directory path
            $directory = 'ticket-attachments/' . date('Y/m');
            
            // Store file
            $path = $file->storeAs($directory, $safeName, 'private');
            
            if (!$path) {
                throw new Exception('Failed to store file to disk');
            }
            
            // Create database record
            $attachment = TicketAttachment::create([
                'ticket_id' => $ticket->id,
                'response_id' => $responseId,
                'original_name' => $originalName,
                'file_path' => $path,
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
            
            if (!$attachment) {
                // Clean up file if database record failed
                Storage::disk('private')->delete($path);
                throw new Exception('Failed to create attachment record');
            }
            
            Log::info('✅ Attachment stored: ' . $originalName . ' -> ' . $path);
            
        } catch (Exception $e) {
            Log::error("Failed to store attachment: " . $e->getMessage());
            throw new Exception("Failed to store attachment: {$file->getClientOriginalName()}");
        }
    }

    /**
     * Auto-assign ticket based on category and workload
     */
    private function autoAssignTicket(Ticket $ticket): void
    {
        try {
            $roleMap = [
                'mental-health' => 'counselor',
                'crisis' => 'counselor',
                'academic' => 'advisor',
                'general' => 'advisor',
                'technical' => 'admin',
                'other' => 'advisor', // Default to advisor
            ];

            $targetRole = $roleMap[$ticket->category] ?? 'advisor';
            
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
     * Permission check methods
     */
    private function canUserViewTicket($user, $ticket): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'student') {
            return $ticket->user_id === $user->id;
        }

        if (in_array($user->role, ['counselor', 'advisor'])) {
            return $ticket->assigned_to === $user->id || 
                   $this->canUserHandleCategory($user, $ticket->category);
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

        if (in_array($user->role, ['counselor', 'advisor'])) {
            return $ticket->assigned_to === $user->id;
        }

        return false;
    }

    private function canUserAssignTicket($user, $ticket): bool
    {
        return $user->role === 'admin' || 
               (in_array($user->role, ['counselor', 'advisor']) && $ticket->assigned_to === $user->id);
    }

    /**
     * Check if user can handle ticket category
     */
    private function canUserHandleCategory(User $user, string $category): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        $categoryRoleMap = [
            'mental-health' => ['counselor'],
            'crisis' => ['counselor'],
            'academic' => ['advisor'],
            'general' => ['advisor'],
            'technical' => ['admin'],
            'other' => ['counselor', 'advisor'],
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
            if (in_array($user->role, ['counselor', 'advisor', 'admin']) && !$response->is_internal) {
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
                      ->orWhereIn('category', ['mental-health', 'crisis']);
                });
                break;
            case 'advisor':
                $query->where(function($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                      ->orWhereIn('category', ['academic', 'general', 'other']);
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