<?php
// app/Models/AccessibilityReport.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessibilityReport extends Model
{
    use HasFactory;

    protected $table = 'accessibility_reports';

    protected $fillable = [
        'faq_id',
        'user_id',
        'type',
        'description',
        'user_agent',
        'status',
        'admin_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the FAQ that this report is about
     */
    public function faq(): BelongsTo
    {
        return $this->belongsTo(FAQ::class);
    }

    /**
     * Get the user who reported the issue
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who resolved the issue
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope for pending reports
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for resolved reports
     */
    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    /**
     * Mark report as resolved
     */
    public function markAsResolved(int $resolvedBy, string $notes = null): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_by' => $resolvedBy,
            'resolved_at' => now(),
            'admin_notes' => $notes,
        ]);
    }
}