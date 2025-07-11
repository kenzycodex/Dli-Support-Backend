<?php
// app/Models/HelpAnalytics.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpAnalytics extends Model
{
    use HasFactory;

    protected $table = 'help_analytics';

    protected $fillable = [
        'type',
        'reference_id',
        'date',
        'count',
        'metadata',
    ];

    protected $casts = [
        'date' => 'date',
        'count' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Scope for search analytics
     */
    public function scopeSearches($query)
    {
        return $query->where('type', 'search');
    }

    /**
     * Scope for category clicks
     */
    public function scopeCategoryClicks($query)
    {
        return $query->where('type', 'category_click');
    }

    /**
     * Scope for FAQ views
     */
    public function scopeFaqViews($query)
    {
        return $query->where('type', 'faq_view');
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $startDate, $endDate = null)
    {
        $query->where('date', '>=', $startDate);
        
        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }
        
        return $query;
    }

    /**
     * Get top items by count
     */
    public function scopeTopByCount($query, $limit = 10)
    {
        return $query->orderBy('count', 'desc')->take($limit);
    }
}