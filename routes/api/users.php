<?php
// routes/api/users.php - User Management Routes

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| User Management and Profile Routes (All authenticated users)
|--------------------------------------------------------------------------
*/

Route::prefix('user')->group(function () {
    
    // Get user profile
    Route::get('/profile', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => ['user' => $request->user()]
        ]);
    })->middleware('throttle:60,1');
    
    // Update user profile
    Route::put('/profile', function (Request $request) {
        $user = $request->user();
        
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'bio' => 'sometimes|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user->update($request->only(['name', 'email', 'phone', 'bio']));
            
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => ['user' => $user->fresh()]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile'
            ], 500);
        }
    })->middleware('throttle:20,1');

    // Change password
    Route::post('/change-password', function (Request $request) {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!\Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 422);
        }

        try {
            $user->update([
                'password' => \Illuminate\Support\Facades\Hash::make($request->new_password)
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password'
            ], 500);
        }
    })->middleware('throttle:10,1');

    // User's notification summary with caching
    Route::get('/notification-summary', function (Request $request) {
        $user = $request->user();
        $cacheKey = "user_notification_summary.{$user->id}";
        
        $summary = Cache::remember($cacheKey, 300, function () use ($user) {
            return [
                'unread_notifications' => $user->notifications()->where('read', false)->count(),
                'total_notifications' => $user->notifications()->count(),
                'high_priority_unread' => $user->notifications()->where('read', false)->where('priority', 'high')->count(),
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    })->middleware('throttle:40,1');

    // User's ticket summary with caching and role-based filtering
    Route::get('/ticket-summary', function (Request $request) {
        $user = $request->user();
        $cacheKey = "user_ticket_summary.{$user->id}";
        
        $summary = Cache::remember($cacheKey, 300, function () use ($user) {
            if ($user->role === 'student') {
                $tickets = $user->tickets();
            } elseif (in_array($user->role, ['counselor', 'advisor'])) {
                $tickets = \App\Models\Ticket::where('assigned_to', $user->id);
            } else {
                $tickets = \App\Models\Ticket::query();
            }
            
            return [
                'open_tickets' => (clone $tickets)->whereIn('status', ['Open', 'In Progress'])->count(),
                'total_tickets' => $tickets->count(),
                'recent_activity' => (clone $tickets)->where('updated_at', '>=', now()->subDays(7))->count(),
                'crisis_tickets' => (clone $tickets)->where('crisis_flag', true)->count(),
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    })->middleware('throttle:40,1');

    // Get user permissions based on role
    Route::get('/permissions', function (Request $request) {
        $user = $request->user();
        
        $permissions = [
            'tickets' => [
                'can_create' => $user->isStudent() || $user->isAdmin(),
                'can_view_own' => true,
                'can_view_all' => $user->isAdmin(),
                'can_assign' => $user->isAdmin(),
                'can_modify' => $user->isStaff(),
                'can_delete' => $user->isAdmin(),
                'can_add_internal_notes' => $user->isStaff(),
                'can_manage_tags' => $user->isStaff(),
            ],
            'users' => [
                'can_view_all' => $user->isAdmin(),
                'can_create' => $user->isAdmin(),
                'can_modify' => $user->isAdmin(),
                'can_delete' => $user->isAdmin(),
            ],
            'system' => [
                'can_view_analytics' => $user->isStaff(),
                'can_export_data' => $user->isAdmin(),
                'can_manage_settings' => $user->isAdmin(),
                'can_view_logs' => $user->isAdmin(),
            ],
        ];
        
        return response()->json([
            'success' => true,
            'data' => [
                'user_role' => $user->role,
                'permissions' => $permissions
            ]
        ]);
    })->middleware('throttle:30,1');
});