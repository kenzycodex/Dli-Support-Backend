<?php

// app/Models/CounselorSpecialization.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class CounselorSpecialization extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'priority_level',
        'max_workload',
        'current_workload',
        'is_available',
        'availability_schedule',
        'expertise_rating',
        'notes',
        'assigned_by',
        'assigned_at',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'availability_schedule' => 'array',
        'expertise_rating' => 'decimal:2',
        'assigned_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // Scopes
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    public function scopeNotAtCapacity(Builder $query): Builder
    {
        return $query->whereColumn('current_workload', '<', 'max_workload');
    }

    public function scopeForCategory(Builder $query, $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeForUser(Builder $query, $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderByRaw("
            CASE priority_level 
                WHEN 'primary' THEN 1 
                WHEN 'secondary' THEN 2 
                WHEN 'backup' THEN 3 
                ELSE 4 
            END
        ");
    }

    public function scopeByWorkload(Builder $query): Builder
    {
        return $query->orderBy('current_workload');
    }

    public function scopeByExpertise(Builder $query): Builder
    {
        return $query->orderByDesc('expertise_rating');
    }

    // Helper methods
    public function isAtCapacity(): bool
    {
        return $this->current_workload >= $this->max_workload;
    }

    public function getWorkloadPercentage(): float
    {
        if ($this->max_workload <= 0) return 0;
        return round(($this->current_workload / $this->max_workload) * 100, 2);
    }

    public function incrementWorkload(): void
    {
        $this->increment('current_workload');
    }

    public function decrementWorkload(): void
    {
        if ($this->current_workload > 0) {
            $this->decrement('current_workload');
        }
    }

    public function canTakeTicket(): bool
    {
        return $this->is_available && !$this->isAtCapacity();
    }

    public function getPriorityWeight(): int
    {
        return match($this->priority_level) {
            'primary' => 100,
            'secondary' => 50,
            'backup' => 25,
            default => 1,
        };
    }

    public function getAssignmentScore(): float
    {
        if (!$this->canTakeTicket()) return 0;
        
        $priorityWeight = $this->getPriorityWeight();
        $workloadFactor = 1 - ($this->current_workload / $this->max_workload);
        $expertiseFactor = $this->expertise_rating / 5;
        
        return $priorityWeight * $workloadFactor * $expertiseFactor;
    }
}
