<?php
// app/Models/ResourceFeedback.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceFeedback extends Model
{
    use HasFactory;

    protected $table = 'resource_feedback';

    protected $fillable = [
        'resource_id',
        'user_id',
        'rating',
        'comment',
        'is_recommended',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_recommended' => 'boolean',
    ];

    /**
     * Get the resource that owns this feedback
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /**
     * Get the user who provided this feedback
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for recommended feedback
     */
    public function scopeRecommended($query)
    {
        return $query->where('is_recommended', true);
    }

    /**
     * Scope by rating
     */
    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }
}