<?php

// app/Models/CrisisKeyword.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class CrisisKeyword extends Model
{
    use HasFactory;

    protected $fillable = [
        'keyword',
        'severity_level',
        'category_id',
        'is_active',
        'exact_match',
        'case_sensitive',
        'trigger_count',
        'response_action',
        'notification_rules',
        'created_by',
        'updated_by',
        'last_triggered_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'exact_match' => 'boolean',
        'case_sensitive' => 'boolean',
        'response_action' => 'array',
        'notification_rules' => 'array',
        'last_triggered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
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

    public function scopeForCategory(Builder $query, $categoryId): Builder
    {
        return $query->where(function ($q) use ($categoryId) {
            $q->where('category_id', $categoryId)
              ->orWhereNull('category_id'); // Global keywords
        });
    }

    public function scopeBySeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity_level', $severity);
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity_level', 'critical');
    }

    public function scopeHigh(Builder $query): Builder
    {
        return $query->where('severity_level', 'high');
    }

    // Detection methods
    public function matches(string $text): bool
    {
        $searchText = $this->case_sensitive ? $text : strtolower($text);
        $keyword = $this->case_sensitive ? $this->keyword : strtolower($this->keyword);

        if ($this->exact_match) {
            return str_contains($searchText, $keyword);
        } else {
            // Use word boundaries for partial matches to avoid false positives
            $pattern = '/\b' . preg_quote($keyword, '/') . '/';
            if (!$this->case_sensitive) {
                $pattern .= 'i';
            }
            return preg_match($pattern, $searchText) === 1;
        }
    }

    public function recordTrigger(): void
    {
        $this->increment('trigger_count');
        $this->update(['last_triggered_at' => now()]);
    }

    public function getSeverityWeight(): int
    {
        return match($this->severity_level) {
            'critical' => 1000,
            'high' => 100,
            'medium' => 10,
            'low' => 1,
            default => 1,
        };
    }

    public function getSeverityColor(): string
    {
        return match($this->severity_level) {
            'critical' => 'bg-red-100 text-red-800 border-red-200',
            'high' => 'bg-orange-100 text-orange-800 border-orange-200',
            'medium' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
            'low' => 'bg-blue-100 text-blue-800 border-blue-200',
            default => 'bg-gray-100 text-gray-800 border-gray-200',
        };
    }

    // Static detection methods
    public static function detectInText(string $text, ?int $categoryId = null): array
    {
        $detectedKeywords = [];
        
        $keywords = static::active()
            ->forCategory($categoryId)
            ->orderByDesc('severity_level')
            ->get();

        foreach ($keywords as $keyword) {
            if ($keyword->matches($text)) {
                $keyword->recordTrigger();
                $detectedKeywords[] = [
                    'id' => $keyword->id,
                    'keyword' => $keyword->keyword,
                    'severity_level' => $keyword->severity_level,
                    'severity_weight' => $keyword->getSeverityWeight(),
                    'category_id' => $keyword->category_id,
                ];
            }
        }

        return $detectedKeywords;
    }

    public static function calculateCrisisScore(array $detectedKeywords): int
    {
        return array_sum(array_column($detectedKeywords, 'severity_weight'));
    }

    public static function isCrisisLevel(array $detectedKeywords): bool
    {
        // Consider it crisis if any critical keywords or high-severity score
        $hasCritical = collect($detectedKeywords)->contains('severity_level', 'critical');
        $totalScore = static::calculateCrisisScore($detectedKeywords);
        
        return $hasCritical || $totalScore >= 100; // Configurable threshold
    }

    // Testing method for admin
    public static function testDetection(string $text, ?int $categoryId = null): array
    {
        $detected = static::detectInText($text, $categoryId);
        $crisisScore = static::calculateCrisisScore($detected);
        $isCrisis = static::isCrisisLevel($detected);
        
        return [
            'text' => $text,
            'detected_keywords' => $detected,
            'crisis_score' => $crisisScore,
            'is_crisis' => $isCrisis,
            'recommendation' => $isCrisis ? 'Immediate attention required' : 'Normal processing',
        ];
    }
}