<?php
// app/Models/TicketCategory.php - FIXED with proper auto-assignment methods

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    // FIXED: Auto-assignment helper methods
    public function getAvailableCounselors(): \Illuminate\Support\Collection
    {
        try {
            return $this->counselorSpecializations()
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
                ->get();
        } catch (\Exception $e) {
            Log::error('Error getting available counselors', [
                'category_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return collect([]);
        }
    }

    public function getBestAvailableCounselor(): ?User
    {
        try {
            $specialization = $this->counselorSpecializations()
                ->with('user')
                ->where('is_available', true)
                ->whereColumn('current_workload', '<', 'max_workload')
                ->whereHas('user', function ($query) {
                    $query->where('status', 'active')
                          ->whereIn('role', ['counselor', 'advisor']);
                })
                ->orderByRaw("
                    CASE priority_level 
                        WHEN 'primary' THEN 1 
                        WHEN 'secondary' THEN 2 
                        WHEN 'backup' THEN 3 
                        ELSE 4 
                    END
                ")
                ->orderBy('current_workload')
                ->orderByDesc('expertise_rating')
                ->first();

            return $specialization?->user;
        } catch (\Exception $e) {
            Log::error('Error getting best available counselor', [
                'category_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function incrementWorkload(User $counselor): void
    {
        try {
            $this->counselorSpecializations()
                ->where('user_id', $counselor->id)
                ->increment('current_workload');
                
            Log::info('Incremented workload for counselor', [
                'category_id' => $this->id,
                'counselor_id' => $counselor->id,
                'counselor_name' => $counselor->name
            ]);
        } catch (\Exception $e) {
            Log::error('Error incrementing workload', [
                'category_id' => $this->id,
                'counselor_id' => $counselor->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function decrementWorkload(User $counselor): void
    {
        try {
            $this->counselorSpecializations()
                ->where('user_id', $counselor->id)
                ->where('current_workload', '>', 0)
                ->decrement('current_workload');
                
            Log::info('Decremented workload for counselor', [
                'category_id' => $this->id,
                'counselor_id' => $counselor->id,
                'counselor_name' => $counselor->name
            ]);
        } catch (\Exception $e) {
            Log::error('Error decrementing workload', [
                'category_id' => $this->id,
                'counselor_id' => $counselor->id,
                'error' => $e->getMessage()
            ]);
        }
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

    // FIXED: Test auto-assignment capability
    public function canAutoAssign(): bool
    {
        if (!$this->auto_assign) {
            return false;
        }

        return $this->getAvailableCounselors()->isNotEmpty();
    }

    // FIXED: Get auto-assignment stats
    public function getAutoAssignmentStats(): array
    {
        try {
            $specializations = $this->counselorSpecializations()->get();
            
            return [
                'auto_assign_enabled' => $this->auto_assign,
                'total_counselors' => $specializations->count(),
                'available_counselors' => $specializations->where('is_available', true)->count(),
                'counselors_with_capacity' => $specializations->filter(function ($spec) {
                    return $spec->is_available && $spec->current_workload < $spec->max_workload;
                })->count(),
                'total_capacity' => $specializations->sum('max_workload'),
                'current_utilization' => $specializations->sum('current_workload'),
                'can_auto_assign' => $this->canAutoAssign(),
            ];
        } catch (\Exception $e) {
            Log::error('Error getting auto-assignment stats', [
                'category_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'auto_assign_enabled' => $this->auto_assign,
                'total_counselors' => 0,
                'available_counselors' => 0,
                'counselors_with_capacity' => 0,
                'total_capacity' => 0,
                'current_utilization' => 0,
                'can_auto_assign' => false,
            ];
        }
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

    // FIXED: Debug auto-assignment for a category
    public function debugAutoAssignment(): array
    {
        try {
            $debug = [
                'category_info' => [
                    'id' => $this->id,
                    'name' => $this->name,
                    'auto_assign' => $this->auto_assign,
                    'is_active' => $this->is_active,
                ],
                'specializations' => [],
                'available_counselors' => [],
                'best_counselor' => null,
                'can_auto_assign' => false,
            ];

            // Get all specializations for this category
            $specializations = $this->counselorSpecializations()
                ->with('user:id,name,email,role,status')
                ->get();

            foreach ($specializations as $spec) {
                $debug['specializations'][] = [
                    'id' => $spec->id,
                    'user_id' => $spec->user_id,
                    'user_name' => $spec->user->name,
                    'user_role' => $spec->user->role,
                    'user_status' => $spec->user->status,
                    'priority_level' => $spec->priority_level,
                    'is_available' => $spec->is_available,
                    'current_workload' => $spec->current_workload,
                    'max_workload' => $spec->max_workload,
                    'has_capacity' => $spec->current_workload < $spec->max_workload,
                    'can_take_ticket' => $spec->is_available && 
                                       $spec->current_workload < $spec->max_workload && 
                                       $spec->user->status === 'active',
                ];
            }

            // Get available counselors
            $available = $this->getAvailableCounselors();
            foreach ($available as $spec) {
                $debug['available_counselors'][] = [
                    'name' => $spec->user->name,
                    'workload' => "{$spec->current_workload}/{$spec->max_workload}",
                    'priority' => $spec->priority_level,
                    'expertise' => $spec->expertise_rating,
                ];
            }

            // Get best counselor
            $best = $this->getBestAvailableCounselor();
            if ($best) {
                $debug['best_counselor'] = [
                    'id' => $best->id,
                    'name' => $best->name,
                    'email' => $best->email,
                    'role' => $best->role,
                ];
            }

            $debug['can_auto_assign'] = $this->canAutoAssign();

            return $debug;

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'category_id' => $this->id,
            ];
        }
    }
}