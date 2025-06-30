<?php
// app/Models/Notification.php

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

    // Type constants
    const TYPE_APPOINTMENT = 'appointment';
    const TYPE_TICKET = 'ticket';
    const TYPE_SYSTEM = 'system';
    const TYPE_REMINDER = 'reminder';

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
     * Get available types
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_APPOINTMENT => 'Appointment',
            self::TYPE_TICKET => 'Ticket',
            self::TYPE_SYSTEM => 'System',
            self::TYPE_REMINDER => 'Reminder',
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
        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'priority' => $priority,
            'data' => $data,
        ]);
    }

    /**
     * Create notifications for multiple users
     */
    public static function createForUsers(array $userIds, $type, $title, $message, $priority = 'medium', $data = null)
    {
        $notifications = [];
        foreach ($userIds as $userId) {
            $notifications[] = [
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'priority' => $priority,
                'data' => $data,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return self::insert($notifications);
    }
}