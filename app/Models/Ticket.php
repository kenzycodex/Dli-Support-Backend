<?php
// app/Models/Ticket.php (Enhanced with role-based features)

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'user_id',
        'subject',
        'description',
        'category',
        'priority',
        'status',
        'assigned_to',
        'crisis_flag',
        'tags',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'crisis_flag' => 'boolean',
        'tags' => 'array',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Category constants
    const CATEGORY_GENERAL = 'general';
    const CATEGORY_ACADEMIC = 'academic';
    const CATEGORY_MENTAL_HEALTH = 'mental-health';
    const CATEGORY_CRISIS = 'crisis';
    const CATEGORY_TECHNICAL = 'technical';
    const CATEGORY_OTHER = 'other';

    // Priority constants
    const PRIORITY_LOW = 'Low';
    const PRIORITY_MEDIUM = 'Medium';
    const PRIORITY_HIGH = 'High';
    const PRIORITY_URGENT = 'Urgent';

    // Status constants
    const STATUS_OPEN = 'Open';
    const STATUS_IN_PROGRESS = 'In Progress';
    const STATUS_RESOLVED = 'Resolved';
    const STATUS_CLOSED = 'Closed';

    // Tag constants
    const TAG_URGENT = 'urgent';
    const TAG_FOLLOW_UP = 'follow-up';
    const TAG_ESCALATED = 'escalated';
    const TAG_REVIEWED = 'reviewed';

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (!$ticket->ticket_number) {
                $ticket->ticket_number = self::generateTicketNumber();
            }
            
            // Auto-set priority based on category
            if ($ticket->category === self::CATEGORY_CRISIS && $ticket->priority !== self::PRIORITY_URGENT) {
                $ticket->priority = self::PRIORITY_URGENT;
                $ticket->crisis_flag = true;
            }
        });

        static::updated(function ($ticket) {
            if ($ticket->isDirty('status')) {
                $ticket->notifyStatusChange();
                
                // Set timestamps based on status
                if ($ticket->status === self::STATUS_RESOLVED && !$ticket->resolved_at) {
                    $ticket->resolved_at = now();
                }
                if ($ticket->status === self::STATUS_CLOSED && !$ticket->closed_at) {
                    $ticket->closed_at = now();
                }
            }
        });
    }

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(TicketResponse::class)->orderBy('created_at', 'asc');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function publicResponses(): HasMany
    {
        return $this->hasMany(TicketResponse::class)
                   ->where('is_internal', false)
                   ->orderBy('created_at', 'asc');
    }

    public function internalResponses(): HasMany
    {
        return $this->hasMany(TicketResponse::class)
                   ->where('is_internal', true)
                   ->orderBy('created_at', 'asc');
    }

    /**
     * Role-based scopes
     */
    public function scopeForStudent(Builder $query, $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCounselor(Builder $query, $userId): Builder
    {
        return $query->where('assigned_to', $userId)
                    ->whereIn('category', [
                        self::CATEGORY_MENTAL_HEALTH,
                        self::CATEGORY_CRISIS
                    ]);
    }

    public function scopeForAdvisor(Builder $query, $userId): Builder
    {
        return $query->where('assigned_to', $userId)
                    ->whereIn('category', [
                        self::CATEGORY_GENERAL,
                        self::CATEGORY_ACADEMIC,
                        self::CATEGORY_OTHER
                    ]);
    }

    public function scopeForAdmin(Builder $query): Builder
    {
        return $query; // Admins see all tickets
    }

    /**
     * Status and category scopes
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_RESOLVED, self::STATUS_CLOSED]);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    public function scopeAssignedTo(Builder $query, $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeCrisis(Builder $query): Builder
    {
        return $query->where('crisis_flag', true);
    }

    public function scopeWithTag(Builder $query, string $tag): Builder
    {
        return $query->whereJsonContains('tags', $tag);
    }

    /**
     * Role-based access methods
     */
    public function canBeViewedBy(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isStudent()) {
            return $this->user_id === $user->id;
        }

        if ($user->isCounselor() || $user->isAdvisor()) {
            return $this->assigned_to === $user->id;
        }

        return false;
    }

    public function canBeModifiedBy(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isStudent()) {
            return $this->user_id === $user->id && $this->isOpen();
        }

        if ($user->isCounselor() || $user->isAdvisor()) {
            return $this->assigned_to === $user->id;
        }

        return false;
    }

    public function canBeAssignedBy(User $user): bool
    {
        return $user->isAdmin() || ($user->isStaff() && $this->assigned_to === $user->id);
    }

    /**
     * Status check methods
     */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED]);
    }

    public function isAssigned(): bool
    {
        return !is_null($this->assigned_to);
    }

    public function isUrgent(): bool
    {
        return $this->priority === self::PRIORITY_URGENT || $this->crisis_flag;
    }

    /**
     * Tag management
     */
    public function addTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }
    }

    public function removeTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        $tags = array_filter($tags, fn($t) => $t !== $tag);
        $this->update(['tags' => array_values($tags)]);
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? []);
    }

    /**
     * Assignment methods
     */
    public function assignTo($userId): void
    {
        $this->update([
            'assigned_to' => $userId,
            'status' => $this->status === self::STATUS_OPEN ? self::STATUS_IN_PROGRESS : $this->status
        ]);

        // Create notification
        Notification::createForUser(
            $this->user_id,
            Notification::TYPE_TICKET,
            'Ticket Assigned',
            "Your ticket #{$this->ticket_number} has been assigned to a staff member.",
            Notification::PRIORITY_MEDIUM,
            ['ticket_id' => $this->id]
        );
    }

    public function unassign(): void
    {
        $this->update([
            'assigned_to' => null,
            'status' => self::STATUS_OPEN
        ]);
    }

    /**
     * Status management
     */
    public function markInProgress(): void
    {
        $this->update(['status' => self::STATUS_IN_PROGRESS]);
    }

    public function resolve(): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_at' => now()
        ]);
    }

    public function close(): void
    {
        $this->update([
            'status' => self::STATUS_CLOSED,
            'closed_at' => now(),
            'resolved_at' => $this->resolved_at ?: now()
        ]);
    }

    public function reopen(): void
    {
        $this->update([
            'status' => self::STATUS_OPEN,
            'resolved_at' => null,
            'closed_at' => null
        ]);
    }

    /**
     * Auto-assignment based on category and role
     */
    public function autoAssign(): void
    {
        $roleMap = [
            self::CATEGORY_MENTAL_HEALTH => User::ROLE_COUNSELOR,
            self::CATEGORY_CRISIS => User::ROLE_COUNSELOR,
            self::CATEGORY_ACADEMIC => User::ROLE_ADVISOR,
            self::CATEGORY_GENERAL => User::ROLE_ADVISOR,
        ];

        if (isset($roleMap[$this->category])) {
            $targetRole = $roleMap[$this->category];
            
            // Find available staff with least workload
            $availableStaff = User::where('role', $targetRole)
                                 ->where('status', User::STATUS_ACTIVE)
                                 ->withCount(['assignedTickets' => function ($query) {
                                     $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
                                 }])
                                 ->orderBy('assigned_tickets_count', 'asc')
                                 ->first();

            if ($availableStaff) {
                $this->assignTo($availableStaff->id);
            }
        }
    }

    /**
     * Static helper methods
     */
    public static function generateTicketNumber(): string
    {
        do {
            $number = 'T' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        } while (self::where('ticket_number', $number)->exists());

        return $number;
    }

    public static function getAvailableCategories(): array
    {
        return [
            self::CATEGORY_GENERAL => 'General Inquiry',
            self::CATEGORY_ACADEMIC => 'Academic Help',
            self::CATEGORY_MENTAL_HEALTH => 'Mental Health',
            self::CATEGORY_CRISIS => 'Crisis Support',
            self::CATEGORY_TECHNICAL => 'Technical Issue',
            self::CATEGORY_OTHER => 'Other',
        ];
    }

    public static function getAvailablePriorities(): array
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_URGENT => 'Urgent',
        ];
    }

    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_OPEN => 'Open',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_RESOLVED => 'Resolved',
            self::STATUS_CLOSED => 'Closed',
        ];
    }

    public static function getAvailableTags(): array
    {
        return [
            self::TAG_URGENT => 'Urgent',
            self::TAG_FOLLOW_UP => 'Follow-up',
            self::TAG_ESCALATED => 'Escalated',
            self::TAG_REVIEWED => 'Reviewed',
        ];
    }

    /**
     * Get tickets statistics for role
     */
    public static function getStatsForUser(User $user): array
    {
        $query = self::query();

        // Apply role-based filtering
        if ($user->isStudent()) {
            $query->forStudent($user->id);
        } elseif ($user->isCounselor()) {
            $query->forCounselor($user->id);
        } elseif ($user->isAdvisor()) {
            $query->forAdvisor($user->id);
        }
        // Admin sees all tickets by default

        return [
            'total' => $query->count(),
            'open' => (clone $query)->byStatus(self::STATUS_OPEN)->count(),
            'in_progress' => (clone $query)->byStatus(self::STATUS_IN_PROGRESS)->count(),
            'resolved' => (clone $query)->byStatus(self::STATUS_RESOLVED)->count(),
            'closed' => (clone $query)->byStatus(self::STATUS_CLOSED)->count(),
            'high_priority' => (clone $query)->byPriority(self::PRIORITY_HIGH)->count(),
            'urgent' => (clone $query)->byPriority(self::PRIORITY_URGENT)->count(),
            'crisis' => (clone $query)->crisis()->count(),
            'unassigned' => $user->isAdmin() ? (clone $query)->unassigned()->count() : 0,
        ];
    }

    /**
     * Notify about status change
     */
    private function notifyStatusChange(): void
    {
        $statusMessages = [
            self::STATUS_IN_PROGRESS => 'Your ticket is now being processed.',
            self::STATUS_RESOLVED => 'Your ticket has been resolved.',
            self::STATUS_CLOSED => 'Your ticket has been closed.',
        ];

        if (isset($statusMessages[$this->status])) {
            Notification::createForUser(
                $this->user_id,
                Notification::TYPE_TICKET,
                'Ticket Status Update',
                "Ticket #{$this->ticket_number}: {$statusMessages[$this->status]}",
                $this->isUrgent() ? Notification::PRIORITY_HIGH : Notification::PRIORITY_MEDIUM,
                ['ticket_id' => $this->id]
            );
        }
    }
}