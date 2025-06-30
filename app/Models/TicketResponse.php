<?php

// app/Models/TicketResponse.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'is_internal',
        'visibility',
        'is_urgent',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'is_urgent' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($response) {
            // Create notification for new response
            if (!$response->is_internal) {
                $response->notifyNewResponse();
            }
        });
    }

    /**
     * Get the ticket this response belongs to
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user who created the response
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get response attachments
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class, 'response_id');
    }

    /**
     * Notify about new response
     */
    private function notifyNewResponse()
    {
        $ticket = $this->ticket;
        
        // Notify the ticket creator if response is from staff
        if ($this->user->isStaff() && $this->user_id !== $ticket->user_id) {
            Notification::createForUser(
                $ticket->user_id,
                Notification::TYPE_TICKET,
                'New Response',
                "You have a new response on ticket #{$ticket->ticket_number}",
                Notification::PRIORITY_MEDIUM,
                ['ticket_id' => $ticket->id]
            );
        }
        
        // Notify assigned staff if response is from student
        if (!$this->user->isStaff() && $ticket->assigned_to && $ticket->assigned_to !== $this->user_id) {
            Notification::createForUser(
                $ticket->assigned_to,
                Notification::TYPE_TICKET,
                'Student Response',
                "Student responded to ticket #{$ticket->ticket_number}",
                Notification::PRIORITY_MEDIUM,
                ['ticket_id' => $ticket->id]
            );
        }
    }
}