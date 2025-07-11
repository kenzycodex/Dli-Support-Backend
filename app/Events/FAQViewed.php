<?php
// app/Events/FAQViewed.php - Event for real-time tracking

namespace App\Events;

use App\Models\FAQ;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FAQViewed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public FAQ $faq,
        public ?User $user = null,
        public ?string $sessionId = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('help-analytics'),
        ];
    }

    /**
     * Get the data to broadcast
     */
    public function broadcastWith(): array
    {
        return [
            'faq_id' => $this->faq->id,
            'faq_title' => $this->faq->question,
            'user_id' => $this->user?->id,
            'timestamp' => now()->toISOString(),
        ];
    }
}