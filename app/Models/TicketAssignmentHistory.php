<?php
// app/Models/TicketAssignmentHistory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class TicketAssignmentHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'assigned_from',
        'assigned_to',
        'assigned_by',
        'assignment_type',
        'reason',
        'assignment_criteria',
        'assigned_at',
    ];

    protected $casts = [
        'assignment_criteria' => 'array',
        'assigned_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Assignment type constants
    const TYPE_AUTO = 'auto';
    const TYPE_MANUAL = 'manual';
    const TYPE_TRANSFER = 'transfer';
    const TYPE_UNASSIGN = 'unassign';

    /**
     * Relationships
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function assignedFrom(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_from');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Scopes
     */
    public function scopeForTicket(Builder $query, int $ticketId): Builder
    {
        return $query->where('ticket_id', $ticketId);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId)
                    ->orWhere('assigned_from', $userId);
    }

    public function scopeAutoAssignments(Builder $query): Builder
    {
        return $query->where('assignment_type', self::TYPE_AUTO);
    }

    public function scopeManualAssignments(Builder $query): Builder
    {
        return $query->where('assignment_type', self::TYPE_MANUAL);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('assigned_at', '>=', now()->subDays($days));
    }

    /**
     * Helper Methods
     */
    public function getAssignmentTypeLabel(): string
    {
        return match($this->assignment_type) {
            self::TYPE_AUTO => 'Auto-assigned',
            self::TYPE_MANUAL => 'Manually assigned',
            self::TYPE_TRANSFER => 'Transferred',
            self::TYPE_UNASSIGN => 'Unassigned',
            default => 'Unknown',
        };
    }

    public function getAssignmentDescription(): string
    {
        if ($this->assignment_type === self::TYPE_UNASSIGN) {
            $fromUser = $this->assignedFrom?->name ?? 'Unknown';
            $byUser = $this->assignedBy?->name ?? 'System';
            return "Unassigned from {$fromUser} by {$byUser}";
        }

        $toUser = $this->assignedTo?->name ?? 'Unknown';
        $byUser = $this->assignedBy?->name ?? 'System';
        
        if ($this->assigned_from) {
            $fromUser = $this->assignedFrom?->name ?? 'Unknown';
            return "Transferred from {$fromUser} to {$toUser} by {$byUser}";
        }

        return "Assigned to {$toUser} by {$byUser}";
    }

    public function wasAutoAssigned(): bool
    {
        return $this->assignment_type === self::TYPE_AUTO;
    }

    /**
     * Static Methods
     */
    public static function getAssignmentStats(int $days = 30): array
    {
        $baseQuery = static::recent($days);

        return [
            'total_assignments' => (clone $baseQuery)->count(),
            'auto_assignments' => (clone $baseQuery)->autoAssignments()->count(),
            'manual_assignments' => (clone $baseQuery)->manualAssignments()->count(),
            'transfers' => (clone $baseQuery)->where('assignment_type', self::TYPE_TRANSFER)->count(),
            'unassignments' => (clone $baseQuery)->where('assignment_type', self::TYPE_UNASSIGN)->count(),
            'top_assigners' => (clone $baseQuery)
                ->selectRaw('assigned_by, count(*) as assignment_count')
                ->with('assignedBy:id,name')
                ->groupBy('assigned_by')
                ->orderByDesc('assignment_count')
                ->limit(5)
                ->get(),
            'assignment_trends' => static::getAssignmentTrends($days),
        ];
    }

    public static function getAssignmentTrends(int $days = 30): array
    {
        $trends = [];
        $startDate = now()->subDays($days);

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $trends[$date->format('Y-m-d')] = [
                'date' => $date->format('M j'),
                'auto' => static::where('assignment_type', self::TYPE_AUTO)
                    ->whereDate('assigned_at', $date)
                    ->count(),
                'manual' => static::where('assignment_type', self::TYPE_MANUAL)
                    ->whereDate('assigned_at', $date)
                    ->count(),
            ];
        }

        return array_values($trends);
    }

    public static function getCounselorWorkloadHistory(int $counselorId, int $days = 30): array
    {
        $assignments = static::where('assigned_to', $counselorId)
            ->recent($days)
            ->with(['ticket:id,ticket_number,subject,status,created_at'])
            ->orderByDesc('assigned_at')
            ->get();

        $unassignments = static::where('assigned_from', $counselorId)
            ->where('assignment_type', '!=', self::TYPE_TRANSFER)
            ->recent($days)
            ->with(['ticket:id,ticket_number,subject,status'])
            ->orderByDesc('assigned_at')
            ->get();

        return [
            'assignments' => $assignments,
            'unassignments' => $unassignments,
            'net_assignments' => $assignments->count() - $unassignments->count(),
            'assignment_rate' => round($assignments->count() / $days, 2),
        ];
    }
}