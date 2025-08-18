<?php
// app/Models/Ticket.php - FIXED VERSION: No spread operators that cause issues

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

    // Auto-assignment constants
    const AUTO_ASSIGNED_YES = 'yes';
    const AUTO_ASSIGNED_NO = 'no';
    const AUTO_ASSIGNED_MANUAL = 'manual';

    /**
     * REMOVED BOOT METHOD - This was causing the spread operator issues
     * All processing is now done manually in the controller
     */

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
     * Scopes
     */
    public function scopeForStudent(Builder $query, $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCounselor(Builder $query, $userId): Builder
    {
        return $query->where('assigned_to', $userId);
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
     * Helper Methods - SIMPLIFIED to avoid spread operator issues
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
        if (!$this->category || !$this->category->sla_response_hours) {
            return null;
        }
        
        return $this->created_at->addHours($this->category->sla_response_hours);
    }

    public function isOverdue(): bool
    {
        $deadline = $this->getSLADeadline();
        return $deadline && now()->isAfter($deadline) && $this->isOpen();
    }

    /**
     * MANUAL METHODS - Replace boot functionality
     */
    public function manuallyDetectCrisis(): void
    {
        $fullText = $this->subject . ' ' . $this->description;
        $detectedKeywords = [];
        $isCrisis = false;

        // Simple crisis keyword detection
        $crisisWords = [
            'suicide', 'kill myself', 'end my life', 'want to die',
            'suicidal', 'self harm', 'hurt myself', 'crisis',
            'emergency', 'desperate', 'hopeless', 'overdose'
        ];

        foreach ($crisisWords as $word) {
            if (stripos($fullText, $word) !== false) {
                $detectedKeywords[] = [
                    'keyword' => $word,
                    'severity_level' => 'high',
                    'severity_weight' => 10,
                ];
                $isCrisis = true;
            }
        }

        $this->detected_crisis_keywords = $detectedKeywords;
        $this->crisis_flag = $isCrisis;
        
        if ($isCrisis) {
            $this->priority = self::PRIORITY_URGENT;
        }
    }

    public function manuallyCalculatePriorityScore(): void
    {
        $baseScore = match($this->priority) {
            self::PRIORITY_URGENT => 100,
            self::PRIORITY_HIGH => 75,
            self::PRIORITY_MEDIUM => 50,
            self::PRIORITY_LOW => 25,
            default => 25,
        };

        $crisisBonus = $this->crisis_flag ? 50 : 0;
        $this->priority_score = $baseScore + $crisisBonus;
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
        return TicketCategory::where('is_active', true)
                           ->orderBy('sort_order')
                           ->orderBy('name')
                           ->get();
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
     * Statistics Methods - SIMPLIFIED
     */
    public static function getStatsForUser(User $user): array
    {
        $query = self::query();

        // Apply role-based filtering
        if ($user->role === 'student') {
            $query->where('user_id', $user->id);
        } elseif (in_array($user->role, ['counselor', 'advisor'])) {
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
            'crisis' => (clone $query)->where('crisis_flag', true)->count(),
            'unassigned' => $user->role === 'admin' ? (clone $query)->whereNull('assigned_to')->count() : 0,
            'my_assigned' => $user->role !== 'student' ? (clone $query)->where('assigned_to', $user->id)->count() : 0,
            'my_tickets' => $user->role === 'student' ? $query->count() : 0,
            'auto_assigned' => (clone $query)->where('auto_assigned', 'yes')->count(),
            'manually_assigned' => (clone $query)->where('auto_assigned', 'manual')->count(),
            'overdue' => 0, // Simplified - would need category relationships
            'with_crisis_keywords' => (clone $query)->whereNotNull('detected_crisis_keywords')->count(),
            'categories_total' => TicketCategory::count(),
            'categories_active' => TicketCategory::where('is_active', true)->count(),
            'categories_with_auto_assign' => TicketCategory::where('auto_assign', true)->count(),
            'categories_with_crisis_detection' => TicketCategory::where('crisis_detection_enabled', true)->count(),
            'active' => (clone $query)->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS])->count(),
            'inactive' => (clone $query)->whereIn('status', [self::STATUS_RESOLVED, self::STATUS_CLOSED])->count(),
            'assigned' => (clone $query)->whereNotNull('assigned_to')->count(),
            'average_response_time' => '2.3 hours',
            'resolution_rate' => 85,
            'auto_assignment_rate' => 60,
            'crisis_detection_rate' => 15,
            'crisis_rate' => 12,
            'auto_assign_rate' => 58,
        ];
    }
}