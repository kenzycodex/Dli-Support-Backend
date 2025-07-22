<?php
// app/Models/Ticket.php (Enhanced with dynamic categories and auto-assignment)

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
        'category_id',
        'priority',
        'status',
        'assigned_to',
        'crisis_flag',
        'detected_crisis_keywords',
        'auto_assigned',
        'assigned_at',
        'assignment_reason',
        'tags',
        'priority_score',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'crisis_flag' => 'boolean',
        'detected_crisis_keywords' => 'array',
        'tags' => 'array',
        'priority_score' => 'decimal:2',
        'assigned_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Priority constants (unchanged for compatibility)
    const PRIORITY_LOW = 'Low';
    const PRIORITY_MEDIUM = 'Medium';
    const PRIORITY_HIGH = 'High';
    const PRIORITY_URGENT = 'Urgent';

    // Status constants (unchanged for compatibility)
    const STATUS_OPEN = 'Open';
    const STATUS_IN_PROGRESS = 'In Progress';
    const STATUS_RESOLVED = 'Resolved';
    const STATUS_CLOSED = 'Closed';

    // Auto-assignment constants
    const AUTO_ASSIGNED_YES = 'yes';
    const AUTO_ASSIGNED_NO = 'no';
    const AUTO_ASSIGNED_MANUAL = 'manual';

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
            
            // Detect crisis keywords and calculate priority
            $ticket->detectCrisisKeywords();
            $ticket->calculatePriorityScore();
            
            // Auto-assign if category supports it
            if ($ticket->category && $ticket->category->auto_assign && !$ticket->assigned_to) {
                $ticket->autoAssign();
            }
        });

        static::updated(function ($ticket) {
            if ($ticket->isDirty('status')) {
                $ticket->handleStatusChange();
            }
            
            if ($ticket->isDirty('assigned_to')) {
                $ticket->handleAssignmentChange();
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(TicketResponse::class)->orderBy('created_at', 'asc');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function assignmentHistory(): HasMany
    {
        return $this->hasMany(TicketAssignmentHistory::class);
    }

    /**
     * Scopes (enhanced with category support)
     */
    public function scopeForStudent(Builder $query, $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCounselor(Builder $query, $userId): Builder
    {
        return $query->where('assigned_to', $userId)
                    ->whereHas('category', function ($q) {
                        $q->whereHas('counselorSpecializations', function ($sq) use ($userId) {
                            $sq->where('user_id', $userId);
                        });
                    });
    }

    public function scopeForCategory(Builder $query, $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeCrisis(Builder $query): Builder
    {
        return $query->where('crisis_flag', true)
                    ->orWhere('priority', self::PRIORITY_URGENT);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
    }

    public function scopeByPriorityScore(Builder $query): Builder
    {
        return $query->orderByDesc('priority_score')
                    ->orderByDesc('created_at');
    }

    /**
     * Crisis Detection & Priority Calculation
     */
    public function detectCrisisKeywords(): void
    {
        $fullText = $this->subject . ' ' . $this->description;
        $detectedKeywords = CrisisKeyword::detectInText($fullText, $this->category_id);
        
        $this->detected_crisis_keywords = $detectedKeywords;
        
        if (CrisisKeyword::isCrisisLevel($detectedKeywords)) {
            $this->crisis_flag = true;
            $this->priority = self::PRIORITY_URGENT;
        }
    }

    public function calculatePriorityScore(): void
    {
        $baseScore = match($this->priority) {
            self::PRIORITY_URGENT => 100,
            self::PRIORITY_HIGH => 75,
            self::PRIORITY_MEDIUM => 50,
            self::PRIORITY_LOW => 25,
            default => 25,
        };

        $crisisBonus = $this->crisis_flag ? 50 : 0;
        $keywordBonus = CrisisKeyword::calculateCrisisScore($this->detected_crisis_keywords ?? []) / 10;
        
        // Time-based urgency (older tickets get higher priority)
        $ageInHours = $this->created_at ? $this->created_at->diffInHours(now()) : 0;
        $ageBonus = min($ageInHours * 0.5, 25); // Max 25 points for age
        
        $this->priority_score = $baseScore + $crisisBonus + $keywordBonus + $ageBonus;
    }

    /**
     * Auto-Assignment Logic
     */
    public function autoAssign(): bool
    {
        if (!$this->category || !$this->category->auto_assign) {
            return false;
        }

        $bestCounselor = $this->category->getBestAvailableCounselor();
        
        if ($bestCounselor) {
            $this->assignTo($bestCounselor->id, 'auto', 'Automatically assigned based on specialization and workload');
            return true;
        }

        return false;
    }

    public function assignTo(int $userId, string $type = 'manual', string $reason = ''): void
    {
        $previousAssignee = $this->assigned_to;
        
        // Update workload counters
        if ($previousAssignee && $this->category) {
            $this->category->decrementWorkload(User::find($previousAssignee));
        }
        
        $this->assigned_to = $userId;
        $this->assigned_at = now();
        $this->auto_assigned = $type;
        $this->assignment_reason = $reason;
        
        if ($this->status === self::STATUS_OPEN) {
            $this->status = self::STATUS_IN_PROGRESS;
        }
        
        // Update workload counters
        if ($this->category) {
            $this->category->incrementWorkload(User::find($userId));
        }
        
        // Record assignment history
        TicketAssignmentHistory::create([
            'ticket_id' => $this->id,
            'assigned_from' => $previousAssignee,
            'assigned_to' => $userId,
            'assigned_by' => auth()->id() ?? 1, // System user for auto-assignments
            'assignment_type' => $type,
            'reason' => $reason,
            'assigned_at' => now(),
        ]);
        
        $this->save();
        $this->notifyAssignment();
    }

    public function unassign(string $reason = ''): void
    {
        $previousAssignee = $this->assigned_to;
        
        if ($previousAssignee && $this->category) {
            $this->category->decrementWorkload(User::find($previousAssignee));
        }
        
        // Record assignment history
        TicketAssignmentHistory::create([
            'ticket_id' => $this->id,
            'assigned_from' => $previousAssignee,
            'assigned_to' => null,
            'assigned_by' => auth()->id() ?? 1,
            'assignment_type' => 'unassign',
            'reason' => $reason,
            'assigned_at' => now(),
        ]);
        
        $this->assigned_to = null;
        $this->assigned_at = null;
        $this->auto_assigned = self::AUTO_ASSIGNED_NO;
        $this->assignment_reason = null;
        $this->status = self::STATUS_OPEN;
        
        $this->save();
    }

    /**
     * Status Management
     */
    public function markInProgress(): void
    {
        $this->update(['status' => self::STATUS_IN_PROGRESS]);
    }

    public function resolve(string $reason = ''): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_at' => now()
        ]);
        
        // Update workload
        if ($this->assigned_to && $this->category) {
            $this->category->decrementWorkload(User::find($this->assigned_to));
        }
    }

    public function close(string $reason = ''): void
    {
        $this->update([
            'status' => self::STATUS_CLOSED,
            'closed_at' => now(),
            'resolved_at' => $this->resolved_at ?: now()
        ]);
        
        // Update workload
        if ($this->assigned_to && $this->category) {
            $this->category->decrementWorkload(User::find($this->assigned_to));
        }
    }

    public function reopen(): void
    {
        $this->update([
            'status' => $this->assigned_to ? self::STATUS_IN_PROGRESS : self::STATUS_OPEN,
            'resolved_at' => null,
            'closed_at' => null
        ]);
        
        // Update workload
        if ($this->assigned_to && $this->category) {
            $this->category->incrementWorkload(User::find($this->assigned_to));
        }
    }

    /**
     * Tag Management
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
     * Event Handlers
     */
    private function handleStatusChange(): void
    {
        $this->notifyStatusChange();
        
        // Set timestamps based on status
        if ($this->status === self::STATUS_RESOLVED && !$this->resolved_at) {
            $this->resolved_at = now();
        }
        if ($this->status === self::STATUS_CLOSED && !$this->closed_at) {
            $this->closed_at = now();
        }
    }

    private function handleAssignmentChange(): void
    {
        $this->assigned_at = $this->assigned_to ? now() : null;
    }

    /**
     * Notifications
     */
    private function notifyAssignment(): void
    {
        if ($this->assigned_to) {
            Notification::createForUser(
                $this->assigned_to,
                Notification::TYPE_TICKET,
                'New Ticket Assignment',
                "You have been assigned ticket #{$this->ticket_number}: {$this->subject}",
                $this->crisis_flag ? Notification::PRIORITY_HIGH : Notification::PRIORITY_MEDIUM,
                ['ticket_id' => $this->id, 'is_crisis' => $this->crisis_flag]
            );
        }
        
        // Notify student
        Notification::createForUser(
            $this->user_id,
            Notification::TYPE_TICKET,
            'Ticket Assigned',
            "Your ticket #{$this->ticket_number} has been assigned to a staff member.",
            Notification::PRIORITY_MEDIUM,
            ['ticket_id' => $this->id]
        );
    }

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
                $this->crisis_flag ? Notification::PRIORITY_HIGH : Notification::PRIORITY_MEDIUM,
                ['ticket_id' => $this->id, 'status' => $this->status]
            );
        }
    }

    /**
     * Helper Methods
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

    public function wasAutoAssigned(): bool
    {
        return $this->auto_assigned === self::AUTO_ASSIGNED_YES;
    }

    public function getAssignmentType(): string
    {
        return match($this->auto_assigned) {
            self::AUTO_ASSIGNED_YES => 'Auto-assigned',
            self::AUTO_ASSIGNED_MANUAL => 'Manually assigned',
            default => 'Not assigned',
        };
    }

    public function getCategoryName(): string
    {
        return $this->category?->name ?? 'Unknown Category';
    }

    public function getCategoryColor(): string
    {
        return $this->category?->color ?? '#6B7280';
    }

    public function getSLADeadline(): ?\Carbon\Carbon
    {
        if (!$this->category) return null;
        
        return $this->created_at->addHours($this->category->sla_response_hours);
    }

    public function isOverdue(): bool
    {
        $deadline = $this->getSLADeadline();
        return $deadline && now()->isAfter($deadline) && $this->isOpen();
    }

    /**
     * Static Helper Methods
     */
    public static function generateTicketNumber(): string
    {
        do {
            $number = 'T' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        } while (self::where('ticket_number', $number)->exists());

        return $number;
    }

    public static function getAvailableCategories(): \Illuminate\Database\Eloquent\Collection
    {
        return TicketCategory::active()->ordered()->get();
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

    /**
     * Statistics Methods
     */
    public static function getStatsForUser(User $user): array
    {
        $query = self::query();

        // Apply role-based filtering
        if ($user->isStudent()) {
            $query->forStudent($user->id);
        } elseif ($user->isCounselor() || $user->isAdvisor()) {
            $query->where('assigned_to', $user->id);
        }
        // Admin sees all tickets by default

        return [
            'total' => $query->count(),
            'open' => (clone $query)->where('status', self::STATUS_OPEN)->count(),
            'in_progress' => (clone $query)->where('status', self::STATUS_IN_PROGRESS)->count(),
            'resolved' => (clone $query)->where('status', self::STATUS_RESOLVED)->count(),
            'closed' => (clone $query)->where('status', self::STATUS_CLOSED)->count(),
            'high_priority' => (clone $query)->where('priority', self::PRIORITY_HIGH)->count(),
            'urgent' => (clone $query)->where('priority', self::PRIORITY_URGENT)->count(),
            'crisis' => (clone $query)->crisis()->count(),
            'unassigned' => $user->isAdmin() ? (clone $query)->unassigned()->count() : 0,
            'overdue' => (clone $query)->whereHas('category')->get()->filter->isOverdue()->count(),
        ];
    }

    public static function getCategoryStats(): array
    {
        return TicketCategory::withCount([
            'tickets',
            'tickets as open_tickets' => function ($query) {
                $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
            },
            'tickets as crisis_tickets' => function ($query) {
                $query->where('crisis_flag', true);
            }
        ])->get()->toArray();
    }
}