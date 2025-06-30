<?php
// app/Http/Controllers/NotificationController.php (RELAXED VERSION)

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Get user's notifications with caching
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $params = $request->only(['type', 'read_status', 'priority', 'search', 'page', 'per_page']);
            $cacheKey = "notifications.user.{$user->id}." . md5(serialize($params));
            
            // Reduced cache time to 1 minute for more responsive updates
            $result = Cache::remember($cacheKey, 60, function () use ($request, $user) {
                
                // Optimized query with specific columns only
                $query = $user->notifications()->select([
                    'id', 'type', 'title', 'message', 'priority', 'read', 'data', 'read_at', 'created_at'
                ]);

                // Apply filters efficiently
                $this->applyFilters($query, $request);

                // Pagination with limit
                $perPage = min($request->get('per_page', 20), 50);
                $notifications = $query->orderBy('created_at', 'desc')->paginate($perPage);

                return [
                    'notifications' => $notifications->items(),
                    'pagination' => [
                        'current_page' => $notifications->currentPage(),
                        'last_page' => $notifications->lastPage(),
                        'per_page' => $notifications->perPage(),
                        'total' => $notifications->total(),
                    ]
                ];
            });

            // Get unread count with separate optimized query
            $unreadCount = $this->getUnreadCountForUser($user->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'notifications' => $result['notifications'],
                    'pagination' => $result['pagination'],
                    'counts' => [
                        'unread' => $unreadCount,
                        'total' => $user->notifications()->count(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Notifications fetch failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications.',
            ], 500);
        }
    }

    /**
     * Get unread notifications count (OPTIMIZED WITH RELAXED CACHING)
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $cacheKey = "unread_count.user.{$user->id}";
            
            // Reduced cache time to 30 seconds for more responsive updates
            $count = Cache::remember($cacheKey, 30, function () use ($user) {
                // Use raw query for maximum performance
                return DB::table('notifications')
                         ->where('user_id', $user->id)
                         ->where('read', false)
                         ->count();
            });

            return response()->json([
                'success' => true,
                'data' => ['unread_count' => $count]
            ]);
        } catch (\Exception $e) {
            Log::warning('Unread count fetch failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);
            
            // Return cached value or 0 on error to prevent breaking UI
            $fallbackCount = Cache::get("unread_count.user.{$request->user()->id}", 0);
            
            return response()->json([
                'success' => true,
                'data' => ['unread_count' => $fallbackCount]
            ]);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        try {
            if ($notification->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to notification'
                ], 403);
            }

            if (!$notification->read) {
                $notification->update([
                    'read' => true,
                    'read_at' => now()
                ]);
                $this->clearUserNotificationCaches($request->user()->id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'data' => ['notification' => $notification->only(['id', 'read', 'read_at'])]
            ]);
        } catch (\Exception $e) {
            Log::error('Mark as read failed', [
                'notification_id' => $notification->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read.',
            ], 500);
        }
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread(Request $request, Notification $notification): JsonResponse
    {
        try {
            if ($notification->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to notification'
                ], 403);
            }

            if ($notification->read) {
                $notification->update([
                    'read' => false,
                    'read_at' => null
                ]);
                $this->clearUserNotificationCaches($request->user()->id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as unread',
                'data' => ['notification' => $notification->only(['id', 'read', 'read_at'])]
            ]);
        } catch (\Exception $e) {
            Log::error('Mark as unread failed', [
                'notification_id' => $notification->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as unread.',
            ], 500);
        }
    }

    /**
     * Mark all notifications as read (BULK OPTIMIZED)
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $updated = DB::table('notifications')
                        ->where('user_id', $request->user()->id)
                        ->where('read', false)
                        ->update([
                            'read' => true,
                            'read_at' => now(),
                            'updated_at' => now()
                        ]);

            $this->clearUserNotificationCaches($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => "Marked {$updated} notifications as read"
            ]);
        } catch (\Exception $e) {
            Log::error('Mark all as read failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read.',
            ], 500);
        }
    }

    /**
     * Delete notification
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        try {
            if ($notification->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to notification'
                ], 403);
            }

            $notification->delete();
            $this->clearUserNotificationCaches($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Notification deletion failed', [
                'notification_id' => $notification->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification.',
            ], 500);
        }
    }

    /**
     * Bulk actions on notifications
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:read,unread,delete',
            'notification_ids' => 'required|array|min:1|max:100',
            'notification_ids.*' => 'integer|exists:notifications,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $notificationIds = $request->notification_ids;
            $action = $request->action;
            $userId = $request->user()->id;

            // Verify ownership
            $ownedIds = DB::table('notifications')
                         ->where('user_id', $userId)
                         ->whereIn('id', $notificationIds)
                         ->pluck('id')
                         ->toArray();

            if (count($ownedIds) !== count($notificationIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some notifications do not belong to you'
                ], 403);
            }

            // Perform bulk operation
            switch ($action) {
                case 'read':
                    $updated = DB::table('notifications')
                               ->whereIn('id', $ownedIds)
                               ->where('read', false)
                               ->update([
                                   'read' => true,
                                   'read_at' => now(),
                                   'updated_at' => now()
                               ]);
                    $message = "Marked {$updated} notifications as read";
                    break;
                    
                case 'unread':
                    $updated = DB::table('notifications')
                               ->whereIn('id', $ownedIds)
                               ->where('read', true)
                               ->update([
                                   'read' => false,
                                   'read_at' => null,
                                   'updated_at' => now()
                               ]);
                    $message = "Marked {$updated} notifications as unread";
                    break;
                    
                case 'delete':
                    $updated = DB::table('notifications')
                               ->whereIn('id', $ownedIds)
                               ->delete();
                    $message = "Deleted {$updated} notifications";
                    break;
            }

            $this->clearUserNotificationCaches($userId);

            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk action failed', [
                'user_id' => $request->user()->id,
                'action' => $request->action,
                'ids_count' => count($request->notification_ids),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform bulk action.',
            ], 500);
        }
    }

    /**
     * Create notification (admin only) with relaxed rate limiting
     */
    public function store(Request $request): JsonResponse
    {
        // Relaxed rate limiting - increased from 5 to 15 per minute
        $cacheKey = "notification_creation.user.{$request->user()->id}";
        $recentCreations = Cache::get($cacheKey, 0);
        
        if ($recentCreations >= 15) {
            return response()->json([
                'success' => false,
                'message' => 'Rate limit exceeded. Please wait before creating more notifications.',
            ], 429);
        }

        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1|max:500',
            'user_ids.*' => 'integer|exists:users,id',
            'type' => 'required|in:appointment,ticket,system,reminder',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:500',
            'priority' => 'sometimes|in:low,medium,high',
            'data' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userIds = $request->user_ids;
            $type = $request->type;
            $title = $request->title;
            $message = $request->message;
            $priority = $request->get('priority', 'medium');
            $data = $request->get('data');

            // Create notifications in batches
            $chunks = array_chunk($userIds, 50);
            
            foreach ($chunks as $chunk) {
                $notifications = [];
                $now = now();
                
                foreach ($chunk as $userId) {
                    $notifications[] = [
                        'user_id' => $userId,
                        'type' => $type,
                        'title' => $title,
                        'message' => $message,
                        'priority' => $priority,
                        'data' => $data ? json_encode($data) : null,
                        'read' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                
                DB::table('notifications')->insert($notifications);
                $this->clearMultipleUserCaches($chunk);
            }

            // Update rate limit
            Cache::put($cacheKey, $recentCreations + 1, 60);

            return response()->json([
                'success' => true,
                'message' => 'Notifications created successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Notification creation failed', [
                'user_id' => $request->user()->id,
                'recipient_count' => count($request->user_ids),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create notifications.',
            ], 500);
        }
    }

    /**
     * Get notification options
     */
    public function getOptions(): JsonResponse
    {
        $options = Cache::remember('notification_options', 86400, function () {
            return [
                'types' => [
                    'appointment' => 'Appointment',
                    'ticket' => 'Support Ticket',
                    'system' => 'System Notification',
                    'reminder' => 'Reminder'
                ],
                'priorities' => [
                    'low' => 'Low',
                    'medium' => 'Medium',
                    'high' => 'High'
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $options
        ]);
    }

    /**
     * Get notification statistics
    */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $stats = Cache::remember('notification_stats_global', 300, function () {
                return [
                    'total_notifications' => DB::table('notifications')->count(),
                    'unread_notifications' => DB::table('notifications')->where('read', false)->count(),
                    'notifications_by_type' => [
                        'appointment' => DB::table('notifications')->where('type', 'appointment')->count(),
                        'ticket' => DB::table('notifications')->where('type', 'ticket')->count(),
                        'system' => DB::table('notifications')->where('type', 'system')->count(),
                        'reminder' => DB::table('notifications')->where('type', 'reminder')->count(),
                    ],
                    'notifications_by_priority' => [
                        'high' => DB::table('notifications')->where('priority', 'high')->count(),
                        'medium' => DB::table('notifications')->where('priority', 'medium')->count(),
                        'low' => DB::table('notifications')->where('priority', 'low')->count(),
                    ],
                    'recent_notifications' => DB::table('notifications')
                                                ->where('created_at', '>=', now()->subDays(7))
                                                ->count(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => ['stats' => $stats]
            ]);
        } catch (\Exception $e) {
            Log::error('Notification stats failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification statistics.',
            ], 500);
        }
    }

    /**
     * PRIVATE HELPER METHODS
     */
    private function applyFilters($query, Request $request): void
    {
        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->has('read_status')) {
            if ($request->read_status === 'unread') {
                $query->where('read', false);
            } elseif ($request->read_status === 'read') {
                $query->where('read', true);
            }
        }

        if ($request->has('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('message', 'LIKE', "%{$search}%");
            });
        }
    }

    private function getUnreadCountForUser(int $userId): int
    {
        $cacheKey = "unread_count.user.{$userId}";
        
        return Cache::remember($cacheKey, 30, function () use ($userId) {
            return DB::table('notifications')
                     ->where('user_id', $userId)
                     ->where('read', false)
                     ->count();
        });
    }

    private function clearUserNotificationCaches(int $userId): void
    {
        try {
            // Clear multiple cache keys related to this user
            $cacheKeys = [
                "unread_count.user.{$userId}",
                "user_notification_summary.{$userId}",
            ];
            
            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }
            
            // Clear tagged caches if Redis is available
            try {
                Cache::tags(['notifications', "user:{$userId}"])->flush();
            } catch (\Exception $e) {
                // Fallback if cache tags are not supported
                Log::debug('Cache tags not supported, using individual key clearing');
            }
        } catch (\Exception $e) {
            Log::warning('Cache clearing failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function clearMultipleUserCaches(array $userIds): void
    {
        try {
            foreach ($userIds as $userId) {
                $this->clearUserNotificationCaches($userId);
            }
        } catch (\Exception $e) {
            Log::warning('Bulk cache clearing failed', [
                'user_count' => count($userIds),
                'error' => $e->getMessage()
            ]);
        }
    }
}