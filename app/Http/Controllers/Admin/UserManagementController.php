<?php
// app/Http/Controllers/Admin/UserManagementController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * Create new user
     */
    public function store(Request $request): JsonResponse
    {
        $this->logRequestDetails('User Creation');

        try {
            Log::info('=== CREATING USER ===', [
                'admin_user_id' => $request->user()?->id,
                'role' => $request->role
            ]);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
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
                $userData = [
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
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

                DB::commit();

                Log::info('✅ User created successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role
                ]);

                return $this->successResponse([
                    'user' => $user->fresh()
                ], 'User created successfully', 201);

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
     * Bulk actions on users
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $this->logRequestDetails('Bulk User Action');

        try {
            Log::info('=== BULK USER ACTION ===', [
                'action' => $request->action,
                'user_count' => count($request->user_ids ?? []),
                'admin_user_id' => $request->user()?->id
            ]);

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:activate,deactivate,suspend,delete',
                'user_ids' => 'required|array|min:1|max:100',
                'user_ids.*' => 'exists:users,id',
                'reason' => 'sometimes|string|max:500',
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
                $users = User::whereIn('id', $userIds);
                $affectedCount = 0;

                switch ($action) {
                    case 'activate':
                        $affectedCount = $users->update(['status' => User::STATUS_ACTIVE]);
                        $message = "{$affectedCount} user(s) activated successfully";
                        break;
                    case 'deactivate':
                        $affectedCount = $users->update(['status' => User::STATUS_INACTIVE]);
                        $message = "{$affectedCount} user(s) deactivated successfully";
                        break;
                    case 'suspend':
                        $affectedCount = $users->update(['status' => User::STATUS_SUSPENDED]);
                        $message = "{$affectedCount} user(s) suspended successfully";
                        break;
                    case 'delete':
                        $affectedCount = $users->count();
                        $users->delete();
                        $message = "{$affectedCount} user(s) deleted successfully";
                        break;
                }

                DB::commit();

                Log::info('✅ Bulk action completed successfully', [
                    'action' => $action,
                    'affected_count' => $affectedCount,
                    'reason' => $reason
                ]);

                return $this->successResponse([
                    'affected_count' => $affectedCount,
                    'action' => $action
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
     * Bulk create users from CSV
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $this->logRequestDetails('Bulk User Creation');

        try {
            Log::info('=== BULK USER CREATION ===', [
                'admin_user_id' => $request->user()?->id,
                'has_file' => $request->hasFile('csv_file'),
                'has_data' => $request->has('users_data')
            ]);

            $validator = Validator::make($request->all(), [
                'csv_file' => 'required_without:users_data|file|mimes:csv,txt|max:10240', // 10MB max
                'users_data' => 'required_without:csv_file|array|max:1000',
                'users_data.*.name' => 'required|string|max:255',
                'users_data.*.email' => 'required|email|max:255',
                'users_data.*.role' => 'required|in:student,counselor,advisor,admin',
                'users_data.*.status' => 'sometimes|in:active,inactive,suspended',
                'skip_duplicates' => 'sometimes|boolean',
                'send_welcome_email' => 'sometimes|boolean',
            ], [
                'csv_file.required_without' => 'Please provide either a CSV file or users data',
                'csv_file.mimes' => 'File must be a CSV file',
                'csv_file.max' => 'File size cannot exceed 10MB',
                'users_data.required_without' => 'Please provide either users data or a CSV file',
                'users_data.max' => 'Cannot create more than 1000 users at once',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Bulk creation validation failed', [
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check your file or data and try again');
            }

            $usersData = [];

            // Process CSV file if provided
            if ($request->hasFile('csv_file')) {
                $usersData = $this->processCsvFile($request->file('csv_file'));
            } else {
                $usersData = $request->users_data;
            }

            if (empty($usersData)) {
                return $this->errorResponse('No valid user data found to process', 400);
            }

            $skipDuplicates = $request->boolean('skip_duplicates', true);
            $sendWelcomeEmail = $request->boolean('send_welcome_email', false);

            $results = $this->processBulkUserCreation($usersData, $skipDuplicates, $sendWelcomeEmail);

            Log::info('✅ Bulk user creation completed', [
                'total_processed' => count($usersData),
                'successful' => $results['successful'],
                'failed' => $results['failed'],
                'skipped' => $results['skipped']
            ]);

            return $this->successResponse([
                'results' => $results,
                'summary' => [
                    'total_processed' => count($usersData),
                    'successful' => $results['successful'],
                    'failed' => $results['failed'],
                    'skipped' => $results['skipped'],
                ]
            ], 'Bulk user creation completed', 201);

        } catch (Exception $e) {
            Log::error('❌ Bulk user creation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'Bulk user creation');
        }
    }

    /**
     * Get user statistics for dashboard
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
     * Reset user password
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->logRequestDetails('Password Reset');

        try {
            Log::info('=== RESETTING USER PASSWORD ===', [
                'target_user_id' => $user->id,
                'admin_user_id' => $request->user()?->id
            ]);

            $validator = Validator::make($request->all(), [
                'new_password' => 'required|string|min:8|confirmed',
                'notify_user' => 'sometimes|boolean',
            ], [
                'new_password.required' => 'New password is required.',
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
                $user->update([
                    'password' => Hash::make($request->new_password)
                ]);

                // Revoke all tokens to force re-login
                $user->tokens()->delete();

                DB::commit();

                Log::info('✅ Password reset successfully', [
                    'user_id' => $user->id,
                    'tokens_revoked' => true
                ]);

                return $this->successResponse(
                    [],
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
     * Toggle user status
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
                $newStatus = $user->status === User::STATUS_ACTIVE 
                    ? User::STATUS_INACTIVE 
                    : User::STATUS_ACTIVE;

                $user->update(['status' => $newStatus]);

                DB::commit();

                Log::info('✅ User status toggled successfully', [
                    'user_id' => $user->id,
                    'old_status' => $user->getOriginal('status'),
                    'new_status' => $newStatus
                ]);

                return $this->successResponse([
                    'user' => $user->fresh()
                ], "User status changed to {$newStatus}");

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
     * Get available roles and statuses
     */
    public function getOptions(): JsonResponse
    {
        try {
            return $this->successResponse([
                'roles' => User::getAvailableRoles(),
                'statuses' => User::getAvailableStatuses()
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
     * Get comprehensive user statistics
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
            ];
        }
    }

    /**
     * Process CSV file for bulk user creation
     */
    private function processCsvFile($file): array
    {
        try {
            $csvData = array_map('str_getcsv', file($file->getRealPath()));
            $headers = array_shift($csvData); // Remove header row
            
            // Normalize headers (remove spaces, convert to lowercase)
            $headers = array_map(function($header) {
                return strtolower(trim(str_replace(' ', '_', $header)));
            }, $headers);

            $users = [];
            
            foreach ($csvData as $row) {
                if (count($row) !== count($headers)) {
                    continue; // Skip malformed rows
                }
                
                $userData = array_combine($headers, $row);
                
                // Map CSV headers to expected fields
                $mappedData = [
                    'name' => $userData['name'] ?? $userData['full_name'] ?? '',
                    'email' => $userData['email'] ?? $userData['email_address'] ?? '',
                    'role' => strtolower($userData['role'] ?? 'student'),
                    'status' => strtolower($userData['status'] ?? 'active'),
                    'phone' => $userData['phone'] ?? $userData['phone_number'] ?? null,
                    'student_id' => $userData['student_id'] ?? null,
                    'employee_id' => $userData['employee_id'] ?? null,
                ];

                // Skip rows with missing required fields
                if (empty($mappedData['name']) || empty($mappedData['email'])) {
                    continue;
                }

                // Validate role
                if (!in_array($mappedData['role'], ['student', 'counselor', 'advisor', 'admin'])) {
                    $mappedData['role'] = 'student';
                }

                // Validate status
                if (!in_array($mappedData['status'], ['active', 'inactive', 'suspended'])) {
                    $mappedData['status'] = 'active';
                }

                $users[] = $mappedData;
            }

            Log::info('CSV file processed', [
                'total_rows' => count($csvData),
                'valid_users' => count($users)
            ]);

            return $users;
        } catch (Exception $e) {
            Log::error('CSV processing failed', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Process bulk user creation
     */
    private function processBulkUserCreation(array $usersData, bool $skipDuplicates = true, bool $sendWelcomeEmail = false): array
    {
        $successful = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];
        $createdUsers = [];

        DB::beginTransaction();

        try {
            foreach ($usersData as $index => $userData) {
                try {
                    // Check for duplicate email
                    if (User::where('email', $userData['email'])->exists()) {
                        if ($skipDuplicates) {
                            $skipped++;
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
                                'error' => 'Email already exists'
                            ];
                            continue;
                        }
                    }

                    // Generate random password
                    $password = $this->generateRandomPassword();
                    
                    $userCreateData = [
                        'name' => $userData['name'],
                        'email' => $userData['email'],
                        'password' => Hash::make($password),
                        'role' => $userData['role'],
                        'status' => $userData['status'],
                        'email_verified_at' => now(),
                    ];

                    // Add optional fields
                    if (!empty($userData['phone'])) {
                        $userCreateData['phone'] = $userData['phone'];
                    }
                    if (!empty($userData['student_id'])) {
                        $userCreateData['student_id'] = $userData['student_id'];
                    }
                    if (!empty($userData['employee_id'])) {
                        $userCreateData['employee_id'] = $userData['employee_id'];
                    }

                    $user = User::create($userCreateData);
                    
                    $createdUsers[] = [
                        'user' => $user,
                        'generated_password' => $password
                    ];
                    
                    $successful++;

                    Log::info('User created in bulk operation', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'index' => $index
                    ]);

                } catch (Exception $e) {
                    $failed++;
                    $errors[] = [
                        'index' => $index,
                        'email' => $userData['email'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];

                    Log::error('Failed to create user in bulk operation', [
                        'index' => $index,
                        'email' => $userData['email'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            // TODO: Send welcome emails if requested
            if ($sendWelcomeEmail && !empty($createdUsers)) {
                // Implement email sending logic here
                Log::info('Welcome emails would be sent', [
                    'user_count' => count($createdUsers)
                ]);
            }

            return [
                'successful' => $successful,
                'failed' => $failed,
                'skipped' => $skipped,
                'errors' => $errors,
                'created_users' => array_map(function($item) {
                    return [
                        'id' => $item['user']->id,
                        'name' => $item['user']->name,
                        'email' => $item['user']->email,
                        'role' => $item['user']->role,
                        'generated_password' => $item['generated_password']
                    ];
                }, $createdUsers)
            ];

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
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