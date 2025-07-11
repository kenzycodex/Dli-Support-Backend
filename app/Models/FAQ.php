<?php
// app/Models/FAQ.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FAQ extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'faqs';

    protected $fillable = [
        'category_id',
        'question',
        'answer',
        'slug',
        'tags',
        'helpful_count',
        'not_helpful_count',
        'view_count',
        'sort_order',
        'is_published',
        'is_featured',
        'created_by',
        'updated_by',
        'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
        'view_count' => 'integer',
        'sort_order' => 'integer',
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Get the category that owns the FAQ
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpCategory::class, 'category_id');
    }

    /**
     * Get the user who created this FAQ
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this FAQ
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get FAQ feedback
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(FAQFeedback::class);
    }

    /**
     * Scope for published FAQs
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope for featured FAQs
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for searching FAQs
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('question', 'LIKE', "%{$search}%")
              ->orWhere('answer', 'LIKE', "%{$search}%")
              ->orWhereJsonContains('tags', $search);
        });
    }

    /**
     * Get helpfulness percentage
     */
    public function getHelpfulnessRateAttribute(): float
    {
        $total = $this->helpful_count + $this->not_helpful_count;
        return $total > 0 ? round(($this->helpful_count / $total) * 100, 1) : 0;
    }

    /**
     * Increment view count
     */
    public function incrementViews(): void
    {
        $this->increment('view_count');
    }

    /**
     * Add helpful feedback
     */
    public function addHelpfulFeedback(): void
    {
        $this->increment('helpful_count');
    }

    /**
     * Add not helpful feedback
     */
    public function addNotHelpfulFeedback(): void
    {
        $this->increment('not_helpful_count');
    }
}