<?php
// app/Services/HelpAnalyticsService.php - New service for analytics

namespace App\Services;

use App\Models\HelpAnalytics;
use App\Models\FAQ;
use App\Models\HelpCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HelpAnalyticsService
{
    /**
     * Track a help-related event
     */
    public function track(string $type, string $referenceId, array $metadata = []): void
    {
        try {
            HelpAnalytics::updateOrCreate([
                'type' => $type,
                'reference_id' => $referenceId,
                'date' => now()->toDateString(),
            ], [
                'count' => DB::raw('count + 1'),
                'metadata' => array_merge(
                    json_decode(HelpAnalytics::where([
                        'type' => $type,
                        'reference_id' => $referenceId,
                        'date' => now()->toDateString(),
                    ])->value('metadata') ?? '{}', true),
                    $metadata
                ),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Help analytics tracking failed: ' . $e->getMessage());
        }
    }

    /**
     * Get search analytics
     */
    public function getSearchAnalytics(int $days = 30): array
    {
        $startDate = now()->subDays($days)->toDateString();
        
        return Cache::remember("help_search_analytics_{$days}d", 300, function () use ($startDate) {
            $searches = HelpAnalytics::searches()
                ->where('date', '>=', $startDate)
                ->orderBy('count', 'desc')
                ->get();

            $topSearches = $searches->take(10)->map(function ($item) {
                $metadata = $item->metadata ?? [];
                return [
                    'query' => $item->reference_id,
                    'count' => $item->count,
                    'results' => $metadata['results_count'] ?? 0,
                ];
            });

            $failedSearches = $searches->filter(function ($item) {
                $metadata = $item->metadata ?? [];
                return ($metadata['results_count'] ?? 1) === 0;
            })->take(10)->map(function ($item) {
                return [
                    'query' => $item->reference_id,
                    'count' => $item->count,
                    'results' => 0,
                ];
            });

            return [
                'top_searches' => $topSearches,
                'failed_searches' => $failedSearches,
                'total_searches' => $searches->sum('count'),
                'unique_queries' => $searches->count(),
            ];
        });
    }

    /**
     * Get category analytics
     */
    public function getCategoryAnalytics(int $days = 30): array
    {
        $startDate = now()->subDays($days)->toDateString();
        
        return Cache::remember("help_category_analytics_{$days}d", 300, function () use ($startDate) {
            $categoryClicks = HelpAnalytics::categoryClicks()
                ->where('date', '>=', $startDate)
                ->orderBy('count', 'desc')
                ->get();

            return $categoryClicks->map(function ($item) {
                $category = HelpCategory::where('slug', $item->reference_id)->first();
                return [
                    'category' => $category?->name ?? $item->reference_id,
                    'slug' => $item->reference_id,
                    'clicks' => $item->count,
                    'color' => $category?->color ?? '#gray',
                ];
            });
        });
    }

    /**
     * Get FAQ performance metrics
     */
    public function getFAQPerformance(int $faqId = null): array
    {
        $query = FAQ::published();
        
        if ($faqId) {
            $query->where('id', $faqId);
        }

        return $query->selectRaw('
                id,
                question,
                view_count,
                helpful_count,
                not_helpful_count,
                (helpful_count / GREATEST(helpful_count + not_helpful_count, 1) * 100) as helpfulness_rate,
                published_at
            ')
            ->orderBy('view_count', 'desc')
            ->get()
            ->map(function ($faq) {
                return [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'views' => $faq->view_count,
                    'helpful_votes' => $faq->helpful_count,
                    'unhelpful_votes' => $faq->not_helpful_count,
                    'helpfulness_rate' => round($faq->helpfulness_rate, 1),
                    'published_at' => $faq->published_at->toISOString(),
                ];
            })
            ->toArray();
    }

    /**
     * Get system overview metrics
     */
    public function getSystemOverview(): array
    {
        return Cache::remember('help_system_overview', 300, function () {
            $totalFAQs = FAQ::count();
            $publishedFAQs = FAQ::published()->count();
            $totalViews = FAQ::sum('view_count');
            $totalHelpfulVotes = FAQ::sum('helpful_count');
            $totalUnhelpfulVotes = FAQ::sum('not_helpful_count');
            
            $averageHelpfulness = $totalFAQs > 0 
                ? FAQ::selectRaw('AVG((helpful_count / GREATEST(helpful_count + not_helpful_count, 1)) * 100) as avg')
                      ->first()->avg ?? 0
                : 0;

            return [
                'total_faqs' => $totalFAQs,
                'published_faqs' => $publishedFAQs,
                'unpublished_faqs' => $totalFAQs - $publishedFAQs,
                'total_categories' => HelpCategory::count(),
                'active_categories' => HelpCategory::active()->count(),
                'total_views' => $totalViews,
                'total_feedback' => $totalHelpfulVotes + $totalUnhelpfulVotes,
                'helpful_votes' => $totalHelpfulVotes,
                'unhelpful_votes' => $totalUnhelpfulVotes,
                'average_helpfulness' => round($averageHelpfulness, 1),
                'satisfaction_rate' => $totalHelpfulVotes > 0 
                    ? round(($totalHelpfulVotes / ($totalHelpfulVotes + $totalUnhelpfulVotes)) * 100, 1)
                    : 0,
            ];
        });
    }

    /**
     * Get trending topics
     */
    public function getTrendingTopics(int $days = 7): array
    {
        $startDate = now()->subDays($days);
        
        return Cache::remember("trending_topics_{$days}d", 600, function () use ($startDate) {
            // Get frequently searched terms
            $searchTrends = HelpAnalytics::searches()
                ->where('date', '>=', $startDate->toDateString())
                ->orderBy('count', 'desc')
                ->take(10)
                ->pluck('reference_id', 'count')
                ->toArray();

            // Get most viewed FAQs
            $faqTrends = FAQ::published()
                ->where('updated_at', '>=', $startDate)
                ->orderBy('view_count', 'desc')
                ->take(5)
                ->get(['question', 'view_count', 'tags'])
                ->map(function ($faq) {
                    return [
                        'question' => $faq->question,
                        'views' => $faq->view_count,
                        'tags' => $faq->tags ?? [],
                    ];
                });

            return [
                'search_trends' => $searchTrends,
                'faq_trends' => $faqTrends,
                'period' => "{$days} days",
            ];
        });
    }

    /**
     * Clear analytics cache
     */
    public function clearCache(): void
    {
        $cacheKeys = [
            'help_search_analytics_*',
            'help_category_analytics_*',
            'help_system_overview',
            'trending_topics_*',
        ];

        foreach ($cacheKeys as $pattern) {
            if (str_contains($pattern, '*')) {
                // For wildcard patterns, you'd need to implement cache tag clearing
                // or use a more sophisticated cache implementation
                Cache::flush(); // This clears all cache - use carefully in production
                break;
            } else {
                Cache::forget($pattern);
            }
        }
    }
}