<?php
// app/Http/Controllers/HelpController.php

namespace App\Http\Controllers;

use App\Models\HelpCategory;
use App\Models\FAQ;
use App\Models\FAQFeedback;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class HelpController extends Controller
{
    /**
     * Get help categories with FAQ counts
     */
    public function getCategories(Request $request): JsonResponse
    {
        try {
            $categories = HelpCategory::active()
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
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Build query
            $query = FAQ::published()->with(['category:id,name,slug,color,icon']);

            // Apply filters
            if ($request->has('category') && $request->category !== 'all') {
                $category = HelpCategory::where('slug', $request->category)->first();
                if ($category) {
                    $query->where('category_id', $category->id);
                }
            }

            if ($request->has('search') && !empty($request->search)) {
                $query->search($request->search);
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

            // Check if FAQ is published
            if (!$faq->is_published) {
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
                    ->get(['id', 'question', 'published_at']),
                'categories_with_counts' => HelpCategory::active()
                    ->withCount(['faqs' => function ($query) {
                        $query->published();
                    }])
                    ->orderBy('faqs_count', 'desc')
                    ->get(['id', 'name', 'slug', 'color'])
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
     * Private helper to notify admins of content suggestions
     */
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