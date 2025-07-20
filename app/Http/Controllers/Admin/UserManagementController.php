<?php
// app/Http/Controllers/Admin/UserManagementController.php (ENHANCED WITH EMAIL INTEGRATION)

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use App\Jobs\SendWelcomeEmail;
use App\Jobs\SendBulkWelcomeEmails;
use App\Mail\PasswordResetNotification;
use App\Mail\StatusChangeNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Exception;

class UserManagementController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all users with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $this->logRequestDetails('User Management Index');

        try {
            Log::info('=== FETCHING USERS ===', [
                'user_id' => $request->user()?->id,
                'filters' => $request->only(['role', 'status', 'search', 'sort_by', 'sort_direction'])
            ]);

            $validator = Validator::make($request->all(), [
                'role' => 'sometimes|in:student,counselor,advisor,admin,all',
                'status' => 'sometimes|in:active,inactive,suspended,all',
                'search' => 'sometimes|string|max:255',
                'sort_by' => 'sometimes|in:name,email,role,status,created_at,last_login_at',
                'sort_direction' => 'sometimes|in:asc,desc',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'page' => 'sometimes|integer|min:1',
            ], [
                'role.in' => 'Invalid role filter',
                'status.in' => 'Invalid status filter',
                'search.max' => 'Search term cannot exceed 255 characters',
                'sort_by.in' => 'Invalid sort field',
                'sort_direction.in' => 'Sort direction must be asc or desc',
                'per_page.min' => 'Items per page must be at least 1',
                'per_page.max' => 'Items per page cannot exceed 100',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ User index validation failed', [
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check your search criteria');
            }

            $query = User::query();

            // Apply filters
            if ($request->has('role') && $request->role !== 'all') {
                $query->where('role', $request->role);
                Log::info('Applied role filter', ['role' => $request->role]);
            }

            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
                Log::info('Applied status filter', ['status' => $request->status]);
            }

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%")
                      ->orWhere('student_id', 'LIKE', "%{$search}%")
                      ->orWhere('employee_id', 'LIKE', "%{$search}%");
                });
                Log::info('Applied search filter', ['search' => $search]);
            }

            // Sort by
            $sortField = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $users = $query->paginate($perPage);

            // Get user statistics
            $stats = $this->getUserStatistics();

            Log::info('✅ Users fetched successfully', [
                'total' => $users->total(),
                'current_page' => $users->currentPage(),
            ]);

            return $this->successResponse([
                'users' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                    'has_more_pages' => $users->hasMorePages(),
                ],
                'stats' => $stats
            ], 'Users retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Users fetch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'Users fetch');
        }
    }

    /**
     * Create new user with optional welcome email
     */
    public function store(Request $request): JsonResponse
    {
        $this->logRequestDetails('User Creation');

        try {
            Log::info('=== CREATING USER ===', [
                'admin_user_id' => $request->user()?->id,
                'role' => $request->role,
                'send_welcome_email' => $request->boolean('send_welcome_email', true)
            ]);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'sometimes|string|min:8|confirmed',
                'role' => 'required|in:student,counselor,advisor,admin',
                'status' => 'sometimes|in:active,inactive,suspended',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'date_of_birth' => 'nullable|date|before:today',
                'student_id' => 'nullable|string|unique:users,student_id',
                'employee_id' => 'nullable|string|unique:users,employee_id',
                'specializations' => 'nullable|array',
                'specializations.*' => 'string|max:100',
                'bio' => 'nullable|string|max:1000',
                'send_welcome_email' => 'sometimes|boolean',
                'generate_password' => 'sometimes|boolean',
            ], [
                'email.unique' => 'This email address is already registered.',
                'student_id.unique' => 'This student ID is already in use.',
                'employee_id.unique' => 'This employee ID is already in use.',
                'password.confirmed' => 'Password confirmation does not match.',
                'password.min' => 'Password must be at least 8 characters long.',
                'name.required' => 'Full name is required.',
                'email.required' => 'Email address is required.',
                'email.email' => 'Please enter a valid email address.',
                'role.required' => 'User role is required.',
                'date_of_birth.before' => 'Date of birth must be in the past.',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ User creation validation failed', [
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check the user information and try again');
            }

            DB::beginTransaction();

            try {
                // Generate password if not provided or if generate_password is true
                $generatePassword = $request->boolean('generate_password', false) || !$request->filled('password');
                $temporaryPassword = null;
                
                if ($generatePassword) {
                    $temporaryPassword = $this->generateRandomPassword();
                    $password = Hash::make($temporaryPassword);
                } else {
                    $password = Hash::make($request->password);
                }

                $userData = [
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => $password,
                    'role' => $request->role,
                    'status' => $request->get('status', User::STATUS_ACTIVE),
                    'email_verified_at' => now(), // Auto-verify for admin created users
                ];

                // Add optional fields only if they're provided and not empty
                $optionalFields = ['phone', 'address', 'date_of_birth', 'student_id', 'employee_id', 'specializations', 'bio'];
                foreach ($optionalFields as $field) {
                    if ($request->filled($field)) {
                        $userData[$field] = $request->$field;
                    }
                }

                $user = User::create($userData);

                // Handle welcome email based on configuration and request
                $sendWelcomeEmail = $this->shouldSendWelcomeEmail($request);
                $emailQueued = false;

                if ($sendWelcomeEmail && $temporaryPassword) {
                    try {
                        Log::info('🚀 Dispatching welcome email job', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'queue_connection' => config('queue.default')
                        ]);

                        // Dispatch the job
                        SendWelcomeEmail::dispatch($user, $temporaryPassword);
                        $emailQueued = true;
                        
                        Log::info('✅ Welcome email job dispatched successfully', [
                            'user_id' => $user->id,
                            'email' => $user->email
                        ]);

                        // CRITICAL FIX: Create notification for admin
                        if ($request->user()) {
                            $request->user()->createNotification(
                                'system',
                                'User Created Successfully',
                                "User {$user->name} ({$user->email}) was created and welcome email was sent.",
                                'medium',
                                ['user_id' => $user->id, 'email_queued' => true]
                            );
                        }

                    } catch (Exception $e) {
                        Log::error('❌ Failed to dispatch welcome email job', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'error' => $e->getMessage(),
                            'queue_connection' => config('queue.default')
                        ]);

                        // Create notification about email failure
                        if ($request->user()) {
                            $request->user()->createNotification(
                                'system',
                                'Email Job Failed',
                                "User {$user->name} was created but welcome email failed to queue: {$e->getMessage()}",
                                'high',
                                ['user_id' => $user->id, 'email_error' => $e->getMessage()]
                            );
                        }
                        
                        // Continue with user creation even if email fails
                    }
                } else {
                    // Create notification for successful creation without email
                    if ($request->user()) {
                        $request->user()->createNotification(
                            'system',
                            'User Created Successfully', 
                            "User {$user->name} ({$user->email}) was created successfully. No welcome email was sent.",
                            'medium',
                            ['user_id' => $user->id, 'email_queued' => false]
                        );
                    }
                }

                DB::commit();

                Log::info('✅ User created successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'welcome_email_queued' => $emailQueued
                ]);

                $responseData = [
                    'user' => $user->fresh(),
                    'email_sent' => $emailQueued,
                ];

                // Include temporary password in response for admin (in production, this should be sent via email only)
                if ($temporaryPassword && config('app.debug')) {
                    $responseData['temporary_password'] = $temporaryPassword;
                    $responseData['password_note'] = 'This temporary password is shown for development purposes only. In production, it will only be sent via email.';
                }

                return $this->successResponse($responseData, 'User created successfully', 201);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('❌ User creation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'User creation');
        }
    }

    /**
     * Get single user
     */
    public function show(User $user): JsonResponse
    {
        $this->logRequestDetails('User Show');

        try {
            Log::info('=== FETCHING SINGLE USER ===', [
                'user_id' => $user->id,
                'user_role' => $user->role
            ]);

            return $this->successResponse([
                'user' => $user
            ], 'User retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ User fetch failed', [
                'user_id' => $user->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            
            return $this->handleException($e, 'User fetch');
        }
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->logRequestDetails('User Update');

        try {
            Log::info('=== UPDATING USER ===', [
                'user_id' => $user->id,
                'admin_user_id' => $request->user()?->id,
                'fields_to_update' => array_keys($request->all())
            ]);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => [
                    'sometimes',
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique('users')->ignore($user->id),
                ],
                'password' => 'sometimes|nullable|string|min:8|confirmed',
                'role' => 'sometimes|required|in:student,counselor,advisor,admin',
                'status' => 'sometimes|required|in:active,inactive,suspended',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'date_of_birth' => 'nullable|date|before:today',
                'student_id' => [
                    'nullable',
                    'string',
                    Rule::unique('users')->ignore($user->id),
                ],
                'employee_id' => [
                    'nullable',
                    'string',
                    Rule::unique('users')->ignore($user->id),
                ],
                'specializations' => 'nullable|array',
                'specializations.*' => 'string|max:100',
                'bio' => 'nullable|string|max:1000',
            ], [
                'email.unique' => 'This email address is already registered.',
                'student_id.unique' => 'This student ID is already in use.',
                'employee_id.unique' => 'This employee ID is already in use.',
                'password.min' => 'Password must be at least 8 characters long.',
                'password.confirmed' => 'Password confirmation does not match.',
                'date_of_birth.before' => 'Date of birth must be in the past.',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ User update validation failed', [
                    'user_id' => $user->id,
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check the user information and try again');
            }

            DB::beginTransaction();

            try {
                $updateData = $request->only([
                    'name', 'email', 'role', 'status', 'phone', 'address',
                    'date_of_birth', 'student_id', 'employee_id', 'specializations', 'bio'
                ]);

                // Only update password if provided
                if ($request->filled('password')) {
                    $updateData['password'] = Hash::make($request->password);
                    Log::info('Password will be updated for user', ['user_id' => $user->id]);
                }

                $user->update($updateData);

                DB::commit();

                Log::info('✅ User updated successfully', [
                    'user_id' => $user->id,
                    'updated_fields' => array_keys($updateData)
                ]);

                return $this->successResponse([
                    'user' => $user->fresh()
                ], 'User updated successfully');

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('❌ User update failed', [
                'user_id' => $user->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'User update');
        }
    }

    /**
     * Delete user
     */
    public function destroy(User $user): JsonResponse
    {
        $this->logRequestDetails('User Deletion');

        try {
            Log::info('=== DELETING USER ===', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'admin_user_id' => request()->user()?->id
            ]);

            // Prevent admin from deleting themselves
            if ($user->id === auth()->id()) {
                Log::warning('❌ Admin attempted to delete own account', [
                    'user_id' => $user->id
                ]);
                return $this->errorResponse('You cannot delete your own account', 403);
            }

            DB::beginTransaction();

            try {
                $userName = $user->name;
                $userEmail = $user->email;

                // Soft delete the user
                $user->delete();

                DB::commit();

                Log::info('✅ User deleted successfully', [
                    'deleted_user_id' => $user->id,
                    'deleted_user_name' => $userName,
                    'deleted_user_email' => $userEmail
                ]);

                return $this->successResponse(
                    [],
                    "User '{$userName}' has been deleted successfully"
                );

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('❌ User deletion failed', [
                'user_id' => $user->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'User deletion');
        }
    }

    /**
     * Enhanced bulk actions with status change notifications
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $this->logRequestDetails('Bulk User Action');

        try {
            Log::info('=== BULK USER ACTION ===', [
                'action' => $request->action,
                'user_count' => count($request->user_ids ?? []),
                'admin_user_id' => $request->user()?->id,
                'notify_users' => $request->boolean('notify_users', true)
            ]);

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:activate,deactivate,suspend,delete',
                'user_ids' => 'required|array|min:1|max:100',
                'user_ids.*' => 'exists:users,id',
                'reason' => 'sometimes|string|max:500',
                'notify_users' => 'sometimes|boolean',
            ], [
                'action.required' => 'Action is required',
                'action.in' => 'Invalid action specified',
                'user_ids.required' => 'Please select users to perform action on',
                'user_ids.min' => 'Please select at least one user',
                'user_ids.max' => 'Cannot perform bulk action on more than 100 users at once',
                'user_ids.*.exists' => 'One or more selected users do not exist',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Bulk action validation failed', [
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check your selection and try again');
            }

            $userIds = $request->user_ids;
            $action = $request->action;
            $reason = $request->get('reason', '');
            $notifyUsers = $request->boolean('notify_users', true);

            // Prevent admin from performing bulk actions on themselves
            if (in_array(auth()->id(), $userIds)) {
                Log::warning('❌ Admin attempted bulk action on own account', [
                    'action' => $action,
                    'admin_id' => auth()->id()
                ]);
                return $this->errorResponse('You cannot perform bulk actions on your own account', 403);
            }

            DB::beginTransaction();

            try {
                $users = User::whereIn('id', $userIds)->get();
                $affectedCount = 0;
                $emailsSent = 0;
                $emailsQueued = 0;
                $statusChanges = [];

                foreach ($users as $user) {
                    $oldStatus = $user->status;
                    $updated = false;

                    switch ($action) {
                        case 'activate':
                            if ($user->status !== User::STATUS_ACTIVE) {
                                $user->update(['status' => User::STATUS_ACTIVE]);
                                $statusChanges[] = ['user' => $user, 'old_status' => $oldStatus, 'new_status' => User::STATUS_ACTIVE];
                                $updated = true;
                            }
                            break;
                        case 'deactivate':
                            if ($user->status !== User::STATUS_INACTIVE) {
                                $user->update(['status' => User::STATUS_INACTIVE]);
                                $statusChanges[] = ['user' => $user, 'old_status' => $oldStatus, 'new_status' => User::STATUS_INACTIVE];
                                $updated = true;
                            }
                            break;
                        case 'suspend':
                            if ($user->status !== User::STATUS_SUSPENDED) {
                                $user->update(['status' => User::STATUS_SUSPENDED]);
                                $statusChanges[] = ['user' => $user, 'old_status' => $oldStatus, 'new_status' => User::STATUS_SUSPENDED];
                                $updated = true;
                            }
                            break;
                        case 'delete':
                            $user->delete();
                            $updated = true;
                            break;
                    }

                    if ($updated) {
                        $affectedCount++;
                    }
                }

                // CRITICAL FIX: Send status change notifications for non-delete actions using new job
                if ($notifyUsers && $action !== 'delete' && $this->shouldSendStatusChangeEmails()) {
                    foreach ($statusChanges as $change) {
                        try {
                            Log::info('🚀 Dispatching bulk status change email job', [
                                'user_id' => $change['user']->id,
                                'old_status' => $change['old_status'],
                                'new_status' => $change['new_status']
                            ]);

                            \App\Jobs\SendStatusChangeNotification::dispatch(
                                $change['user'], 
                                $change['old_status'], 
                                $change['new_status'], 
                                $request->user(), 
                                $reason ?: "Bulk {$action} operation by administrator"
                            );
                            
                            $emailsQueued++;
                        } catch (Exception $e) {
                            Log::error('❌ Failed to dispatch bulk status change email job', [
                                'user_id' => $change['user']->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }

                $message = match($action) {
                    'activate' => "{$affectedCount} user(s) activated successfully",
                    'deactivate' => "{$affectedCount} user(s) deactivated successfully", 
                    'suspend' => "{$affectedCount} user(s) suspended successfully",
                    'delete' => "{$affectedCount} user(s) deleted successfully",
                };

                if ($emailsSent > 0) {
                    $message .= ". {$emailsSent} notification emails sent.";
                }

                // if ($emailsQueued > 0) {
                //     $message .= ". {$emailsQueued} notification emails queued.";
                // }

                DB::commit();

                Log::info('✅ Bulk action completed successfully', [
                    'action' => $action,
                    'affected_count' => $affectedCount,
                    'emails_sent' => $emailsSent,
                    'reason' => $reason
                ]);

                return $this->successResponse([
                    'affected_count' => $affectedCount,
                    'action' => $action,
                    'emails_sent' => $emailsSent,
                    'status_changes' => count($statusChanges)
                ], $message);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('❌ Bulk action failed', [
                'action' => $request->action ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'Bulk user action');
        }
    }

    /**
     * FIXED: Enhanced bulk create users from CSV or data array with proper FormData handling
     * FIXED: Enhanced bulk create users with proper boolean handling from FormData
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        try {
            Log::info('=== BULK USER CREATION START ===', [
                'admin_user_id' => $request->user()?->id,
                'request_method' => $request->method(),
                'content_type' => $request->header('Content-Type'),
                'input_keys' => array_keys($request->all()),
                'has_users_data' => $request->has('users_data'),
                'users_data_type' => gettype($request->input('users_data')),
            ]);

            // CRITICAL FIX: Handle boolean conversion from FormData strings
            $this->preprocessFormDataBooleans($request);

            // CRITICAL FIX: Handle FormData with JSON string for users_data
            $usersDataRaw = $request->input('users_data');
            $usersData = null;

            // Check if users_data is a JSON string (from FormData) or already an array
            if (is_string($usersDataRaw)) {
                try {
                    $usersData = json_decode($usersDataRaw, true, 512, JSON_THROW_ON_ERROR);
                    Log::info('✅ Successfully decoded users_data from JSON string', [
                        'user_count' => is_array($usersData) ? count($usersData) : 0
                    ]);
                } catch (JsonException $e) {
                    Log::error('❌ Failed to decode users_data JSON string', [
                        'error' => $e->getMessage(),
                        'raw_data_preview' => substr($usersDataRaw, 0, 200)
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid JSON format for users_data',
                        'error' => 'The users_data field must contain valid JSON'
                    ], 422);
                }
            } elseif (is_array($usersDataRaw)) {
                $usersData = $usersDataRaw;
                Log::info('✅ Using users_data as array directly', [
                    'user_count' => count($usersData)
                ]);
            } else {
                Log::error('❌ users_data is neither string nor array', [
                    'type' => gettype($usersDataRaw),
                    'value' => $usersDataRaw
                ]);
            }

            // FIXED: More flexible validation that accepts string booleans from FormData
            $validator = Validator::make($request->all(), [
                'users_data' => 'required', // We'll validate the decoded data separately
                'skip_duplicates' => 'sometimes|in:true,false,1,0,"true","false","1","0"', // Accept string booleans
                'send_welcome_email' => 'sometimes|in:true,false,1,0,"true","false","1","0"', // Accept string booleans
                'generate_passwords' => 'sometimes|in:true,false,1,0,"true","false","1","0"', // Accept string booleans
                'dry_run' => 'sometimes|in:true,false,1,0,"true","false","1","0"', // Accept string booleans
            ], [
                'users_data.required' => 'User data is required',
                'skip_duplicates.in' => 'Skip duplicates must be true or false',
                'send_welcome_email.in' => 'Send welcome email must be true or false',
                'generate_passwords.in' => 'Generate passwords must be true or false',
                'dry_run.in' => 'Dry run must be true or false',
            ]);

            // Additional validation for the decoded users_data
            if (!is_array($usersData) || empty($usersData)) {
                $validator->after(function ($validator) {
                    $validator->errors()->add('users_data', 'User data must be a non-empty array');
                });
            } elseif (count($usersData) > 1000) {
                $validator->after(function ($validator) {
                    $validator->errors()->add('users_data', 'Cannot process more than 1000 users at once');
                });
            } else {
                // Validate each user in the array
                foreach ($usersData as $index => $userData) {
                    if (!is_array($userData)) {
                        $validator->after(function ($validator) use ($index) {
                            $validator->errors()->add("users_data.{$index}", "User data at index {$index} must be an object");
                        });
                        continue;
                    }

                    if (empty($userData['name'])) {
                        $validator->after(function ($validator) use ($index) {
                            $validator->errors()->add("users_data.{$index}.name", "Name is required for user at index {$index}");
                        });
                    }

                    if (empty($userData['email']) || !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                        $validator->after(function ($validator) use ($index) {
                            $validator->errors()->add("users_data.{$index}.email", "Valid email is required for user at index {$index}");
                        });
                    }
                }
            }

            if ($validator->fails()) {
                Log::error('❌ Bulk creation validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'first_error' => $validator->errors()->first(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed: ' . $validator->errors()->first(),
                    'errors' => $validator->errors()->toArray(),
                ], 422);
            }

            // Extract options with proper boolean conversion
            $skipDuplicates = $this->convertToBoolean($request->input('skip_duplicates', 'true'));
            $sendWelcomeEmail = $this->convertToBoolean($request->input('send_welcome_email', 'false'));
            $generatePasswords = $this->convertToBoolean($request->input('generate_passwords', 'true'));
            $dryRun = $this->convertToBoolean($request->input('dry_run', 'false'));

            Log::info('Processing bulk creation with options', [
                'user_count' => count($usersData),
                'skip_duplicates' => $skipDuplicates,
                'send_welcome_email' => $sendWelcomeEmail,
                'generate_passwords' => $generatePasswords,
                'dry_run' => $dryRun,
            ]);

            // Validate and sanitize the user data
            $validatedUsers = $this->validateAndSanitizeUsersData($usersData);
            
            if (empty($validatedUsers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid users found in the provided data',
                    'data' => [
                        'total_processed' => count($usersData),
                        'valid_users' => 0,
                    ]
                ], 400);
            }

            // Process the bulk creation
            $results = $this->processBulkUserCreationFixed(
                $validatedUsers,
                $skipDuplicates,
                $sendWelcomeEmail,
                $generatePasswords,
                $request->user(),
                $dryRun
            );

            Log::info('✅ Bulk creation completed', [
                'results' => $results,
                'dry_run' => $dryRun
            ]);

            $message = $dryRun 
                ? 'Bulk user creation validation completed (dry run)' 
                : 'Bulk user creation completed';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'results' => $results,
                    'summary' => [
                        'total_submitted' => count($usersData),
                        'total_valid' => count($validatedUsers),
                        'successful' => $results['successful'],
                        'failed' => $results['failed'],
                        'skipped' => $results['skipped'],
                        'emails_queued' => $results['emails_queued'] ?? 0,
                        'dry_run' => $dryRun,
                    ]
                ]
            ], $dryRun ? 200 : 201);

        } catch (Exception $e) {
            Log::error('❌ Bulk creation exception', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error during bulk creation',
                'error' => config('app.debug') ? $e->getMessage() : 'Please try again later',
            ], 500);
        }
    }

    /**
     * ENHANCED: Add a method to handle the FormData preparation properly
     */
    protected function prepareForValidation(): void
    {
        // If we have a JSON string in users_data, decode it
        if ($this->has('users_data') && is_string($this->input('users_data'))) {
            try {
                $decodedUsersData = json_decode($this->input('users_data'), true, 512, JSON_THROW_ON_ERROR);
                $this->merge(['users_data' => $decodedUsersData]);
                
                Log::info('✅ Decoded users_data from JSON string in prepareForValidation', [
                    'user_count' => is_array($decodedUsersData) ? count($decodedUsersData) : 0
                ]);
            } catch (JsonException $e) {
                Log::warning('⚠️ Failed to decode users_data in prepareForValidation', [
                    'error' => $e->getMessage()
                ]);
                // Leave as-is, validation will catch the error
            }
        }

        // Convert string booleans to actual booleans
        $booleanFields = ['skip_duplicates', 'send_welcome_email', 'generate_passwords', 'dry_run'];
        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                if (is_string($value)) {
                    $this->merge([$field => filter_var($value, FILTER_VALIDATE_BOOLEAN)]);
                }
            }
        }
    }

    /**
     * Enhanced validation and sanitization with better error handling
     */
    private function validateAndSanitizeUsersData(array $usersData): array
    {
        $validatedUsers = [];
        $seenEmails = [];
        $seenStudentIds = [];
        $seenEmployeeIds = [];
        
        Log::info('Starting user data validation', [
            'total_users' => count($usersData)
        ]);
        
        foreach ($usersData as $index => $userData) {
            try {
                // Ensure we have an array
                if (!is_array($userData)) {
                    Log::warning('Skipping non-array user data', [
                        'index' => $index, 
                        'type' => gettype($userData)
                    ]);
                    continue;
                }

                // Clean and validate each user
                $cleanUser = $this->sanitizeUserData($userData, $index);
                
                if ($cleanUser) {
                    // Check for duplicates within the current batch
                    $email = strtolower($cleanUser['email']);
                    
                    if (isset($seenEmails[$email])) {
                        Log::warning('Duplicate email within batch', [
                            'email' => $email,
                            'current_index' => $index,
                            'first_seen_index' => $seenEmails[$email]
                        ]);
                        continue; // Skip this duplicate
                    }
                    $seenEmails[$email] = $index;
                    
                    // Check student_id duplicates within batch
                    if (!empty($cleanUser['student_id'])) {
                        if (isset($seenStudentIds[$cleanUser['student_id']])) {
                            Log::warning('Duplicate student_id within batch', [
                                'student_id' => $cleanUser['student_id'],
                                'current_index' => $index,
                                'first_seen_index' => $seenStudentIds[$cleanUser['student_id']]
                            ]);
                            continue;
                        }
                        $seenStudentIds[$cleanUser['student_id']] = $index;
                    }
                    
                    // Check employee_id duplicates within batch
                    if (!empty($cleanUser['employee_id'])) {
                        if (isset($seenEmployeeIds[$cleanUser['employee_id']])) {
                            Log::warning('Duplicate employee_id within batch', [
                                'employee_id' => $cleanUser['employee_id'],
                                'current_index' => $index,
                                'first_seen_index' => $seenEmployeeIds[$cleanUser['employee_id']]
                            ]);
                            continue;
                        }
                        $seenEmployeeIds[$cleanUser['employee_id']] = $index;
                    }
                    
                    $validatedUsers[] = $cleanUser;
                }
                
            } catch (Exception $e) {
                Log::warning('Failed to validate user data', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                    'user_data' => $userData
                ]);
                continue;
            }
        }

        Log::info('User data validation completed', [
            'original_count' => count($usersData),
            'validated_count' => count($validatedUsers),
            'duplicate_emails_in_batch' => count($seenEmails) - count($validatedUsers),
        ]);

        return $validatedUsers;
    }

    /**
     * Sanitize individual user data with relaxed validation
     */
    private function sanitizeUserData(array $userData, int $index): ?array
    {
        // Required fields with flexible key names
        $name = $this->extractValue($userData, ['name', 'full_name', 'fullname', 'user_name', 'username']);
        $email = $this->extractValue($userData, ['email', 'email_address', 'emailaddress', 'mail']);
        $role = $this->extractValue($userData, ['role', 'user_role', 'userrole', 'type']);
        $status = $this->extractValue($userData, ['status', 'user_status', 'userstatus', 'state']);

        // Validate required fields
        if (empty($name) || empty($email)) {
            Log::warning('Skipping user with missing required fields', [
                'index' => $index,
                'name' => $name,
                'email' => $email
            ]);
            return null;
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Skipping user with invalid email', [
                'index' => $index,
                'email' => $email
            ]);
            return null;
        }

        // Sanitize and validate role
        $role = $this->sanitizeRole($role);
        if (!$role) {
            Log::warning('Invalid role, defaulting to student', [
                'index' => $index,
                'original_role' => $userData['role'] ?? 'unknown'
            ]);
            $role = 'student'; // Default to student
        }

        // Sanitize and validate status
        $status = $this->sanitizeStatus($status);
        if (!$status) {
            $status = 'active'; // Default to active
        }

        // Optional fields
        $phone = $this->extractValue($userData, ['phone', 'phone_number', 'phonenumber', 'mobile']);
        $studentId = $this->extractValue($userData, ['student_id', 'studentid', 'student_number', 'student_no']);
        $employeeId = $this->extractValue($userData, ['employee_id', 'employeeid', 'employee_number', 'employee_no', 'staff_id']);

        // Clean phone number
        if ($phone) {
            $phone = $this->cleanPhoneNumber($phone);
        }

        return [
            'name' => trim($name),
            'email' => strtolower(trim($email)),
            'role' => $role,
            'status' => $status,
            'phone' => $phone,
            'student_id' => $studentId ? trim($studentId) : null,
            'employee_id' => $employeeId ? trim($employeeId) : null,
        ];
    }

    /**
     * Extract value from array using multiple possible keys
     */
    private function extractValue(array $data, array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            if (isset($data[$key]) && !empty(trim($data[$key]))) {
                return trim($data[$key]);
            }
        }
        return null;
    }

    /**
     * Sanitize role value
     */
    private function sanitizeRole(string $role = null): ?string
    {
        if (!$role) return null;
        
        $role = strtolower(trim($role));
        
        // Map variations to standard roles
        $roleMap = [
            'student' => 'student',
            'students' => 'student',
            'pupil' => 'student',
            'learner' => 'student',
            'counselor' => 'counselor',
            'counsellor' => 'counselor',
            'therapist' => 'counselor',
            'psychologist' => 'counselor',
            'advisor' => 'advisor',
            'adviser' => 'advisor',
            'mentor' => 'advisor',
            'guide' => 'advisor',
            'admin' => 'admin',
            'administrator' => 'admin',
            'manager' => 'admin',
            'supervisor' => 'admin'
        ];

        return $roleMap[$role] ?? null;
    }

    /**
     * Sanitize status value
     */
    private function sanitizeStatus(string $status = null): ?string
    {
        if (!$status) return null;
        
        $status = strtolower(trim($status));
        
        // Map variations to standard statuses
        $statusMap = [
            'active' => 'active',
            'enabled' => 'active',
            'live' => 'active',
            'on' => 'active',
            'yes' => 'active',
            'inactive' => 'inactive',
            'disabled' => 'inactive',
            'off' => 'inactive',
            'no' => 'inactive',
            'suspended' => 'suspended',
            'banned' => 'suspended',
            'blocked' => 'suspended'
        ];

        return $statusMap[$status] ?? null;
    }

    /**
     * Clean phone number
     */
    private function cleanPhoneNumber(string $phone): ?string
    {
        // Remove all non-digit characters except + at the beginning
        $cleaned = preg_replace('/[^\d+]/', '', $phone);
        
        // Ensure + is only at the beginning
        if (strpos($cleaned, '+') === 0) {
            $cleaned = '+' . preg_replace('/[^\d]/', '', substr($cleaned, 1));
        } else {
            $cleaned = preg_replace('/[^\d]/', '', $cleaned);
        }
        
        // Return null if too short or too long
        if (strlen($cleaned) < 10 || strlen($cleaned) > 20) {
            return null;
        }
        
        return $cleaned;
    }

    /**
     * Enhanced CSV processing with better error handling
     */
    private function processCsvFile($file): array
    {
        try {
            $csvData = array_map('str_getcsv', file($file->getRealPath()));
            
            if (empty($csvData)) {
                Log::error('Empty CSV file');
                return [];
            }

            $headers = array_shift($csvData); // Remove header row
            
            if (empty($headers)) {
                Log::error('CSV file has no headers');
                return [];
            }
            
            // Normalize headers (remove spaces, convert to lowercase)
            $headers = array_map(function($header) {
                return strtolower(trim(str_replace([' ', '-', '_'], '_', $header)));
            }, $headers);

            Log::info('CSV headers found', ['headers' => $headers]);

            $users = [];
            
            foreach ($csvData as $rowIndex => $row) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Pad or trim row to match header count
                $row = array_pad(array_slice($row, 0, count($headers)), count($headers), '');
                
                if (count($row) !== count($headers)) {
                    Log::warning('Row column count mismatch', [
                        'row_index' => $rowIndex,
                        'expected' => count($headers),
                        'actual' => count($row)
                    ]);
                    continue;
                }
                
                $userData = array_combine($headers, $row);
                
                if ($userData) {
                    $users[] = $userData;
                }
            }

            Log::info('CSV file processed', [
                'total_rows' => count($csvData),
                'valid_users' => count($users),
                'headers' => $headers
            ]);

            return $users;
        } catch (Exception $e) {
            Log::error('CSV processing failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return [];
        }
    }

    /**
     * Get enhanced user statistics including email settings
     */
    public function getStats(): JsonResponse
    {
        $this->logRequestDetails('User Statistics');

        try {
            Log::info('=== FETCHING USER STATISTICS ===');

            $stats = $this->getUserStatistics();

            Log::info('✅ User statistics retrieved successfully', [
                'total_users' => $stats['total_users']
            ]);

            return $this->successResponse([
                'stats' => $stats
            ], 'User statistics retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ User statistics fetch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'User statistics fetch');
        }
    }

    /**
     * Reset user password with enhanced email notification
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->logRequestDetails('Password Reset');

        try {
            Log::info('=== RESETTING USER PASSWORD ===', [
                'target_user_id' => $user->id,
                'admin_user_id' => $request->user()?->id,
                'notify_user' => $request->boolean('notify_user', true),
                'generate_password' => $request->boolean('generate_password', true)
            ]);

            $validator = Validator::make($request->all(), [
                'new_password' => 'sometimes|string|min:8|confirmed',
                'generate_password' => 'sometimes|boolean',
                'notify_user' => 'sometimes|boolean',
                'reset_reason' => 'sometimes|string|max:500',
            ], [
                'new_password.min' => 'New password must be at least 8 characters long.',
                'new_password.confirmed' => 'Password confirmation does not match.',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Password reset validation failed', [
                    'user_id' => $user->id,
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check the password requirements');
            }

            DB::beginTransaction();

            try {
                $generatePassword = $request->boolean('generate_password', true);
                $notifyUser = $request->boolean('notify_user', true);
                $resetReason = $request->get('reset_reason', 'Administrative password reset');
                $temporaryPassword = null;

                if ($generatePassword || !$request->filled('new_password')) {
                    $temporaryPassword = $this->generateRandomPassword();
                    $password = Hash::make($temporaryPassword);
                } else {
                    $password = Hash::make($request->new_password);
                    $temporaryPassword = $request->new_password; // For email notification
                }

                $user->update([
                    'password' => $password,
                    'password_reset_email_sent_at' => $notifyUser ? now() : null,
                    'last_email_sent_at' => $notifyUser ? now() : null,
                ]);

                // Revoke all tokens to force re-login
                $user->tokens()->delete();

                // CRITICAL FIX: Dispatch password reset email job
                $emailQueued = false;
                if ($notifyUser && $temporaryPassword && $this->shouldSendPasswordResetEmails()) {
                    try {
                        Log::info('🚀 Dispatching password reset email job', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'admin_user_id' => $request->user()?->id
                        ]);

                        // Dispatch the new password reset job
                        \App\Jobs\SendPasswordResetNotification::dispatch(
                            $user, 
                            $temporaryPassword, 
                            $request->user(), 
                            $resetReason
                        );
                        
                        $emailQueued = true;
                        
                        Log::info('✅ Password reset email job dispatched successfully', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'admin_user_id' => $request->user()?->id
                        ]);
                    } catch (Exception $e) {
                        Log::error('❌ Failed to dispatch password reset email job', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                DB::commit();

                Log::info('✅ Password reset successfully', [
                    'user_id' => $user->id,
                    'tokens_revoked' => true,
                    'email_queued' => $emailQueued,
                    'reset_reason' => $resetReason
                ]);

                $responseData = [
                    'tokens_revoked' => true,
                    'email_queued' => $emailQueued,
                ];

                // Include temporary password in response for admin (in development only)
                if ($temporaryPassword && config('app.debug')) {
                    $responseData['temporary_password'] = $temporaryPassword;
                    $responseData['password_note'] = 'This temporary password is shown for development purposes only. In production, it will only be sent via email.';
                }

                return $this->successResponse(
                    $responseData,
                    'Password reset successfully. User will need to log in again.'
                );

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('❌ Password reset failed', [
                'user_id' => $user->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'Password reset');
        }
    }

    /**
     * Toggle user status with enhanced email notification
     */
    public function toggleStatus(User $user): JsonResponse
    {
        $this->logRequestDetails('Status Toggle');

        try {
            Log::info('=== TOGGLING USER STATUS ===', [
                'user_id' => $user->id,
                'current_status' => $user->status,
                'admin_user_id' => request()->user()?->id
            ]);

            // Prevent admin from toggling their own status
            if ($user->id === auth()->id()) {
                Log::warning('❌ Admin attempted to toggle own status', [
                    'user_id' => $user->id
                ]);
                return $this->errorResponse('You cannot change your own status', 403);
            }

            DB::beginTransaction();

            try {
                $oldStatus = $user->status;
                $newStatus = $user->status === User::STATUS_ACTIVE 
                    ? User::STATUS_INACTIVE 
                    : User::STATUS_ACTIVE;

                $user->update(['status' => $newStatus]);

                // CRITICAL FIX: Dispatch status change email job
                $emailQueued = false;
                if ($this->shouldSendStatusChangeEmails()) {
                    try {
                        Log::info('🚀 Dispatching status change email job', [
                            'user_id' => $user->id,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'admin_user_id' => request()->user()?->id
                        ]);

                        // Dispatch the new status change job
                        \App\Jobs\SendStatusChangeNotification::dispatch(
                            $user, 
                            $oldStatus, 
                            $newStatus, 
                            request()->user(), 
                            'Status changed by administrator'
                        );
                        
                        $emailQueued = true;
                        
                        Log::info('✅ Status change email job dispatched successfully', [
                            'user_id' => $user->id,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus
                        ]);
                    } catch (Exception $e) {
                        Log::error('❌ Failed to dispatch status change email job', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                DB::commit();

                Log::info('✅ User status toggled successfully', [
                    'user_id' => $user->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'email_queued' => $emailQueued
                ]);

                return $this->successResponse([
                    'user' => $user->fresh(),
                    'email_queued' => $emailQueued,
                    'status_change' => [
                        'from' => $oldStatus,
                        'to' => $newStatus
                    ]
                ], "User status changed from {$oldStatus} to {$newStatus}");

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('❌ Status toggle failed', [
                'user_id' => $user->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'Status toggle');
        }
    }

    /**
     * Get available roles, statuses, and email settings
     */
    public function getOptions(): JsonResponse
    {
        try {
            return $this->successResponse([
                'roles' => User::getAvailableRoles(),
                'statuses' => User::getAvailableStatuses(),
                'email_settings' => [
                    'welcome_emails_enabled' => config('app.send_welcome_emails', env('SEND_WELCOME_EMAILS', true)),
                    'bulk_emails_enabled' => config('app.send_bulk_operation_reports', env('SEND_BULK_OPERATION_REPORTS', true)),
                    'admin_notifications_enabled' => config('app.send_admin_notifications', env('SEND_ADMIN_NOTIFICATIONS', true)),
                    'password_reset_emails_enabled' => config('app.send_password_reset_emails', env('SEND_PASSWORD_RESET_EMAILS', true)),
                    'status_change_emails_enabled' => config('app.send_email_on_status_change', env('SEND_EMAIL_ON_STATUS_CHANGE', true)),
                ]
            ], 'User options retrieved successfully');
        } catch (Exception $e) {
            return $this->handleException($e, 'Options fetch');
        }
    }

    /**
     * Export users data
     */
    public function export(Request $request): JsonResponse
    {
        $this->logRequestDetails('User Export');

        try {
            Log::info('=== EXPORTING USERS ===', [
                'admin_user_id' => $request->user()?->id,
                'filters' => $request->only(['role', 'status', 'search'])
            ]);

            $query = User::query();

            // Apply same filters as index
            if ($request->has('role') && $request->role !== 'all') {
                $query->where('role', $request->role);
            }

            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%")
                      ->orWhere('student_id', 'LIKE', "%{$search}%")
                      ->orWhere('employee_id', 'LIKE', "%{$search}%");
                });
            }

            $users = $query->get([
                'id', 'name', 'email', 'role', 'status', 'phone', 
                'student_id', 'employee_id', 'created_at', 'last_login_at'
            ]);

            Log::info('✅ Users exported successfully', [
                'exported_count' => $users->count()
            ]);

            return $this->successResponse([
                'users' => $users,
                'exported_at' => now()->toISOString(),
                'total_exported' => $users->count()
            ], 'Users data exported successfully');

        } catch (Exception $e) {
            Log::error('❌ User export failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'User export');
        }
    }

    // PRIVATE HELPER METHODS

    /**
     * ADDED: Helper method to preprocess FormData boolean strings
     */
    private function preprocessFormDataBooleans(Request $request): void
    {
        $booleanFields = ['skip_duplicates', 'send_welcome_email', 'generate_passwords', 'dry_run'];
        
        foreach ($booleanFields as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                $booleanValue = $this->convertToBoolean($value);
                $request->merge([$field => $booleanValue]);
                
                Log::info("Converted {$field} from '{$value}' to " . ($booleanValue ? 'true' : 'false'));
            }
        }
    }

    /**
     * ADDED: Helper method to convert string booleans to actual booleans
     */
    private function convertToBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['true', '1', 'yes', 'on'], true);
        }
        
        if (is_numeric($value)) {
            return (bool) $value;
        }
        
        return false;
    }

    /**
     * Get comprehensive user statistics with email settings
     */
    private function getUserStatistics(): array
    {
        try {
            return [
                'total_users' => User::count(),
                'active_users' => User::where('status', User::STATUS_ACTIVE)->count(),
                'inactive_users' => User::where('status', User::STATUS_INACTIVE)->count(),
                'suspended_users' => User::where('status', User::STATUS_SUSPENDED)->count(),
                'students' => User::where('role', User::ROLE_STUDENT)->count(),
                'counselors' => User::where('role', User::ROLE_COUNSELOR)->count(),
                'advisors' => User::where('role', User::ROLE_ADVISOR)->count(),
                'admins' => User::where('role', User::ROLE_ADMIN)->count(),
                'recent_registrations' => User::where('created_at', '>=', now()->subDays(7))->count(),
                'recent_logins' => User::where('last_login_at', '>=', now()->subDays(7))->count(),
                'never_logged_in' => User::whereNull('last_login_at')->count(),
                'this_month_registrations' => User::where('created_at', '>=', now()->startOfMonth())->count(),
                'email_settings' => [
                    'welcome_emails_enabled' => config('app.send_welcome_emails', env('SEND_WELCOME_EMAILS', true)),
                    'bulk_emails_enabled' => config('app.send_bulk_operation_reports', env('SEND_BULK_OPERATION_REPORTS', true)),
                    'admin_notifications_enabled' => config('app.send_admin_notifications', env('SEND_ADMIN_NOTIFICATIONS', true)),
                    'password_reset_emails_enabled' => config('app.send_password_reset_emails', env('SEND_PASSWORD_RESET_EMAILS', true)),
                    'status_change_emails_enabled' => config('app.send_email_on_status_change', env('SEND_EMAIL_ON_STATUS_CHANGE', true)),
                ],
                'email_stats' => [
                    'users_with_welcome_emails' => User::whereNotNull('welcome_email_sent_at')->count(),
                    'users_with_password_resets' => User::whereNotNull('password_reset_email_sent_at')->count(),
                    'total_emails_sent' => User::sum('total_emails_sent') ?: 0,
                ]
            ];
        } catch (Exception $e) {
            Log::error('Failed to get user statistics', [
                'error' => $e->getMessage()
            ]);
            // Return default stats on error
            return [
                'total_users' => 0,
                'active_users' => 0,
                'inactive_users' => 0,
                'suspended_users' => 0,
                'students' => 0,
                'counselors' => 0,
                'advisors' => 0,
                'admins' => 0,
                'recent_registrations' => 0,
                'recent_logins' => 0,
                'never_logged_in' => 0,
                'this_month_registrations' => 0,
                'email_settings' => [
                    'welcome_emails_enabled' => false,
                    'bulk_emails_enabled' => false,
                    'admin_notifications_enabled' => false,
                    'password_reset_emails_enabled' => false,
                    'status_change_emails_enabled' => false,
                ],
                'email_stats' => [
                    'users_with_welcome_emails' => 0,
                    'users_with_password_resets' => 0,
                    'total_emails_sent' => 0,
                ]
            ];
        }
    }

    /**
     * IMPROVED: Better error handling in the bulk creation process
     */
    private function processBulkUserCreationFixed(
        array $usersData, 
        bool $skipDuplicates = true, 
        bool $sendWelcomeEmail = false, 
        bool $generatePasswords = true,
        $adminUser = null,
        bool $dryRun = false
    ): array {
        $successful = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];
        $createdUsers = [];
        $emailsQueued = 0;
        $duplicateEmails = [];
        $duplicateStudentIds = [];
        $duplicateEmployeeIds = [];

        Log::info('=== STARTING BULK USER CREATION PROCESS ===', [
            'user_count' => count($usersData),
            'skip_duplicates' => $skipDuplicates,
            'dry_run' => $dryRun
        ]);

        if ($dryRun) {
            Log::info('=== DRY RUN MODE - NO ACTUAL CREATION ===');
            
            // Simulate the process without creating users
            foreach ($usersData as $index => $userData) {
                try {
                    // Check for existing email
                    if (User::where('email', $userData['email'])->exists()) {
                        if ($skipDuplicates) {
                            $skipped++;
                            $duplicateEmails[] = $userData['email'];
                        } else {
                            $failed++;
                            $errors[] = [
                                'index' => $index,
                                'email' => $userData['email'],
                                'error' => 'Email already exists',
                                'field' => 'email'
                            ];
                        }
                        continue;
                    }

                    // Check for existing student_id if provided
                    if (!empty($userData['student_id']) && User::where('student_id', $userData['student_id'])->exists()) {
                        if ($skipDuplicates) {
                            $skipped++;
                            $duplicateStudentIds[] = $userData['student_id'];
                        } else {
                            $failed++;
                            $errors[] = [
                                'index' => $index,
                                'email' => $userData['email'],
                                'student_id' => $userData['student_id'],
                                'error' => 'Student ID already exists',
                                'field' => 'student_id'
                            ];
                        }
                        continue;
                    }

                    // Check for existing employee_id if provided
                    if (!empty($userData['employee_id']) && User::where('employee_id', $userData['employee_id'])->exists()) {
                        if ($skipDuplicates) {
                            $skipped++;
                            $duplicateEmployeeIds[] = $userData['employee_id'];
                        } else {
                            $failed++;
                            $errors[] = [
                                'index' => $index,
                                'email' => $userData['email'],
                                'employee_id' => $userData['employee_id'],
                                'error' => 'Employee ID already exists',
                                'field' => 'employee_id'
                            ];
                        }
                        continue;
                    }

                    $successful++;
                    
                } catch (Exception $e) {
                    $failed++;
                    $errors[] = [
                        'index' => $index,
                        'email' => $userData['email'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'field' => 'general'
                    ];
                }
            }

            return [
                'successful' => $successful,
                'failed' => $failed,
                'skipped' => $skipped,
                'errors' => $errors,
                'emails_queued' => 0,
                'created_users' => [],
                'dry_run' => true,
                'duplicate_analysis' => [
                    'duplicate_emails' => array_unique($duplicateEmails),
                    'duplicate_student_ids' => array_unique($duplicateStudentIds),
                    'duplicate_employee_ids' => array_unique($duplicateEmployeeIds),
                ]
            ];
        }

        // Actual creation process
        DB::beginTransaction();

        try {
            foreach ($usersData as $index => $userData) {
                try {
                    // Check for duplicate email
                    if (User::where('email', $userData['email'])->exists()) {
                        if ($skipDuplicates) {
                            $skipped++;
                            $duplicateEmails[] = $userData['email'];
                            Log::info('Skipped duplicate email', [
                                'email' => $userData['email'],
                                'index' => $index
                            ]);
                            continue;
                        } else {
                            $failed++;
                            $errors[] = [
                                'index' => $index,
                                'email' => $userData['email'],
                                'error' => 'Email already exists',
                                'field' => 'email'
                            ];
                            continue;
                        }
                    }

                    // Check for duplicate student_id if provided
                    if (!empty($userData['student_id']) && User::where('student_id', $userData['student_id'])->exists()) {
                        if ($skipDuplicates) {
                            $skipped++;
                            $duplicateStudentIds[] = $userData['student_id'];
                            Log::info('Skipped duplicate student ID', [
                                'student_id' => $userData['student_id'],
                                'index' => $index
                            ]);
                            continue;
                        } else {
                            $failed++;
                            $errors[] = [
                                'index' => $index,
                                'email' => $userData['email'],
                                'student_id' => $userData['student_id'],
                                'error' => 'Student ID already exists',
                                'field' => 'student_id'
                            ];
                            continue;
                        }
                    }

                    // Check for duplicate employee_id if provided
                    if (!empty($userData['employee_id']) && User::where('employee_id', $userData['employee_id'])->exists()) {
                        if ($skipDuplicates) {
                            $skipped++;
                            $duplicateEmployeeIds[] = $userData['employee_id'];
                            Log::info('Skipped duplicate employee ID', [
                                'employee_id' => $userData['employee_id'],
                                'index' => $index
                            ]);
                            continue;
                        } else {
                            $failed++;
                            $errors[] = [
                                'index' => $index,
                                'email' => $userData['email'],
                                'employee_id' => $userData['employee_id'],
                                'error' => 'Employee ID already exists',
                                'field' => 'employee_id'
                            ];
                            continue;
                        }
                    }

                    // Generate secure password
                    $password = $generatePasswords ? $this->generateSecurePassword() : $this->generateSecurePassword();
                    
                    // Prepare user creation data
                    $userCreateData = [
                        'name' => trim($userData['name']),
                        'email' => strtolower(trim($userData['email'])),
                        'password' => Hash::make($password),
                        'role' => $userData['role'] ?? 'student',
                        'status' => $userData['status'] ?? 'active',
                        'email_verified_at' => now(), // Auto-verify admin created users
                        'created_by_admin' => true,
                        'created_via_bulk' => true,
                    ];

                    // Add optional fields if provided and not empty
                    $optionalFields = ['phone', 'student_id', 'employee_id', 'address', 'date_of_birth'];
                    foreach ($optionalFields as $field) {
                        if (!empty($userData[$field])) {
                            $userCreateData[$field] = trim($userData[$field]);
                        }
                    }

                    // Create the user
                    $user = User::create($userCreateData);
                    
                    $createdUsers[] = [
                        'user' => $user,
                        'generated_password' => $password,
                        'index' => $index
                    ];
                    
                    $successful++;

                    Log::info('User created successfully in bulk operation', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'role' => $user->role,
                        'index' => $index
                    ]);

                } catch (Exception $e) {
                    $failed++;
                    $errorData = [
                        'index' => $index,
                        'email' => $userData['email'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'field' => 'creation'
                    ];

                    // Add more context for specific errors
                    if (str_contains($e->getMessage(), 'Duplicate entry')) {
                        if (str_contains($e->getMessage(), 'email')) {
                            $errorData['field'] = 'email';
                            $errorData['error'] = 'Email already exists';
                        } elseif (str_contains($e->getMessage(), 'student_id')) {
                            $errorData['field'] = 'student_id';
                            $errorData['error'] = 'Student ID already exists';
                        } elseif (str_contains($e->getMessage(), 'employee_id')) {
                            $errorData['field'] = 'employee_id';
                            $errorData['error'] = 'Employee ID already exists';
                        }
                    }

                    $errors[] = $errorData;

                    Log::error('Failed to create user in bulk operation', [
                        'index' => $index,
                        'email' => $userData['email'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'user_data' => $userData
                    ]);
                }
            }

            DB::commit();

            // Queue welcome emails if requested and we have created users
            if ($sendWelcomeEmail && !empty($createdUsers) && $this->shouldSendWelcomeEmail(request())) {
                try {
                    // Send individual welcome emails
                    foreach ($createdUsers as $userItem) {
                        try {
                            if (class_exists('App\Jobs\SendWelcomeEmail')) {
                                SendWelcomeEmail::dispatch($userItem['user'], $userItem['generated_password']);
                            } else {
                                // Direct email sending as fallback
                                Mail::to($userItem['user']->email)->send(
                                    new \App\Mail\WelcomeEmail($userItem['user'], $userItem['generated_password'])
                                );
                            }
                        } catch (Exception $emailError) {
                            Log::error('Failed to send individual welcome email', [
                                'user_id' => $userItem['user']->id,
                                'error' => $emailError->getMessage()
                            ]);
                        }
                    }
                    
                    $emailsQueued = count($createdUsers);
                    
                    Log::info('✅ Bulk welcome emails queued/sent', [
                        'user_count' => count($createdUsers),
                        'admin_user_id' => $adminUser?->id
                    ]);
                    
                } catch (Exception $e) {
                    Log::error('❌ Failed to queue/send bulk welcome emails', [
                        'user_count' => count($createdUsers),
                        'error' => $e->getMessage()
                    ]);
                    // Don't fail the entire operation for email issues
                }
            }

            // Prepare successful users data for response
            $createdUsersData = array_map(function($item) {
                return [
                    'id' => $item['user']->id,
                    'name' => $item['user']->name,
                    'email' => $item['user']->email,
                    'role' => $item['user']->role,
                    'status' => $item['user']->status,
                    'student_id' => $item['user']->student_id,
                    'employee_id' => $item['user']->employee_id,
                    'index' => $item['index'],
                    // Only include password in debug mode
                    'generated_password' => config('app.debug') ? $item['generated_password'] : '[HIDDEN - SENT VIA EMAIL]'
                ];
            }, $createdUsers);

            return [
                'successful' => $successful,
                'failed' => $failed,
                'skipped' => $skipped,
                'errors' => $errors,
                'emails_queued' => $emailsQueued,
                'created_users' => $createdUsersData,
                'dry_run' => false,
                'duplicate_analysis' => [
                    'duplicate_emails' => array_unique($duplicateEmails),
                    'duplicate_student_ids' => array_unique($duplicateStudentIds),
                    'duplicate_employee_ids' => array_unique($duplicateEmployeeIds),
                ]
            ];

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('❌ Bulk creation transaction failed', [
                'error' => $e->getMessage(),
                'successful_before_rollback' => $successful,
                'failed_before_rollback' => $failed
            ]);
            
            throw new Exception('Bulk user creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate a more secure random password
     */
    private function generateSecurePassword(int $length = 12): string
    {
        // Ensure password has at least one of each character type
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*';
        
        $password = '';
        
        // Ensure at least one character from each set
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];
        
        // Fill the rest randomly
        $allCharacters = $lowercase . $uppercase . $numbers . $symbols;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allCharacters[random_int(0, strlen($allCharacters) - 1)];
        }
        
        // Shuffle the password to randomize the order
        return str_shuffle($password);
    }

    /**
     * Generate a random password
     */
    private function generateRandomPassword(int $length = 12): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $password;
    }

    /**
     * Determine if welcome email should be sent based on configuration and request
     */
    private function shouldSendWelcomeEmail(Request $request): bool
    {
        // Check global configuration first
        $globalEnabled = config('app.send_welcome_emails', env('SEND_WELCOME_EMAILS', true));
        
        if (!$globalEnabled) {
            return false;
        }

        // Check request preference (defaults to true if global is enabled)
        return $request->boolean('send_welcome_email', true);
    }

    /**
     * Check if password reset emails should be sent
     */
    private function shouldSendPasswordResetEmails(): bool
    {
        return config('app.send_password_reset_emails', env('SEND_PASSWORD_RESET_EMAILS', true));
    }

    /**
     * Check if status change emails should be sent
     */
    private function shouldSendStatusChangeEmails(): bool
    {
        return config('app.send_email_on_status_change', env('SEND_EMAIL_ON_STATUS_CHANGE', true));
    }

    /**
     * Log request details for debugging
     */
    private function logRequestDetails(string $operation): void
    {
        Log::info("=== {$operation} Request ===", [
            'method' => request()->method(),
            'url' => request()->fullUrl(),
            'user_agent' => request()->header('User-Agent'),
            'ip' => request()->ip(),
            'user_id' => auth()->id(),
        ]);
    }
}