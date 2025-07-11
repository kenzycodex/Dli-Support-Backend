<?php
// app/Models/HelpCategory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpCategory extends Model
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
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get all FAQs in this category
     */
    public function faqs(): HasMany
    {
        return $this->hasMany(FAQ::class, 'category_id')->orderBy('sort_order');
    }

    /**
     * Scope for active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered categories
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * Get category count with FAQs
     */
    public function getFaqCountAttribute(): int
    {
        return $this->faqs()->where('is_published', true)->count();
    }

    /**
     * Get analytics for this category
     */
    public function analytics(): HasMany
    {
        return $this->hasMany(HelpAnalytics::class, 'reference_id', 'slug')
                    ->where('type', 'category_click');
    }

    /**
     * Get total views for all FAQs in this category
     */
    public function getTotalViewsAttribute(): int
    {
        return $this->faqs()->sum('view_count');
    }

    /**
     * Get average helpfulness rate for FAQs in this category
     */
    public function getAverageHelpfulnessAttribute(): float
    {
        $faqs = $this->faqs()->published()->get();
        
        if ($faqs->isEmpty()) return 0;
        
        $totalRate = $faqs->sum(function ($faq) {
            return $faq->helpfulness_rate;
        });
        
        return round($totalRate / $faqs->count(), 1);
    }

    /**
     * Get most popular FAQ in this category
     */
    public function getMostPopularFaqAttribute(): ?FAQ
    {
        return $this->faqs()
                    ->published()
                    ->orderBy('view_count', 'desc')
                    ->first();
    }

    /**
     * Scope for categories with published FAQs
     */
    public function scopeWithPublishedFaqs($query)
    {
        return $query->whereHas('faqs', function ($q) {
            $q->published();
        });
    }

    /**
     * Get category performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        $faqs = $this->faqs()->published();
        
        return [
            'total_faqs' => $faqs->count(),
            'total_views' => $faqs->sum('view_count'),
            'total_helpful_votes' => $faqs->sum('helpful_count'),
            'total_unhelpful_votes' => $faqs->sum('not_helpful_count'),
            'average_helpfulness' => $this->average_helpfulness,
            'most_popular_faq' => $this->most_popular_faq,
        ];
    }
}