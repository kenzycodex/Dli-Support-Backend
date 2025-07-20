<?php
// app/Models/Notification.php (UPDATED - Enhanced for ticket system compatibility)

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'priority',
        'read',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Type constants (keeping your existing ones + ticket system requirements)
    const TYPE_APPOINTMENT = 'appointment';
    const TYPE_TICKET = 'ticket';
    const TYPE_SYSTEM = 'system';
    const TYPE_REMINDER = 'reminder';
    const TYPE_MESSAGE = 'message'; // Added for controller compatibility

    // Priority constants
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';

    /**
     * Get the user that owns the notification
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('read', false);
    }

    /**
     * Scope for read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('read', true);
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by priority
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead()
    {
        $this->update([
            'read' => true,
            'read_at' => now()
        ]);
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread()
    {
        $this->update([
            'read' => false,
            'read_at' => null
        ]);
    }

    /**
     * Check if notification is read - ADDED for controller compatibility
     */
    public function isRead(): bool
    {
        return (bool) $this->read;
    }

    /**
     * Get available types
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_APPOINTMENT => 'Appointment',
            self::TYPE_TICKET => 'Ticket',
            self::TYPE_SYSTEM => 'System',
            self::TYPE_REMINDER => 'Reminder',
            self::TYPE_MESSAGE => 'Message',
        ];
    }

    /**
     * Get available priorities
     */
    public static function getAvailablePriorities(): array
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High',
        ];
    }

    /**
     * Create a notification for a user
     */
    public static function createForUser($userId, $type, $title, $message, $priority = 'medium', $data = null)
    {
        try {
            return self::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'priority' => $priority,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to create notification', [
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Create notifications for multiple users (ENHANCED - Better error handling)
     */
    public static function createForUsers(array $userIds, $type, $title, $message, $priority = 'medium', $data = null)
    {
        $notifications = [];
        $now = now();
        
        foreach ($userIds as $userId) {
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

        try {
            self::insert($notifications);
            return count($notifications);
        } catch (\Exception $e) {
            \Log::error('Failed to create bulk notifications: ' . $e->getMessage());
            
            // Fallback: create individually
            $successCount = 0;
            foreach ($userIds as $userId) {
                try {
                    self::createForUser($userId, $type, $title, $message, $priority, $data);
                    $successCount++;
                } catch (\Exception $e) {
                    \Log::error('Failed to create notification for user ' . $userId . ': ' . $e->getMessage());
                }
            }
            return $successCount;
        }
    }

    /**
     * Get priority icon for UI
     */
    public function getPriorityIconAttribute(): string
    {
        switch ($this->priority) {
            case self::PRIORITY_HIGH:
                return '🔴';
            case self::PRIORITY_MEDIUM:
                return '🟡';
            case self::PRIORITY_LOW:
                return '🟢';
            default:
                return '⚪';
        }
    }

    /**
     * Get type icon for UI
     */
    public function getTypeIconAttribute(): string
    {
        switch ($this->type) {
            case self::TYPE_TICKET:
                return '🎫';
            case self::TYPE_APPOINTMENT:
                return '📅';
            case self::TYPE_SYSTEM:
                return '⚙️';
            case self::TYPE_REMINDER:
                return '⏰';
            case self::TYPE_MESSAGE:
                return '💬';
            default:
                return '📢';
        }
    }

    /**
     * Get time ago string
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get formatted data for display
     */
    public function getFormattedDataAttribute(): array
    {
        return $this->data ?? [];
    }

    /**
     * Scope for help-related notifications
     */
    public function scopeHelpRelated($query)
    {
        return $query->where('type', 'system')
                    ->where(function ($q) {
                        $q->where('title', 'like', '%FAQ%')
                        ->orWhere('title', 'like', '%Help%')
                        ->orWhere('title', 'like', '%Content%');
                    });
    }

    /**
     * Scope for content suggestion notifications
     */
    public function scopeContentSuggestions($query)
    {
        return $query->where('type', 'system')
                    ->where('title', 'like', '%Suggestion%');
    }

    /**
     * Mark notification as help system related
     */
    public function markAsHelpNotification(): void
    {
        $data = json_decode($this->data, true) ?? [];
        $data['category'] = 'help_system';
        $this->update(['data' => json_encode($data)]);
    }

    /**
     * Get notification metadata
     */
    public function getMetadataAttribute(): array
    {
        return json_decode($this->data, true) ?? [];
    }
}