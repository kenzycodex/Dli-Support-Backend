<?php
// app/Models/Resource.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resource extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'title',
        'description',
        'slug',
        'type',
        'subcategory',
        'difficulty',
        'duration',
        'external_url',
        'download_url',
        'thumbnail_url',
        'tags',
        'author_name',
        'author_bio',
        'rating',
        'download_count',
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
        'rating' => 'decimal:1',
        'download_count' => 'integer',
        'view_count' => 'integer',
        'sort_order' => 'integer',
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected $dates = ['deleted_at'];

    // Type constants
    const TYPE_ARTICLE = 'article';
    const TYPE_VIDEO = 'video';
    const TYPE_AUDIO = 'audio';
    const TYPE_EXERCISE = 'exercise';
    const TYPE_TOOL = 'tool';
    const TYPE_WORKSHEET = 'worksheet';

    // Difficulty constants
    const DIFFICULTY_BEGINNER = 'beginner';
    const DIFFICULTY_INTERMEDIATE = 'intermediate';
    const DIFFICULTY_ADVANCED = 'advanced';

    /**
     * Get the category that owns the resource
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ResourceCategory::class, 'category_id');
    }

    /**
     * Get the user who created this resource
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this resource
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get resource feedback/ratings
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(ResourceFeedback::class);
    }

    /**
     * Scope for published resources
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope for featured resources
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by difficulty
     */
    public function scopeByDifficulty($query, $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }

    /**
     * Scope for searching resources
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'LIKE', "%{$search}%")
              ->orWhere('description', 'LIKE', "%{$search}%")
              ->orWhere('author_name', 'LIKE', "%{$search}%")
              ->orWhereJsonContains('tags', $search);
        });
    }

    /**
     * Get available types
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_ARTICLE => 'Article',
            self::TYPE_VIDEO => 'Video',
            self::TYPE_AUDIO => 'Audio',
            self::TYPE_EXERCISE => 'Exercise',
            self::TYPE_TOOL => 'Tool',
            self::TYPE_WORKSHEET => 'Worksheet',
        ];
    }

    /**
     * Get available difficulties
     */
    public static function getAvailableDifficulties(): array
    {
        return [
            self::DIFFICULTY_BEGINNER => 'Beginner',
            self::DIFFICULTY_INTERMEDIATE => 'Intermediate',
            self::DIFFICULTY_ADVANCED => 'Advanced',
        ];
    }

    /**
     * Increment view count
     */
    public function incrementViews(): void
    {
        $this->increment('view_count');
    }

    /**
     * Increment download count
     */
    public function incrementDownloads(): void
    {
        $this->increment('download_count');
    }

    /**
     * Update rating based on feedback
     */
    public function updateRating(): void
    {
        $averageRating = $this->feedback()->avg('rating');
        $this->update(['rating' => round($averageRating, 1)]);
    }
}
