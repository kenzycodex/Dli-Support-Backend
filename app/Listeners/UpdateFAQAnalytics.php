<?php
// app/Listeners/UpdateFAQAnalytics.php - Event listener

namespace App\Listeners;

use App\Events\FAQViewed;
use App\Services\HelpAnalyticsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateFAQAnalytics implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private HelpAnalyticsService $analyticsService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(FAQViewed $event): void
    {
        // Track the FAQ view
        $this->analyticsService->track(
            'faq_view',
            (string) $event->faq->id,
            [
                'question' => $event->faq->question,
                'category_id' => $event->faq->category_id,
                'user_id' => $event->user?->id,
                'session_id' => $event->sessionId,
            ]
        );

        // Track category interest
        if ($event->faq->category) {
            $this->analyticsService->track(
                'category_interest',
                $event->faq->category->slug,
                [
                    'category_name' => $event->faq->category->name,
                    'via_faq' => $event->faq->id,
                ]
            );
        }
    }
}