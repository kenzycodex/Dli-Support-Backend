<?php
// app/Models/Resource.php (FIXED - Added missing methods and improvements)

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

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
        'rating' => 'decimal:2', // Changed to 2 decimal places for more precision
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
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug when creating
        static::creating(function ($resource) {
            if (empty($resource->slug)) {
                $resource->slug = Str::slug($resource->title);
            }
        });

        // Update slug when title changes
        static::updating(function ($resource) {
            if ($resource->isDirty('title') && empty($resource->slug)) {
                $resource->slug = Str::slug($resource->title);
            }
        });
    }

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
     * Get users who bookmarked this resource
     */
    public function bookmarkedByUsers()
    {
        return $this->belongsToMany(User::class, 'user_bookmarks', 'resource_id', 'user_id')
                    ->withTimestamps();
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
     * Scope by category
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
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
              ->orWhere('subcategory', 'LIKE', "%{$search}%");
              
            // Handle tags search for both JSON and array formats
            if (is_array($search)) {
                foreach ($search as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            } else {
                $q->orWhereJsonContains('tags', $search);
            }
        });
    }

    /**
     * Scope for ordering by popularity
     */
    public function scopePopular($query)
    {
        return $query->orderBy('view_count', 'desc');
    }

    /**
     * Scope for ordering by rating
     */
    public function scopeTopRated($query)
    {
        return $query->orderBy('rating', 'desc');
    }

    /**
     * Scope for ordering by downloads
     */
    public function scopeMostDownloaded($query)
    {
        return $query->orderBy('download_count', 'desc');
    }

    /**
     * Scope for ordering by newest
     */
    public function scopeNewest($query)
    {
        return $query->orderBy('published_at', 'desc');
    }

    /**
     * Scope for ordering by featured first
     */
    public function scopeFeaturedFirst($query)
    {
        return $query->orderBy('is_featured', 'desc')
                    ->orderBy('sort_order', 'asc')
                    ->orderBy('rating', 'desc');
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
     * FIXED: Increment view count safely
     */
    public function incrementViews(): bool
    {
        try {
            $this->increment('view_count');
            Log::info('Resource view count incremented', [
                'resource_id' => $this->id,
                'new_count' => $this->fresh()->view_count
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to increment resource views', [
                'resource_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * FIXED: Increment download count safely
     */
    public function incrementDownloads(): bool
    {
        try {
            $this->increment('download_count');
            Log::info('Resource download count incremented', [
                'resource_id' => $this->id,
                'new_count' => $this->fresh()->download_count
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to increment resource downloads', [
                'resource_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * CRITICAL FIX: This is the missing method that the controller calls!
     * Update rating based on feedback - matches controller expectation
     */
    public function updateResourceRating(): bool
    {
        try {
            $averageRating = $this->feedback()->avg('rating');
            
            if ($averageRating !== null) {
                $newRating = round($averageRating, 2);
                $this->update(['rating' => $newRating]);
                
                Log::info('Resource rating updated', [
                    'resource_id' => $this->id,
                    'old_rating' => $this->getOriginal('rating'),
                    'new_rating' => $newRating,
                    'feedback_count' => $this->feedback()->count()
                ]);
                
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to update resource rating', [
                'resource_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Alternative method name for consistency (same functionality)
     */
    public function updateRating(): bool
    {
        return $this->updateResourceRating();
    }

    /**
     * Get the helpfulness rate for this resource
     */
    public function getHelpfulnessRate(): float
    {
        $totalFeedback = $this->feedback()->count();
        if ($totalFeedback === 0) {
            return 0.0;
        }

        $helpfulFeedback = $this->feedback()->where('is_recommended', true)->count();
        return round(($helpfulFeedback / $totalFeedback) * 100, 1);
    }

    /**
     * Check if resource is bookmarked by a specific user
     */
    public function isBookmarkedBy(int $userId): bool
    {
        return $this->bookmarkedByUsers()->where('user_id', $userId)->exists();
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        if (empty($this->duration)) {
            return 'Self-paced';
        }
        
        return $this->duration;
    }

    /**
     * Get formatted view count
     */
    public function getFormattedViewCountAttribute(): string
    {
        return $this->formatCount($this->view_count);
    }

    /**
     * Get formatted download count
     */
    public function getFormattedDownloadCountAttribute(): string
    {
        return $this->formatCount($this->download_count);
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute(): string
    {
        return self::getAvailableTypes()[$this->type] ?? ucfirst($this->type);
    }

    /**
     * Get difficulty label
     */
    public function getDifficultyLabelAttribute(): string
    {
        return self::getAvailableDifficulties()[$this->difficulty] ?? ucfirst($this->difficulty);
    }

    /**
     * Get primary action URL (external_url or download_url based on type)
     */
    public function getPrimaryUrlAttribute(): string
    {
        if ($this->type === self::TYPE_WORKSHEET && !empty($this->download_url)) {
            return $this->download_url;
        }
        
        return $this->external_url;
    }

    /**
     * Get primary action type (access or download)
     */
    public function getPrimaryActionAttribute(): string
    {
        if ($this->type === self::TYPE_WORKSHEET && !empty($this->download_url)) {
            return 'download';
        }
        
        return 'access';
    }

    /**
     * Check if resource has downloadable content
     */
    public function hasDownload(): bool
    {
        return !empty($this->download_url);
    }

    /**
     * Get the time ago string for publishing
     */
    public function getPublishedTimeAgoAttribute(): string
    {
        if (!$this->published_at) {
            return 'Not published';
        }

        return $this->published_at->diffForHumans();
    }

    /**
     * Get resource statistics
     */
    public function getStatsAttribute(): array
    {
        return [
            'views' => $this->view_count,
            'downloads' => $this->download_count,
            'rating' => $this->rating,
            'feedback_count' => $this->feedback()->count(),
            'helpfulness_rate' => $this->getHelpfulnessRate(),
            'bookmark_count' => $this->bookmarkedByUsers()->count(),
        ];
    }

    /**
     * Private helper to format counts
     */
    private function formatCount(int $count): string
    {
        if ($count < 1000) {
            return (string) $count;
        }
        
        if ($count < 1000000) {
            return round($count / 1000, 1) . 'K';
        }
        
        return round($count / 1000000, 1) . 'M';
    }

    /**
     * Validation rules for creating/updating resources
     */
    public static function getValidationRules(bool $isUpdate = false): array
    {
        $rules = [
            'category_id' => 'required|exists:resource_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'type' => 'required|in:' . implode(',', array_keys(self::getAvailableTypes())),
            'difficulty' => 'required|in:' . implode(',', array_keys(self::getAvailableDifficulties())),
            'external_url' => 'required|url|max:500',
            'download_url' => 'nullable|url|max:500',
            'thumbnail_url' => 'nullable|url|max:500',
            'duration' => 'nullable|string|max:50',
            'subcategory' => 'nullable|string|max:100',
            'author_name' => 'nullable|string|max:255',
            'author_bio' => 'nullable|string|max:1000',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:50',
            'sort_order' => 'nullable|integer|min:0',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
        ];

        if ($isUpdate) {
            // Make some fields optional for updates
            $rules['category_id'] = 'sometimes|' . $rules['category_id'];
            $rules['title'] = 'sometimes|' . $rules['title'];
            $rules['description'] = 'sometimes|' . $rules['description'];
            $rules['type'] = 'sometimes|' . $rules['type'];
            $rules['difficulty'] = 'sometimes|' . $rules['difficulty'];
            $rules['external_url'] = 'sometimes|' . $rules['external_url'];
        }

        return $rules;
    }

    /**
     * Create a new resource with proper defaults
     */
    public static function createResource(array $data, int $createdBy): self
    {
        $resource = new self();
        
        // Set required fields
        $resource->fill($data);
        $resource->created_by = $createdBy;
        $resource->rating = 0.0;
        $resource->view_count = 0;
        $resource->download_count = 0;
        $resource->sort_order = $data['sort_order'] ?? 0;
        
        // Set published_at if publishing immediately
        if ($data['is_published'] ?? false) {
            $resource->published_at = now();
        }
        
        $resource->save();
        
        Log::info('Resource created successfully', [
            'resource_id' => $resource->id,
            'title' => $resource->title,
            'created_by' => $createdBy
        ]);
        
        return $resource;
    }

    /**
     * Update resource with proper tracking
     */
    public function updateResource(array $data, int $updatedBy): bool
    {
        try {
            $oldData = $this->toArray();
            
            $this->fill($data);
            $this->updated_by = $updatedBy;
            
            // Handle publishing state changes
            if (isset($data['is_published'])) {
                if ($data['is_published'] && !$this->getOriginal('is_published')) {
                    // Publishing for first time
                    $this->published_at = now();
                } elseif (!$data['is_published'] && $this->getOriginal('is_published')) {
                    // Unpublishing
                    $this->published_at = null;
                }
            }
            
            $saved = $this->save();
            
            if ($saved) {
                Log::info('Resource updated successfully', [
                    'resource_id' => $this->id,
                    'title' => $this->title,
                    'updated_by' => $updatedBy,
                    'changes' => $this->getChanges()
                ]);
            }
            
            return $saved;
        } catch (\Exception $e) {
            Log::error('Failed to update resource', [
                'resource_id' => $this->id,
                'error' => $e->getMessage(),
                'updated_by' => $updatedBy
            ]);
            return false;
        }
    }
}