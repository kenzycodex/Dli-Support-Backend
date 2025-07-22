<?php
// app/Models/TicketCategory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class TicketCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'sort_order',
        'is_active',
        'auto_assign',
        'crisis_detection_enabled',
        'sla_response_hours',
        'max_priority_level',
        'notification_settings',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_assign' => 'boolean',
        'crisis_detection_enabled' => 'boolean',
        'notification_settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }

    public function counselorSpecializations(): HasMany
    {
        return $this->hasMany(CounselorSpecialization::class, 'category_id');
    }

    public function crisisKeywords(): HasMany
    {
        return $this->hasMany(CrisisKeyword::class, 'category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithAutoAssign(Builder $query): Builder
    {
        return $query->where('auto_assign', true);
    }

    public function scopeWithCrisisDetection(Builder $query): Builder
    {
        return $query->where('crisis_detection_enabled', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Helper methods
    public function getAvailableCounselors(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->counselorSpecializations()
            ->with('user')
            ->where('is_available', true)
            ->whereHas('user', function ($query) {
                $query->where('status', 'active')
                      ->whereIn('role', ['counselor', 'advisor']);
            })
            ->orderBy('priority_level')
            ->orderBy('current_workload')
            ->get()
            ->pluck('user');
    }

    public function getBestAvailableCounselor(): ?User
    {
        $specialization = $this->counselorSpecializations()
            ->with('user')
            ->where('is_available', true)
            ->whereColumn('current_workload', '<', 'max_workload')
            ->whereHas('user', function ($query) {
                $query->where('status', 'active')
                      ->whereIn('role', ['counselor', 'advisor']);
            })
            ->orderBy('priority_level')
            ->orderBy('current_workload')
            ->orderByDesc('expertise_rating')
            ->first();

        return $specialization?->user;
    }

    public function incrementWorkload(User $counselor): void
    {
        $this->counselorSpecializations()
            ->where('user_id', $counselor->id)
            ->increment('current_workload');
    }

    public function decrementWorkload(User $counselor): void
    {
        $this->counselorSpecializations()
            ->where('user_id', $counselor->id)
            ->where('current_workload', '>', 0)
            ->decrement('current_workload');
    }

    public function getActiveTicketsCount(): int
    {
        return $this->tickets()
            ->whereIn('status', ['Open', 'In Progress'])
            ->count();
    }

    public function getCrisisKeywords(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->crisisKeywords()
            ->where('is_active', true)
            ->orderBy('severity_level')
            ->get();
    }

    // Static helper methods
    public static function getForDropdown(): array
    {
        return static::active()
            ->ordered()
            ->pluck('name', 'id')
            ->toArray();
    }

    public static function getWithCounselors(): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->with(['counselorSpecializations.user'])
            ->ordered()
            ->get();
    }
}