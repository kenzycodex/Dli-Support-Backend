<?php
// app/Http/Controllers/Admin/UserManagementController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    /**
     * Get all users with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        // Apply filters
        if ($request->has('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Sort by
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage);

        // Get user statistics
        $stats = [
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
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
                'stats' => $stats
            ]
        ]);
    }

    /**
     * Create new user
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:student,counselor,advisor,admin',
            'status' => 'sometimes|in:active,inactive,suspended',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'student_id' => 'nullable|string|unique:users,student_id',
            'employee_id' => 'nullable|string|unique:users,employee_id',
            'specializations' => 'nullable|array',
            'bio' => 'nullable|string',
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

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
            if ($request->filled('phone')) {
                $userData['phone'] = $request->phone;
            }
            if ($request->filled('address')) {
                $userData['address'] = $request->address;
            }
            if ($request->filled('date_of_birth')) {
                $userData['date_of_birth'] = $request->date_of_birth;
            }
            if ($request->filled('student_id')) {
                $userData['student_id'] = $request->student_id;
            }
            if ($request->filled('employee_id')) {
                $userData['employee_id'] = $request->employee_id;
            }
            if ($request->filled('specializations')) {
                $userData['specializations'] = $request->specializations;
            }
            if ($request->filled('bio')) {
                $userData['bio'] = $request->bio;
            }

            $user = User::create($userData);

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => ['user' => $user]
            ], 201);
        } catch (\Exception $e) {
            \Log::error('User creation failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user. Please try again.',
                'errors' => ['general' => ['An unexpected error occurred while creating the user.']]
            ], 500);
        }
    }

    /**
     * Get single user
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['user' => $user]
        ]);
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user): JsonResponse
    {
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
            'password' => 'sometimes|nullable|string|min:8',
            'role' => 'sometimes|required|in:student,counselor,advisor,admin',
            'status' => 'sometimes|required|in:active,inactive,suspended',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
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
            'bio' => 'nullable|string',
        ], [
            'email.unique' => 'This email address is already registered.',
            'student_id.unique' => 'This student ID is already in use.',
            'employee_id.unique' => 'This employee ID is already in use.',
            'password.min' => 'Password must be at least 8 characters long.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = $request->only([
                'name', 'email', 'role', 'status', 'phone', 'address',
                'date_of_birth', 'student_id', 'employee_id', 'specializations', 'bio'
            ]);

            // Only update password if provided
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => ['user' => $user->fresh()]
            ]);
        } catch (\Exception $e) {
            \Log::error('User update failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user. Please try again.',
            ], 500);
        }
    }

    /**
     * Delete user
     */
    public function destroy(User $user): JsonResponse
    {
        // Prevent admin from deleting themselves
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        try {
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('User deletion failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user. Please try again.',
            ], 500);
        }
    }

    /**
     * Bulk actions on users
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:activate,deactivate,suspend,delete',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userIds = $request->user_ids;
        $action = $request->action;

        // Prevent admin from performing bulk actions on themselves
        if (in_array(auth()->id(), $userIds)) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot perform bulk actions on your own account'
            ], 403);
        }

        try {
            $users = User::whereIn('id', $userIds);

            switch ($action) {
                case 'activate':
                    $users->update(['status' => User::STATUS_ACTIVE]);
                    $message = 'Users activated successfully';
                    break;
                case 'deactivate':
                    $users->update(['status' => User::STATUS_INACTIVE]);
                    $message = 'Users deactivated successfully';
                    break;
                case 'suspend':
                    $users->update(['status' => User::STATUS_SUSPENDED]);
                    $message = 'Users suspended successfully';
                    break;
                case 'delete':
                    $users->delete();
                    $message = 'Users deleted successfully';
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            \Log::error('Bulk action failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform bulk action. Please try again.',
            ], 500);
        }
    }

    /**
     * Get user statistics for dashboard
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = [
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
            ];

            return response()->json([
                'success' => true,
                'data' => ['stats' => $stats]
            ]);
        } catch (\Exception $e) {
            \Log::error('Stats retrieval failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics.',
            ], 500);
        }
    }

    /**
     * Reset user password
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'new_password' => 'required|string|min:8',
        ], [
            'new_password.required' => 'New password is required.',
            'new_password.min' => 'New password must be at least 8 characters long.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            // Revoke all tokens to force re-login
            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Password reset failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password. Please try again.',
            ], 500);
        }
    }

    /**
     * Toggle user status
     */
    public function toggleStatus(User $user): JsonResponse
    {
        // Prevent admin from toggling their own status
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot change your own status'
            ], 403);
        }

        try {
            $newStatus = $user->status === User::STATUS_ACTIVE 
                ? User::STATUS_INACTIVE 
                : User::STATUS_ACTIVE;

            $user->update(['status' => $newStatus]);

            return response()->json([
                'success' => true,
                'message' => "User status changed to {$newStatus}",
                'data' => ['user' => $user->fresh()]
            ]);
        } catch (\Exception $e) {
            \Log::error('Status toggle failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to change user status. Please try again.',
            ], 500);
        }
    }

    /**
     * Get available roles and statuses
     */
    public function getOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'roles' => User::getAvailableRoles(),
                'statuses' => User::getAvailableStatuses()
            ]
        ]);
    }

    /**
     * Export users data
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $query = User::query();

            // Apply same filters as index
            if ($request->has('role') && $request->role !== 'all') {
                $query->where('role', $request->role);
            }

            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }

            $users = $query->get(['id', 'name', 'email', 'role', 'status', 'phone', 'created_at', 'last_login_at']);

            return response()->json([
                'success' => true,
                'data' => ['users' => $users],
                'message' => 'Users data exported successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Export failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export users data. Please try again.',
            ], 500);
        }
    }
}