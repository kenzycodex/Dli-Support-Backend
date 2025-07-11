<?php
// app/Models/FAQFeedback.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FAQFeedback extends Model
{
    use HasFactory;

    protected $table = 'faq_feedback';

    protected $fillable = [
        'faq_id',
        'user_id',
        'is_helpful',
        'comment',
        'ip_address',
    ];

    protected $casts = [
        'is_helpful' => 'boolean',
    ];

    /**
     * Get the FAQ that owns this feedback
     */
    public function faq(): BelongsTo
    {
        return $this->belongsTo(FAQ::class);
    }

    /**
     * Get the user who provided this feedback
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for helpful feedback
     */
    public function scopeHelpful($query)
    {
        return $query->where('is_helpful', true);
    }

    /**
     * Scope for not helpful feedback
     */
    public function scopeNotHelpful($query)
    {
        return $query->where('is_helpful', false);
    }
}
