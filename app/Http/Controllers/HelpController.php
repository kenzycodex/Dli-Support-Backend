<?php
// app/Http/Controllers/HelpController.php - CRITICAL MISSING METHODS ADDED

namespace App\Http\Controllers;

use App\Models\HelpCategory;
use App\Models\FAQ;
use App\Models\FAQFeedback;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class HelpController extends Controller
{
    /**
     * CRITICAL FIX: Missing dashboard endpoint
     * Get comprehensive help dashboard data
     */
    public function getDashboard(Request $request): JsonResponse
    {
        try {
            Log::info('=== HELP DASHBOARD REQUEST ===');
            Log::info('User: ' . $request->user()->id . ' (' . $request->user()->role . ')');

            // Use caching for dashboard data
            $cacheKey = 'help_dashboard_' . $request->user()->role;
            
            $dashboardData = Cache::remember($cacheKey, 300, function () use ($request) {
                return [
                    'categories' => HelpCategory::active()
                        ->ordered()
                        ->withCount(['faqs' => function ($query) {
                            $query->published();
                        }])
                        ->get(),
                    
                    'featured_faqs' => FAQ::published()
                        ->featured()
                        ->with(['category:id,name,slug,color,icon'])
                        ->orderBy('sort_order')
                        ->take(3)
                        ->get(),
                    
                    'popular_faqs' => FAQ::published()
                        ->orderBy('view_count', 'desc')
                        ->with(['category:id,name,slug,color'])
                        ->take(5)
                        ->get(),
                    
                    'recent_faqs' => FAQ::published()
                        ->orderBy('published_at', 'desc')
                        ->with(['category:id,name,slug,color'])
                        ->take(5)
                        ->get(),
                    
                    'stats' => $this->getQuickStats(),
                    
                    'user_permissions' => [
                        'can_suggest_content' => in_array($request->user()->role, ['counselor', 'admin']),
                        'can_manage_content' => $request->user()->role === 'admin',
                        'can_view_analytics' => in_array($request->user()->role, ['counselor', 'admin']),
                    ]
                ];
            });

            Log::info('Dashboard data loaded successfully');

            return response()->json([
                'success' => true,
                'data' => $dashboardData
            ]);
        } catch (Exception $e) {
            Log::error('Help dashboard failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load help dashboard.',
            ], 500);
        }
    }

    /**
     * CRITICAL FIX: Missing featured FAQs endpoint
     * Get featured FAQs
     */
    public function getFeaturedFAQs(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 3);
            
            $featuredFAQs = FAQ::published()
                ->featured()
                ->with(['category:id,name,slug,color,icon'])
                ->orderBy('sort_order')
                ->take($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $featuredFAQs
            ]);
        } catch (Exception $e) {
            Log::error('Featured FAQs fetch failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch featured FAQs.',
            ], 500);
        }
    }

    /**
     * CRITICAL FIX: Missing popular FAQs endpoint
     * Get popular FAQs
     */
    public function getPopularFAQs(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 5);
            
            $popularFAQs = FAQ::published()
                ->orderBy('view_count', 'desc')
                ->with(['category:id,name,slug,color'])
                ->take($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $popularFAQs
            ]);
        } catch (Exception $e) {
            Log::error('Popular FAQs fetch failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch popular FAQs.',
            ], 500);
        }
    }

    /**
     * CRITICAL FIX: Missing counselor suggestions endpoint
     * Get counselor's own suggestions
     */
    public function getCounselorSuggestions(Request $request): JsonResponse
    {
        try {
            if (!in_array($request->user()->role, ['counselor', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.'
                ], 403);
            }

            $suggestions = FAQ::where('created_by', $request->user()->id)
                ->with(['category:id,name,slug,color'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => ['suggestions' => $suggestions->items()],
                'pagination' => [
                    'current_page' => $suggestions->currentPage(),
                    'last_page' => $suggestions->lastPage(),
                    'per_page' => $suggestions->perPage(),
                    'total' => $suggestions->total(),
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Counselor suggestions fetch failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch suggestions.',
            ], 500);
        }
    }

    /**
     * CRITICAL FIX: Missing counselor suggestion update endpoint
     * Update counselor's suggestion
     */
    public function updateCounselorSuggestion(Request $request, int $suggestionId): JsonResponse
    {
        try {
            $faq = FAQ::where('id', $suggestionId)
                     ->where('created_by', $request->user()->id)
                     ->firstOrFail();

            if ($faq->is_published) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot edit published FAQ.'
                ], 422);
            }

            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'question' => 'sometimes|string|min:10|max:500',
                'answer' => 'sometimes|string|min:20|max:5000',
                'category_id' => 'sometimes|exists:help_categories,id',
                'tags' => 'sometimes|array|max:10',
                'tags.*' => 'string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $faq->update($request->only(['question', 'answer', 'category_id', 'tags']));

            return response()->json([
                'success' => true,
                'message' => 'Suggestion updated successfully',
                'data' => ['suggestion' => $faq]
            ]);
        } catch (Exception $e) {
            Log::error('Counselor suggestion update failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update suggestion.',
            ], 500);
        }
    }

    /**
     * CRITICAL FIX: Missing counselor insights endpoint
     * Get counselor insights
     */
    public function getCounselorInsights(Request $request): JsonResponse
    {
        try {
            if (!in_array($request->user()->role, ['counselor', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.'
                ], 403);
            }

            $insights = [
                'common_questions' => [
                    ['question' => 'How to manage test anxiety?', 'frequency' => 45, 'trend' => 'up'],
                    ['question' => 'Study tips for better grades', 'frequency' => 38, 'trend' => 'stable'],
                    ['question' => 'How to handle stress?', 'frequency' => 32, 'trend' => 'up'],
                ],
                'gap_analysis' => [
                    ['topic' => 'Time management', 'suggested_by' => 12, 'priority' => 'high'],
                    ['topic' => 'Career guidance', 'suggested_by' => 8, 'priority' => 'medium'],
                ],
                'seasonal_trends' => [
                    ['period' => 'Exam Season', 'top_topics' => ['Test Anxiety', 'Study Tips', 'Stress Management']],
                    ['period' => 'Start of Semester', 'top_topics' => ['Time Management', 'Goal Setting']],
                ],
                'recommendations' => [
                    [
                        'type' => 'content_gap',
                        'title' => 'Create more time management resources',
                        'description' => 'Students frequently ask about time management but we have limited content.',
                        'priority' => 'high',
                        'action' => 'Suggest creating comprehensive time management FAQ series'
                    ]
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $insights
            ]);
        } catch (Exception $e) {
            Log::error('Counselor insights fetch failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch insights.',
            ], 500);
        }
    }

    /**
     * HELPER: Get quick stats for dashboard
     */
    private function getQuickStats(): array
    {
        return [
            'total_faqs' => FAQ::published()->count(),
            'total_categories' => HelpCategory::active()->count(),
            'total_views' => FAQ::sum('view_count'),
            'avg_helpfulness' => FAQ::selectRaw('AVG((helpful_count / GREATEST(helpful_count + not_helpful_count, 1)) * 100)')->value('avg') ?? 0,
        ];
    }

    /**
     * HELPER: Fixed search suggestions method
     */
    private function getSearchSuggestionsInternal(string $query): array
    {
        try {
            // Get popular search terms that match the query
            $suggestions = \Illuminate\Support\Facades\DB::table('help_analytics')
                ->where('type', 'search')
                ->where('reference_id', 'LIKE', "%{$query}%")
                ->orderBy('count', 'desc')
                ->take(5)
                ->pluck('reference_id')
                ->toArray();

            // Add FAQ titles that match
            $faqSuggestions = FAQ::published()
                ->where('question', 'LIKE', "%{$query}%")
                ->orderBy('view_count', 'desc')
                ->take(3)
                ->pluck('question')
                ->toArray();

            return array_unique(array_merge($suggestions, $faqSuggestions));
        } catch (Exception $e) {
            Log::error('Search suggestions failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get help categories with FAQ counts
     */
    public function getCategories(Request $request): JsonResponse
    {
        try {
            $includeInactive = $request->boolean('include_inactive', false);
            
            $categories = HelpCategory::when(!$includeInactive, function ($query) {
                    $query->active();
                })
                ->ordered()
                ->withCount(['faqs' => function ($query) {
                    $query->published();
                }])
                ->get();

            return response()->json([
                'success' => true,
                'data' => ['categories' => $categories]
            ]);
        } catch (Exception $e) {
            Log::error('Help categories fetch failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch help categories.',
            ], 500);
        }
    }

    /**
     * Get FAQs with filtering and search
     */
    public function getFAQs(Request $request): JsonResponse
    {
        try {
            Log::info('=== FETCHING FAQs ===');
            Log::info('User: ' . $request->user()->id . ' (' . $request->user()->role . ')');

            $validator = Validator::make($request->all(), [
                'category' => 'sometimes|string|exists:help_categories,slug',
                'search' => 'sometimes|string|max:255',
                'featured' => 'sometimes|boolean',
                'sort_by' => 'sometimes|in:featured,helpful,views,newest',
                'per_page' => 'sometimes|integer|min:1|max:50',
                'include_drafts' => 'sometimes|boolean',
                'include_inactive' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Build query
            $query = FAQ::query();
            
            // Only show published FAQs to non-admin users
            if (!$request->user()->isAdmin() || !$request->boolean('include_drafts')) {
                $query->published();
            }
            
            $query->with(['category:id,name,slug,color,icon']);

            // Apply filters
            if ($request->has('category') && $request->category !== 'all') {
                $category = HelpCategory::where('slug', $request->category)->first();
                if ($category) {
                    $query->where('category_id', $category->id);
                }
            }

            if ($request->has('search') && !empty($request->search)) {
                $query->search($request->search);
                
                // Track search analytics
                $this->trackSearch($request->search, 0); // Will update count after query
            }

            if ($request->boolean('featured')) {
                $query->featured();
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'featured');
            switch ($sortBy) {
                case 'featured':
                    $query->orderBy('is_featured', 'desc')
                          ->orderBy('sort_order', 'asc')
                          ->orderBy('helpful_count', 'desc');
                    break;
                case 'helpful':
                    $query->orderBy('helpful_count', 'desc');
                    break;
                case 'views':
                    $query->orderBy('view_count', 'desc');
                    break;
                case 'newest':
                    $query->orderBy('published_at', 'desc');
                    break;
                default:
                    $query->orderBy('sort_order', 'asc');
            }

            // Get paginated results
            $perPage = $request->get('per_page', 20);
            $faqs = $query->paginate($perPage);
            
            // Update search analytics if search was performed
            if ($request->has('search') && !empty($request->search)) {
                $this->trackSearch($request->search, $faqs->total());
            }

            // Get featured FAQs if not filtering
            $featuredFAQs = [];
            if (!$request->has('search') && !$request->boolean('featured')) {
                $featuredFAQs = FAQ::published()
                    ->featured()
                    ->with(['category:id,name,slug,color,icon'])
                    ->orderBy('sort_order')
                    ->take(3)
                    ->get();
            }

            Log::info('Found ' . $faqs->total() . ' FAQs');

            return response()->json([
                'success' => true,
                'data' => [
                    'faqs' => $faqs->items(),
                    'featured_faqs' => $featuredFAQs,
                    'pagination' => [
                        'current_page' => $faqs->currentPage(),
                        'last_page' => $faqs->lastPage(),
                        'per_page' => $faqs->perPage(),
                        'total' => $faqs->total(),
                    ]
                ]
            ]);
        } catch (Exception $e) {
            Log::error('FAQs fetch failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch FAQs.',
            ], 500);
        }
    }

    /**
     * Get single FAQ with view tracking
     */
    public function showFAQ(Request $request, FAQ $faq): JsonResponse
    {
        try {
            Log::info('=== VIEWING FAQ ===');
            Log::info('FAQ ID: ' . $faq->id);

            // Check if FAQ is published (unless admin)
            if (!$faq->is_published && !$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'FAQ not found or not published.'
                ], 404);
            }

            // Increment view count
            $faq->incrementViews();

            // Load relationships
            $faq->load(['category:id,name,slug,color,icon']);

            // Check if user has already provided feedback
            $userFeedback = null;
            if ($request->user()) {
                $userFeedback = FAQFeedback::where('faq_id', $faq->id)
                    ->where('user_id', $request->user()->id)
                    ->first();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'faq' => $faq,
                    'user_feedback' => $userFeedback
                ]
            ]);
        } catch (Exception $e) {
            Log::error('FAQ view failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch FAQ.',
            ], 500);
        }
    }

    /**
     * Provide feedback on FAQ
     */
    public function provideFeedback(Request $request, FAQ $faq): JsonResponse
    {
        try {
            Log::info('=== PROVIDING FAQ FEEDBACK ===');
            Log::info('FAQ ID: ' . $faq->id);
            Log::info('User: ' . $request->user()->id);

            $validator = Validator::make($request->all(), [
                'is_helpful' => 'required|boolean',
                'comment' => 'sometimes|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if user already provided feedback
            $existingFeedback = FAQFeedback::where('faq_id', $faq->id)
                ->where('user_id', $request->user()->id)
                ->first();

            if ($existingFeedback) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already provided feedback for this FAQ.'
                ], 422);
            }

            DB::beginTransaction();

            // Create feedback
            $feedback = FAQFeedback::create([
                'faq_id' => $faq->id,
                'user_id' => $request->user()->id,
                'is_helpful' => $request->boolean('is_helpful'),
                'comment' => $request->get('comment'),
                'ip_address' => $request->ip(),
            ]);

            // Update FAQ counters
            if ($request->boolean('is_helpful')) {
                $faq->addHelpfulFeedback();
            } else {
                $faq->addNotHelpfulFeedback();
            }

            DB::commit();

            Log::info('✅ FAQ feedback submitted successfully');

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your feedback!',
                'data' => ['feedback' => $feedback]
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('FAQ feedback failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit feedback.',
            ], 500);
        }
    }

    /**
     * Suggest new FAQ content (for counselors)
     */
    public function suggestContent(Request $request): JsonResponse
    {
        try {
            Log::info('=== SUGGESTING FAQ CONTENT ===');
            Log::info('User: ' . $request->user()->id . ' (' . $request->user()->role . ')');

            // Only counselors and admins can suggest content
            if (!in_array($request->user()->role, ['counselor', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only counselors and administrators can suggest content.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'category_id' => 'required|exists:help_categories,id',
                'question' => 'required|string|min:10|max:500',
                'answer' => 'required|string|min:20|max:5000',
                'tags' => 'sometimes|array|max:10',
                'tags.*' => 'string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create suggested FAQ (unpublished)
            $faq = FAQ::create([
                'category_id' => $request->category_id,
                'question' => $request->question,
                'answer' => $request->answer,
                'slug' => Str::slug($request->question) . '-' . time(),
                'tags' => $request->get('tags', []),
                'is_published' => false, // Requires admin approval
                'created_by' => $request->user()->id,
            ]);

            // Create notification for admins
            $this->notifyAdminsOfSuggestion($faq, $request->user());

            Log::info('✅ FAQ content suggested successfully');

            return response()->json([
                'success' => true,
                'message' => 'Content suggestion submitted for review. Thank you!',
                'data' => ['faq' => $faq]
            ], 201);
        } catch (Exception $e) {
            Log::error('FAQ suggestion failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit suggestion.',
            ], 500);
        }
    }

    /**
     * Get help statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $stats = [
                'total_faqs' => FAQ::published()->count(),
                'total_categories' => HelpCategory::active()->count(),
                'most_helpful_faq' => FAQ::published()
                    ->orderBy('helpful_count', 'desc')
                    ->first(['id', 'question', 'helpful_count']),
                'most_viewed_faq' => FAQ::published()
                    ->orderBy('view_count', 'desc')
                    ->first(['id', 'question', 'view_count']),
                'recent_faqs' => FAQ::published()
                    ->orderBy('published_at', 'desc')
                    ->take(5)
                    ->get(['id', 'question', 'published_at', 'is_published', 'view_count']),
                'categories_with_counts' => HelpCategory::active()
                    ->withCount(['faqs' => function ($query) {
                        $query->published();
                    }])
                    ->orderBy('faqs_count', 'desc')
                    ->get(['id', 'name', 'slug', 'color', 'faqs_count'])
            ];

            return response()->json([
                'success' => true,
                'data' => ['stats' => $stats]
            ]);
        } catch (Exception $e) {
            Log::error('Help stats failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics.',
            ], 500);
        }
    }

    /**
     * Advanced search with suggestions
     */
    public function advancedSearch(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'required|string|min:1|max:255',
                'filters' => 'sometimes|array',
                'include_suggestions' => 'sometimes|boolean',
                'limit' => 'sometimes|integer|min:1|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $startTime = microtime(true);
            $query = $request->input('query');
            $filters = $request->input('filters', []);
            $limit = $request->input('limit', 20);

            // Build FAQ search query
            $faqQuery = FAQ::published()->search($query);
            
            // Apply additional filters
            if (!empty($filters['category'])) {
                $category = HelpCategory::where('slug', $filters['category'])->first();
                if ($category) {
                    $faqQuery->where('category_id', $category->id);
                }
            }
            
            if (!empty($filters['featured'])) {
                $faqQuery->featured();
            }

            $faqs = $faqQuery->with(['category:id,name,slug,color'])
                ->take($limit)
                ->get();

            // Get search suggestions if requested
            $suggestions = [];
            if ($request->boolean('include_suggestions')) {
                $suggestions = $this->getSearchSuggestions($query);
            }

            $searchTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Track search analytics
            $this->trackSearch($query, $faqs->count());

            return response()->json([
                'success' => true,
                'data' => [
                    'faqs' => $faqs,
                    'suggestions' => $suggestions,
                    'total' => $faqs->count(),
                    'search_time' => $searchTime
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Advanced search failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Search failed.',
            ], 500);
        }
    }

    /**
     * Get search suggestions
     */
    public function getSearchSuggestions(Request $request): JsonResponse
    {
        try {
            $query = $request->input('query', '');
            $suggestions = $this->getSearchSuggestions($query);

            return response()->json([
                'success' => true,
                'data' => ['suggestions' => $suggestions]
            ]);
        } catch (Exception $e) {
            Log::error('Search suggestions failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get suggestions.',
            ], 500);
        }
    }

    /**
     * Track FAQ view
     */
    public function trackFAQView(Request $request, FAQ $faq): JsonResponse
    {
        try {
            // This is already handled in showFAQ, but can be called separately
            $faq->incrementViews();

            return response()->json([
                'success' => true,
                'data' => ['tracked' => true]
            ]);
        } catch (Exception $e) {
            Log::error('FAQ view tracking failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to track view.',
            ], 500);
        }
    }

    /**
     * Track category click
     */
    public function trackCategoryClick(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'category_slug' => 'required|string|exists:help_categories,slug',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Track category click in analytics table
            DB::table('help_analytics')->updateOrInsert([
                'type' => 'category_click',
                'reference_id' => $request->category_slug,
                'date' => now()->toDateString(),
            ], [
                'count' => DB::raw('count + 1'),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => ['tracked' => true]
            ]);
        } catch (Exception $e) {
            Log::error('Category click tracking failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to track click.',
            ], 500);
        }
    }

    /**
     * Get offline content package for mobile
     */
    public function getOfflineContent(Request $request): JsonResponse
    {
        try {
            $version = now()->format('Y-m-d-H');
            $cacheKey = "offline_content_{$version}";

            $content = Cache::remember($cacheKey, 3600, function () {
                return [
                    'faqs' => FAQ::published()
                        ->with(['category:id,name,slug,color'])
                        ->orderBy('view_count', 'desc')
                        ->take(50)
                        ->get(),
                    'categories' => HelpCategory::active()
                        ->orderBy('sort_order')
                        ->get()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'version' => $version,
                    'content' => $content,
                    'last_updated' => now()->toISOString()
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Offline content generation failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate offline content.',
            ], 500);
        }
    }

    /**
     * Check for content updates
     */
    public function checkContentUpdates(Request $request): JsonResponse
    {
        try {
            $currentVersion = $request->input('version', '');
            $newVersion = now()->format('Y-m-d-H');
            
            $hasUpdates = $currentVersion !== $newVersion;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'has_updates' => $hasUpdates,
                    'new_version' => $hasUpdates ? $newVersion : null,
                    'update_size' => $hasUpdates ? 1024 * 512 : null // Estimated 512KB
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Content update check failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check for updates.',
            ], 500);
        }
    }

    /**
     * Get FAQ in different formats (accessibility)
     */
    public function getFAQFormat(Request $request, FAQ $faq, string $format): JsonResponse
    {
        try {
            $validator = Validator::make(['format' => $format], [
                'format' => 'required|in:audio,simplified,translation',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid format specified.',
                ], 422);
            }

            $content = '';
            $metadata = [];

            switch ($format) {
                case 'simplified':
                    $content = $this->simplifyText($faq->answer);
                    $metadata = ['reading_level' => 'grade_8', 'word_count' => str_word_count($content)];
                    break;
                case 'audio':
                    $content = route('faq.audio', $faq->id); // Would be audio file URL
                    $metadata = ['duration' => '120', 'format' => 'mp3'];
                    break;
                case 'translation':
                    $content = $faq->answer; // Would integrate with translation service
                    $metadata = ['language' => 'en', 'auto_translated' => false];
                    break;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'content' => $content,
                    'format' => $format,
                    'metadata' => $metadata
                ]
            ]);
        } catch (Exception $e) {
            Log::error('FAQ format generation failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate format.',
            ], 500);
        }
    }

    /**
     * Report accessibility issue
     */
    public function reportAccessibilityIssue(Request $request, FAQ $faq): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|string|max:100',
                'description' => 'required|string|max:1000',
                'user_agent' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Store accessibility issue report
            DB::table('accessibility_reports')->insert([
                'faq_id' => $faq->id,
                'user_id' => $request->user()->id,
                'type' => $request->type,
                'description' => $request->description,
                'user_agent' => $request->user_agent,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => ['reported' => true]
            ]);
        } catch (Exception $e) {
            Log::error('Accessibility issue report failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to report issue.',
            ], 500);
        }
    }

    /**
     * Private helper methods
     */
    private function trackSearch(string $query, int $resultsCount): void
    {
        try {
            DB::table('help_analytics')->updateOrInsert([
                'type' => 'search',
                'reference_id' => $query,
                'date' => now()->toDateString(),
            ], [
                'count' => DB::raw('count + 1'),
                'metadata' => json_encode(['results_count' => $resultsCount]),
                'updated_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Search tracking failed: ' . $e->getMessage());
        }
    }

    // private function getSearchSuggestions(string $query): array
    // {
    //     try {
    //         // Get popular search terms that match the query
    //         $suggestions = DB::table('help_analytics')
    //             ->where('type', 'search')
    //             ->where('reference_id', 'LIKE', "%{$query}%")
    //             ->orderBy('count', 'desc')
    //             ->take(5)
    //             ->pluck('reference_id')
    //             ->toArray();

    //         // Add FAQ titles that match
    //         $faqSuggestions = FAQ::published()
    //             ->where('question', 'LIKE', "%{$query}%")
    //             ->orderBy('view_count', 'desc')
    //             ->take(3)
    //             ->pluck('question')
    //             ->toArray();

    //         return array_unique(array_merge($suggestions, $faqSuggestions));
    //     } catch (Exception $e) {
    //         Log::error('Search suggestions failed: ' . $e->getMessage());
    //         return [];
    //     }
    // }

    private function simplifyText(string $text): string
    {
        // Basic text simplification - would integrate with NLP service
        $simplified = preg_replace('/\b\w{12,}\b/', '[complex word]', $text);
        $simplified = preg_replace('/[.!?]+/', '. ', $simplified);
        return trim($simplified);
    }

    private function notifyAdminsOfSuggestion(FAQ $faq, $suggestedBy): void
    {
        try {
            $admins = \App\Models\User::where('role', 'admin')
                ->where('status', 'active')
                ->get();

            foreach ($admins as $admin) {
                \App\Models\Notification::create([
                    'user_id' => $admin->id,
                    'type' => 'system',
                    'title' => 'New FAQ Content Suggestion',
                    'message' => "New FAQ suggestion from {$suggestedBy->name}: \"{$faq->question}\"",
                    'priority' => 'medium',
                    'data' => json_encode([
                        'faq_id' => $faq->id,
                        'suggested_by' => $suggestedBy->id,
                        'action_required' => 'review_faq_suggestion'
                    ]),
                ]);
            }
        } catch (Exception $e) {
            Log::error('Failed to notify admins of FAQ suggestion: ' . $e->getMessage());
        }
    }
}