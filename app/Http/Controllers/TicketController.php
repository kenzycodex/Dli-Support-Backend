<?php
// app/Http/Controllers/TicketController.php (Enhanced with dynamic categories)

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketResponse;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Models\CrisisKeyword;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class TicketController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get tickets with role-based filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            Log::info('=== FETCHING TICKETS ===', [
                'user_id' => $request->user()->id,
                'user_role' => $request->user()->role,
                'filters' => $request->only(['status', 'category_id', 'priority', 'assigned', 'search'])
            ]);

            $user = $request->user();
            $query = Ticket::with([
                'user:id,name,email,role',
                'assignedTo:id,name,email,role',
                'category:id,name,slug,color,icon'
            ]);

            // Apply role-based filtering
            if ($user->isStudent()) {
                $query->forStudent($user->id);
            } elseif ($user->isCounselor() || $user->isAdvisor()) {
                $query->where('assigned_to', $user->id);
            }
            // Admin sees all tickets

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('category_id') && $request->category_id !== 'all') {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('priority') && $request->priority !== 'all') {
                $query->where('priority', $request->priority);
            }

            if ($request->has('assigned')) {
                if ($request->assigned === 'unassigned') {
                    $query->unassigned();
                } elseif ($request->assigned === 'assigned') {
                    $query->whereNotNull('assigned_to');
                }
            }

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('ticket_number', 'LIKE', "%{$search}%")
                      ->orWhere('subject', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%")
                      ->orWhereHas('user', function ($userQuery) use ($search) {
                          $userQuery->where('name', 'LIKE', "%{$search}%")
                                   ->orWhere('email', 'LIKE', "%{$search}%");
                      });
                });
            }

            // Order by priority score and creation date
            $query->byPriorityScore();

            $perPage = $request->get('per_page', 20);
            $tickets = $query->paginate($perPage);

            // Add additional data for the response
            $stats = Ticket::getStatsForUser($user);
            $categories = TicketCategory::active()->ordered()->get(['id', 'name', 'slug', 'color', 'icon']);

            Log::info('✅ Tickets fetched successfully', [
                'total' => $tickets->total(),
                'user_role' => $user->role
            ]);

            return $this->successResponse([
                'tickets' => $tickets->items(),
                'pagination' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total(),
                ],
                'stats' => $stats,
                'categories' => $categories,
                'user_role' => $user->role,
            ], 'Tickets retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Tickets fetch failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id
            ]);
            
            return $this->handleException($e, 'Tickets fetch');
        }
    }

    /**
     * Create new ticket with auto-assignment
     */
    public function store(Request $request): JsonResponse
    {
        $this->logRequestDetails('Ticket Creation');

        try {
            Log::info('=== CREATING TICKET ===', [
                'user_id' => $request->user()->id,
                'user_role' => $request->user()->role,
            ]);

            $validator = Validator::make($request->all(), [
                'subject' => 'required|string|min:5|max:255',
                'description' => 'required|string|min:20|max:5000',
                'category_id' => 'required|exists:ticket_categories,id',
                'priority' => 'sometimes|in:Low,Medium,High,Urgent',
                'attachments' => 'sometimes|array|max:5',
                'attachments.*' => 'file|max:10240|mimes:pdf,doc,docx,txt,jpg,jpeg,png,gif',
                'created_for' => 'sometimes|exists:users,id', // Admin creating for student
            ], [
                'subject.required' => 'Subject is required',
                'subject.min' => 'Subject must be at least 5 characters',
                'description.required' => 'Description is required',
                'description.min' => 'Description must be at least 20 characters',
                'category_id.required' => 'Please select a category',
                'category_id.exists' => 'Invalid category selected',
                'attachments.max' => 'Maximum 5 attachments allowed',
                'attachments.*.max' => 'Each file must be under 10MB',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator, 'Please check your input and try again');
            }

            // Verify category is active
            $category = TicketCategory::active()->find($request->category_id);
            if (!$category) {
                return $this->errorResponse('Selected category is not available', 422);
            }

            // Determine who the ticket is for
            $ticketUserId = $request->get('created_for', $request->user()->id);
            
            // Only admins can create tickets for others
            if ($ticketUserId !== $request->user()->id && !$request->user()->isAdmin()) {
                return $this->errorResponse('You can only create tickets for yourself', 403);
            }

            DB::beginTransaction();

            try {
                // Create ticket
                $ticket = new Ticket([
                    'user_id' => $ticketUserId,
                    'subject' => trim($request->subject),
                    'description' => trim($request->description),
                    'category_id' => $request->category_id,
                    'priority' => $request->get('priority', 'Medium'),
                ]);

                // Crisis detection and priority calculation happen in model boot
                $ticket->save();

                // Handle attachments
                if ($request->hasFile('attachments')) {
                    foreach ($request->file('attachments') as $file) {
                        $this->storeAttachment($ticket, $file);
                    }
                }

                // Load relationships for response
                $ticket->load([
                    'user:id,name,email,role',
                    'assignedTo:id,name,email,role',
                    'category:id,name,slug,color,icon',
                    'attachments'
                ]);

                DB::commit();

                Log::info('✅ Ticket created successfully', [
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'category' => $category->name,
                    'crisis_detected' => $ticket->crisis_flag,
                    'auto_assigned' => $ticket->assigned_to ? 'Yes' : 'No',
                ]);

                return $this->successResponse([
                    'ticket' => $ticket
                ], 'Ticket created successfully', 201);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Ticket creation failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return $this->handleException($e, 'Ticket creation');
        }
    }

    /**
     * Get ticket options (categories, priorities, etc.)
     */
    public function getOptions(Request $request): JsonResponse
    {
        try {
            Log::info('=== FETCHING TICKET OPTIONS ===', [
                'user_id' => $request->user()->id,
                'user_role' => $request->user()->role,
            ]);

            $user = $request->user();

            // Get available categories based on user role
            $categoriesQuery = TicketCategory::active()->ordered();
            
            // Students see all categories, counselors see their specializations
            if ($user->isCounselor() || $user->isAdvisor()) {
                $userCategories = $user->counselorSpecializations()
                    ->where('is_available', true)
                    ->pluck('category_id')
                    ->toArray();
                
                if (!empty($userCategories)) {
                    $categoriesQuery->whereIn('id', $userCategories);
                }
            }

            $categories = $categoriesQuery->get(['id', 'name', 'slug', 'description', 'color', 'icon', 'sla_response_hours']);

            $options = [
                'categories' => $categories,
                'priorities' => [
                    ['value' => 'Low', 'label' => 'Low', 'color' => 'bg-green-100 text-green-800'],
                    ['value' => 'Medium', 'label' => 'Medium', 'color' => 'bg-yellow-100 text-yellow-800'],
                    ['value' => 'High', 'label' => 'High', 'color' => 'bg-orange-100 text-orange-800'],
                    ['value' => 'Urgent', 'label' => 'Urgent', 'color' => 'bg-red-100 text-red-800'],
                ],
                'statuses' => [
                    ['value' => 'Open', 'label' => 'Open', 'color' => 'bg-blue-100 text-blue-800'],
                    ['value' => 'In Progress', 'label' => 'In Progress', 'color' => 'bg-yellow-100 text-yellow-800'],
                    ['value' => 'Resolved', 'label' => 'Resolved', 'color' => 'bg-green-100 text-green-800'],
                    ['value' => 'Closed', 'label' => 'Closed', 'color' => 'bg-gray-100 text-gray-800'],
                ],
                'available_staff' => $user->isAdmin() ? $this->getAvailableStaff() : [],
                'user_permissions' => $this->getUserPermissions($user),
                'crisis_keywords_active' => CrisisKeyword::active()->count() > 0,
            ];

            Log::info('✅ Ticket options retrieved successfully');

            return $this->successResponse($options, 'Ticket options retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Ticket options fetch failed', [
                'error' => $e->getMessage(),
            ]);
            
            return $this->handleException($e, 'Ticket options fetch');
        }
    }

    /**
     * Show single ticket with full conversation - FIXED for relationship and permission issues
     */
    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        try {
            Log::info('=== FETCHING TICKET DETAILS ===', [
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'user_role' => $request->user()->role,
            ]);

            $user = $request->user();

            // FIXED: Check permissions first with better error handling
            try {
                $canView = $user->can('view', $ticket);
                if (!$canView) {
                    Log::warning('Permission denied for ticket view', [
                        'user_id' => $user->id,
                        'user_role' => $user->role,
                        'ticket_id' => $ticket->id,
                        'ticket_user_id' => $ticket->user_id,
                        'ticket_assigned_to' => $ticket->assigned_to,
                        'ticket_category_id' => $ticket->category_id
                    ]);
                    
                    return $this->errorResponse('You do not have permission to view this ticket', 403);
                }
            } catch (\Exception $policyError) {
                Log::error('Policy check failed for ticket view', [
                    'user_id' => $user->id,
                    'ticket_id' => $ticket->id,
                    'error' => $policyError->getMessage(),
                    'trace' => $policyError->getTraceAsString()
                ]);
                
                // For admins, allow access even if policy fails
                if ($user->role !== 'admin') {
                    return $this->errorResponse('Permission check failed. Please try again.', 500);
                }
            }

            // FIXED: Load all relationships with proper error handling
            try {
                $ticket->load([
                    'user:id,name,email,role',
                    'assignedTo:id,name,email,role',
                    'category:id,name,slug,color,icon,sla_response_hours,crisis_detection_enabled',
                    'responses' => function ($query) use ($user) {
                        // FIXED: Filter responses based on user role
                        if ($user->role === 'student') {
                            $query->where('is_internal', false);
                        }
                        $query->orderBy('created_at', 'asc');
                    },
                    'responses.user:id,name,email,role',
                    'responses.attachments',
                    'attachments',
                    'assignmentHistory.assignedFrom:id,name',
                    'assignmentHistory.assignedTo:id,name',
                    'assignmentHistory.assignedBy:id,name',
                ]);
            } catch (\Exception $loadError) {
                Log::error('Failed to load ticket relationships', [
                    'ticket_id' => $ticket->id,
                    'error' => $loadError->getMessage(),
                    'trace' => $loadError->getTraceAsString()
                ]);
                
                // Try to load minimal relationships
                try {
                    $ticket->load([
                        'user:id,name,email,role',
                        'category:id,name,slug,color,icon'
                    ]);
                    
                    Log::info('Loaded minimal ticket relationships after error');
                } catch (\Exception $minimalLoadError) {
                    Log::error('Failed to load even minimal relationships', [
                        'ticket_id' => $ticket->id,
                        'error' => $minimalLoadError->getMessage()
                    ]);
                    
                    return $this->errorResponse('Failed to load ticket data. Please try again.', 500);
                }
            }

            // FIXED: Add computed fields with error handling
            try {
                // Calculate SLA deadline if category has SLA settings
                $ticket->sla_deadline = null;
                $ticket->is_overdue = false;
                
                if ($ticket->category && $ticket->category->sla_response_hours) {
                    $createdAt = \Carbon\Carbon::parse($ticket->created_at);
                    $deadline = $createdAt->addHours($ticket->category->sla_response_hours);
                    $ticket->sla_deadline = $deadline->toISOString();
                    
                    // Check if overdue (only for non-resolved tickets)
                    if (!in_array($ticket->status, ['Resolved', 'Closed'])) {
                        $ticket->is_overdue = now()->gt($deadline);
                    }
                }
                
                // Assignment type
                $ticket->assignment_type = $ticket->auto_assigned === 'yes' ? 'automatic' : 
                                        ($ticket->assigned_to ? 'manual' : 'unassigned');
                
            } catch (\Exception $computedError) {
                Log::warning('Failed to calculate computed fields', [
                    'ticket_id' => $ticket->id,
                    'error' => $computedError->getMessage()
                ]);
                
                // Set safe defaults
                $ticket->sla_deadline = null;
                $ticket->is_overdue = false;
                $ticket->assignment_type = 'unknown';
            }

            // FIXED: Ensure crisis keywords are properly formatted
            if (!$ticket->detected_crisis_keywords) {
                $ticket->detected_crisis_keywords = [];
            } elseif (is_string($ticket->detected_crisis_keywords)) {
                try {
                    $ticket->detected_crisis_keywords = json_decode($ticket->detected_crisis_keywords, true) ?: [];
                } catch (\Exception $jsonError) {
                    Log::warning('Failed to decode crisis keywords JSON', [
                        'ticket_id' => $ticket->id,
                        'error' => $jsonError->getMessage()
                    ]);
                    $ticket->detected_crisis_keywords = [];
                }
            }

            // FIXED: Ensure tags are properly formatted
            if (!$ticket->tags) {
                $ticket->tags = [];
            } elseif (is_string($ticket->tags)) {
                try {
                    $ticket->tags = json_decode($ticket->tags, true) ?: [];
                } catch (\Exception $jsonError) {
                    Log::warning('Failed to decode tags JSON', [
                        'ticket_id' => $ticket->id,
                        'error' => $jsonError->getMessage()
                    ]);
                    $ticket->tags = [];
                }
            }

            // FIXED: Ensure responses have proper structure
            if (!$ticket->responses) {
                $ticket->responses = collect([]);
            }

            // FIXED: Ensure attachments have proper structure
            if (!$ticket->attachments) {
                $ticket->attachments = collect([]);
            }

            Log::info('✅ Ticket details retrieved successfully', [
                'ticket_id' => $ticket->id,
                'response_count' => $ticket->responses->count(),
                'attachment_count' => $ticket->attachments->count(),
                'has_category' => !!$ticket->category,
                'category_name' => $ticket->category->name ?? 'N/A',
                'user_role' => $user->role
            ]);

            return $this->successResponse([
                'ticket' => $ticket,
                'permissions' => $this->getTicketPermissions($user, $ticket),
            ], 'Ticket details retrieved successfully');

        } catch (\Exception $e) {
            Log::error('❌ Ticket details fetch failed', [
                'ticket_id' => $ticket->id ?? 'unknown',
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->handleException($e, 'Ticket details fetch');
        }
    }

    /**
     * Update ticket (staff only)
     */
    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        try {
            Log::info('=== UPDATING TICKET ===', [
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
            ]);

            $user = $request->user();

            if (!$this->canModifyTicket($user, $ticket)) {
                return $this->errorResponse('You do not have permission to modify this ticket', 403);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'sometimes|in:Open,In Progress,Resolved,Closed',
                'priority' => 'sometimes|in:Low,Medium,High,Urgent',
                'crisis_flag' => 'sometimes|boolean',
                'tags' => 'sometimes|array|max:10',
                'tags.*' => 'string|max:50',
                'subject' => 'sometimes|string|min:5|max:255',
                'description' => 'sometimes|string|min:20|max:5000',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            DB::beginTransaction();

            try {
                $updateData = $request->only(['status', 'priority', 'crisis_flag', 'tags', 'subject', 'description']);
                
                // Recalculate priority score if priority changed
                if ($request->has('priority')) {
                    $ticket->priority = $request->priority;
                    $ticket->calculatePriorityScore();
                    $updateData['priority_score'] = $ticket->priority_score;
                }

                $ticket->update($updateData);

                DB::commit();

                $ticket->load([
                    'user:id,name,email,role',
                    'assignedTo:id,name,email,role',
                    'category:id,name,slug,color,icon'
                ]);

                Log::info('✅ Ticket updated successfully', [
                    'ticket_id' => $ticket->id,
                    'changes' => array_keys($updateData),
                ]);

                return $this->successResponse([
                    'ticket' => $ticket
                ], 'Ticket updated successfully');

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Ticket update failed', [
                'ticket_id' => $ticket->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return $this->handleException($e, 'Ticket update');
        }
    }

    /**
     * Assign ticket to staff (admin only)
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        try {
            Log::info('=== ASSIGNING TICKET ===', [
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
            ]);

            if (!$request->user()->isAdmin()) {
                return $this->errorResponse('Only administrators can assign tickets', 403);
            }

            $validator = Validator::make($request->all(), [
                'assigned_to' => 'nullable|exists:users,id',
                'reason' => 'sometimes|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            DB::beginTransaction();

            try {
                if ($request->assigned_to) {
                    // Validate staff member can handle this category
                    $assignee = User::find($request->assigned_to);
                    if (!$assignee->isStaff()) {
                        return $this->errorResponse('Can only assign tickets to staff members', 422);
                    }

                    // Check if counselor specializes in this category
                    if ($assignee->isCounselor() || $assignee->isAdvisor()) {
                        $hasSpecialization = $assignee->counselorSpecializations()
                            ->where('category_id', $ticket->category_id)
                            ->where('is_available', true)
                            ->exists();
                        
                        if (!$hasSpecialization && !$assignee->isAdmin()) {
                            return $this->errorResponse('This staff member does not specialize in the selected category', 422);
                        }
                    }

                    $ticket->assignTo(
                        $request->assigned_to, 
                        'manual', 
                        $request->get('reason', 'Manually assigned by admin')
                    );
                } else {
                    $ticket->unassign($request->get('reason', 'Unassigned by admin'));
                }

                DB::commit();

                $ticket->load([
                    'user:id,name,email,role',
                    'assignedTo:id,name,email,role',
                    'category:id,name,slug,color,icon'
                ]);

                Log::info('✅ Ticket assignment updated successfully', [
                    'ticket_id' => $ticket->id,
                    'assigned_to' => $request->assigned_to,
                ]);

                return $this->successResponse([
                    'ticket' => $ticket
                ], 'Ticket assignment updated successfully');

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Ticket assignment failed', [
                'ticket_id' => $ticket->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return $this->handleException($e, 'Ticket assignment');
        }
    }

    /**
     * Add response to ticket
     */
    public function addResponse(Request $request, Ticket $ticket): JsonResponse
    {
        try {
            Log::info('=== ADDING TICKET RESPONSE ===', [
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
            ]);

            $user = $request->user();

            if (!$this->canRespondToTicket($user, $ticket)) {
                return $this->errorResponse('You do not have permission to respond to this ticket', 403);
            }

            $validator = Validator::make($request->all(), [
                'message' => 'required|string|min:5|max:5000',
                'is_internal' => 'sometimes|boolean',
                'visibility' => 'sometimes|in:all,counselors,admins',
                'is_urgent' => 'sometimes|boolean',
                'attachments' => 'sometimes|array|max:5',
                'attachments.*' => 'file|max:10240|mimes:pdf,doc,docx,txt,jpg,jpeg,png,gif',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            // Students cannot create internal responses
            $isInternal = $request->get('is_internal', false) && $user->isStaff();

            DB::beginTransaction();

            try {
                $response = TicketResponse::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'message' => trim($request->message),
                    'is_internal' => $isInternal,
                    'visibility' => $request->get('visibility', 'all'),
                    'is_urgent' => $request->get('is_urgent', false) && $user->isStaff(),
                ]);

                // Handle attachments
                if ($request->hasFile('attachments')) {
                    foreach ($request->file('attachments') as $file) {
                        $this->storeResponseAttachment($response, $file);
                    }
                }

                // Update ticket status if needed
                if ($ticket->status === 'Open' && $user->isStaff()) {
                    $ticket->update(['status' => 'In Progress']);
                }

                DB::commit();

                $response->load(['user:id,name,email,role', 'attachments']);

                Log::info('✅ Response added successfully', [
                    'ticket_id' => $ticket->id,
                    'response_id' => $response->id,
                    'is_internal' => $isInternal,
                ]);

                return $this->successResponse([
                    'response' => $response,
                    'ticket_status' => $ticket->fresh()->status,
                ], 'Response added successfully', 201);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Response creation failed', [
                'ticket_id' => $ticket->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return $this->handleException($e, 'Response creation');
        }
    }

    /**
     * Manage ticket tags
     */
    public function manageTags(Request $request, Ticket $ticket): JsonResponse
    {
        try {
            Log::info('=== MANAGING TICKET TAGS ===', [
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
            ]);

            if (!$request->user()->isStaff()) {
                return $this->errorResponse('Only staff can manage ticket tags', 403);
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:add,remove,set',
                'tags' => 'required|array',
                'tags.*' => 'string|max:50',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            DB::beginTransaction();

            try {
                switch ($request->action) {
                    case 'add':
                        foreach ($request->tags as $tag) {
                            $ticket->addTag($tag);
                        }
                        break;

                    case 'remove':
                        foreach ($request->tags as $tag) {
                            $ticket->removeTag($tag);
                        }
                        break;

                    case 'set':
                        $ticket->update(['tags' => $request->tags]);
                        break;
                }

                DB::commit();

                Log::info('✅ Ticket tags managed successfully', [
                    'ticket_id' => $ticket->id,
                    'action' => $request->action,
                    'tag_count' => count($request->tags),
                ]);

                return $this->successResponse([
                    'ticket' => $ticket->fresh(['category']),
                    'action' => $request->action,
                ], 'Tags updated successfully');

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Tag management failed', [
                'ticket_id' => $ticket->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return $this->handleException($e, 'Tag management');
        }
    }

    /**
     * Download attachment
     */
    public function downloadAttachment(Request $request, TicketAttachment $attachment): JsonResponse
    {
        try {
            Log::info('=== DOWNLOADING ATTACHMENT ===', [
                'attachment_id' => $attachment->id,
                'user_id' => $request->user()->id,
            ]);

            $user = $request->user();
            $ticket = $attachment->ticket;

            if (!$this->canViewTicket($user, $ticket)) {
                return $this->errorResponse('You do not have permission to download this attachment', 403);
            }

            // Check if file exists
            if (!Storage::disk('private')->exists($attachment->file_path)) {
                Log::warning('File not found for download', [
                    'attachment_id' => $attachment->id,
                    'file_path' => $attachment->file_path,
                ]);
                return $this->errorResponse('File not found', 404);
            }

            $filePath = Storage::disk('private')->path($attachment->file_path);
            $headers = [
                'Content-Type' => $attachment->file_type,
                'Content-Disposition' => 'attachment; filename="' . $attachment->original_name . '"',
                'Content-Length' => $attachment->file_size,
            ];

            Log::info('✅ Attachment download initiated', [
                'attachment_id' => $attachment->id,
                'original_name' => $attachment->original_name,
            ]);

            return response()->download($filePath, $attachment->original_name, $headers);

        } catch (Exception $e) {
            Log::error('❌ Attachment download failed', [
                'attachment_id' => $attachment->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Download failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete ticket (admin only)
     */
    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        try {
            Log::info('=== DELETING TICKET ===', [
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
            ]);

            if (!$request->user()->isAdmin()) {
                return $this->errorResponse('Only administrators can delete tickets', 403);
            }

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|min:10|max:500',
                'notify_user' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $ticketNumber = $ticket->ticket_number;
            $ticketId = $ticket->id;

            DB::beginTransaction();

            try {
                // Delete attachments from storage
                foreach ($ticket->attachments as $attachment) {
                    Storage::disk('private')->delete($attachment->file_path);
                }

                // Delete ticket (cascade will handle related records)
                $ticket->delete();

                DB::commit();

                Log::info('✅ Ticket deleted successfully', [
                    'ticket_id' => $ticketId,
                    'ticket_number' => $ticketNumber,
                ]);

                return $this->deleteSuccessResponse('Ticket', $ticketNumber);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Ticket deletion failed', [
                'ticket_id' => $ticket->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return $this->handleException($e, 'Ticket deletion');
        }
    }

    /**
     * Private helper methods
     */
    private function canViewTicket(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin()) return true;
        if ($user->isStudent()) return $ticket->user_id === $user->id;
        if ($user->isStaff()) return $ticket->assigned_to === $user->id;
        return false;
    }

    private function canModifyTicket(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin()) return true;
        if ($user->isStudent()) return $ticket->user_id === $user->id && $ticket->isOpen();
        if ($user->isStaff()) return $ticket->assigned_to === $user->id;
        return false;
    }

    private function canRespondToTicket(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin()) return true;
        if ($user->isStudent()) return $ticket->user_id === $user->id && !in_array($ticket->status, ['Closed']);
        if ($user->isStaff()) return $ticket->assigned_to === $user->id;
        return false;
    }

    private function getUserPermissions(User $user): array
    {
        return [
            'can_create' => $user->isStudent() || $user->isAdmin(),
            'can_view_all' => $user->isAdmin(),
            'can_assign' => $user->isAdmin(),
            'can_modify' => $user->isStaff(),
            'can_delete' => $user->isAdmin(),
            'can_add_internal_notes' => $user->isStaff(),
            'can_manage_tags' => $user->isStaff(),
            'can_download_attachments' => true,
        ];
    }

    /**
     * FIXED: Enhanced permission checking for tickets
     */
    private function getTicketPermissions(User $user, Ticket $ticket): array
    {
        try {
            return [
                'can_view' => $user->can('view', $ticket),
                'can_modify' => $user->can('update', $ticket),
                'can_respond' => $user->can('addResponse', $ticket),
                'can_assign' => $user->can('assign', $ticket),
                'can_delete' => $user->can('delete', $ticket),
                'can_download_attachments' => $user->can('downloadAttachment', $ticket),
                'can_add_internal_notes' => $user->isStaff() && ($user->role === 'admin' || $ticket->assigned_to === $user->id),
                'can_manage_tags' => $user->can('manageTags', $ticket),
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to get ticket permissions', [
                'user_id' => $user->id,
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage()
            ]);
            
            // Safe defaults based on user role
            $isOwner = $ticket->user_id === $user->id;
            $isAssigned = $ticket->assigned_to === $user->id;
            
            return [
                'can_view' => $user->role === 'admin' || $isOwner || $isAssigned,
                'can_modify' => $user->role === 'admin' || $isAssigned,
                'can_respond' => $user->role === 'admin' || $isOwner || $isAssigned,
                'can_assign' => $user->role === 'admin',
                'can_delete' => $user->role === 'admin',
                'can_download_attachments' => true,
                'can_add_internal_notes' => $user->isStaff(),
                'can_manage_tags' => $user->isStaff(),
            ];
        }
    }

    

    private function getAvailableStaff(): array
    {
        return User::whereIn('role', ['counselor', 'advisor', 'admin'])
            ->where('status', 'active')
            ->get(['id', 'name', 'email', 'role'])
            ->toArray();
    }

    private function storeAttachment(Ticket $ticket, $file): TicketAttachment
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileName = $ticket->id . '_' . time() . '_' . uniqid() . '.' . $extension;
        $filePath = $file->storeAs('ticket-attachments', $fileName, 'private');

        return TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'original_name' => $originalName,
            'file_path' => $filePath,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);
    }

    private function storeResponseAttachment(TicketResponse $response, $file): TicketAttachment
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileName = $response->ticket_id . '_response_' . $response->id . '_' . time() . '_' . uniqid() . '.' . $extension;
        $filePath = $file->storeAs('ticket-attachments', $fileName, 'private');

        return TicketAttachment::create([
            'ticket_id' => $response->ticket_id,
            'response_id' => $response->id,
            'original_name' => $originalName,
            'file_path' => $filePath,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);
    }
}