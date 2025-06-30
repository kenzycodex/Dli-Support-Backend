<?php
// app/Models/TicketAttachment.php (Fixed with proper imports)

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Fixed: Added missing import

class TicketAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'response_id',
        'original_name',
        'file_path',
        'file_type',
        'file_size',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the ticket this attachment belongs to
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the response this attachment belongs to
     */
    public function response(): BelongsTo
    {
        return $this->belongsTo(TicketResponse::class, 'response_id');
    }

    /**
     * Get file size in human readable format
     */
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if file is an image
     */
    public function isImage(): bool
    {
        return in_array($this->file_type, [
            'image/jpeg', 
            'image/png', 
            'image/gif', 
            'image/webp',
            'image/jpg'
        ]);
    }

    /**
     * Check if file is a document
     */
    public function isDocument(): bool
    {
        return in_array($this->file_type, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain'
        ]);
    }

    /**
     * Get file icon based on type
     */
    public function getFileIcon(): string
    {
        if ($this->isImage()) {
            return 'image';
        }

        switch ($this->file_type) {
            case 'application/pdf':
                return 'pdf';
            case 'application/msword':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                return 'document';
            case 'text/plain':
                return 'text';
            default:
                return 'file';
        }
    }

    /**
     * Get download URL
     */
    public function getDownloadUrl(): string
    {
        return route('api.tickets.attachments.download', $this->id);
    }

    /**
     * Scope for images only
     */
    public function scopeImages($query)
    {
        return $query->where('file_type', 'like', 'image/%');
    }

    /**
     * Scope for documents only
     */
    public function scopeDocuments($query)
    {
        return $query->whereIn('file_type', [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain'
        ]);
    }
}