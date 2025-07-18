<?php
// app/Http/Controllers/HelpController.php (FIXED - Complete rewrite with ApiResponseTrait)

namespace App\Http\Controllers;

use App\Models\HelpCategory;
use App\Models\FAQ;
use App\Models\FAQFeedback;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class HelpController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get help categories with FAQ counts
     */
    public function getCategories(Request $request): JsonResponse
    {
        $this->logRequestDetails('Help Categories Fetch');

        try {
            Log::info('=== FETCHING HELP CATEGORIES ===', [
                'user_id' => $request->user()?->id,
                'user_role' => $request->user()?->role
            ]);

            $categories = HelpCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->withCount(['faqs' => function ($query) {
                    $query->where('is_published', true);
                }])
                ->get();

            Log::info('✅ Help categories fetched successfully', [
                'count' => $categories->count()
            ]);

            return $this->successResponse([
                'categories' => $categories
            ], 'Help categories retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Help categories fetch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'Help categories fetch');
        }
    }

    /**
     * Get FAQs with filtering and search
     */
    public function getFAQs(Request $request): JsonResponse
    {
        $this->logRequestDetails('FAQs Fetch');

        try {
            Log::info('=== FETCHING FAQs ===', [
                'user_id' => $request->user()?->id,
                'user_role' => $request->user()?->role,
                'filters' => $request->only(['category', 'search', 'featured', 'sort_by'])
            ]);

            $validator = Validator::make($request->all(), [
                'category' => 'sometimes|string|exists:help_categories,slug',
                'search' => 'sometimes|string|max:255',
                'featured' => 'sometimes|boolean',
                'sort_by' => 'sometimes|in:featured,helpful,views,newest',
                'per_page' => 'sometimes|integer|min:1|max:50',
            ], [
                'category.exists' => 'Invalid category selected',
                'search.max' => 'Search term cannot exceed 255 characters',
                'sort_by.in' => 'Invalid sort option',
                'per_page.min' => 'Items per page must be at least 1',
                'per_page.max' => 'Items per page cannot exceed 50',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ FAQ fetch validation failed', [
                    'errors' => $validator->errors()
                ]);
                return $this->validationErrorResponse($validator, 'Please check your search criteria');
            }

            // Build query for published FAQs only
            $query = FAQ::where('is_published', true)
                ->with(['category:id,name,slug,color,icon']);

            // Apply category filter
            if ($request->has('category') && $request->category !== 'all') {
                $category = HelpCategory::where('slug', $request->category)->first();
                if ($category) {
                    $query->where('category_id', $category->id);
                    Log::info('Applied category filter', ['category_id' => $category->id]);
                }
            }

            // Apply search filter
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('question', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('answer', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('tags', 'LIKE', "%{$searchTerm}%");
                });
                Log::info('Applied search filter', ['search' => $searchTerm]);
            }

            // Apply featured filter
            if ($request->boolean('featured')) {
                $query->where('is_featured', true);
                Log::info('Applied featured filter');
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

            // Get featured FAQs separately if not filtering
            $featuredFAQs = [];
            if (!$request->has('search') && !$request->boolean('featured')) {
                $featuredFAQs = FAQ::where('is_published', true)
                    ->where('is_featured', true)
                    ->with(['category:id,name,slug,color,icon'])
                    ->orderBy('sort_order')
                    ->take(3)
                    ->get();
            }

            Log::info('✅ FAQs fetched successfully', [
                'total' => $faqs->total(),
                'featured_count' => count($featuredFAQs),
                'current_page' => $faqs->currentPage()
            ]);

            return $this->paginatedResponse($faqs, 'FAQs retrieved successfully')
                ->setData(array_merge($this->paginatedResponse($faqs)->getData(true), [
                    'data' => array_merge($this->paginatedResponse($faqs)->getData(true)['data'], [
                        'featured_faqs' => $featuredFAQs
                    ])
                ]));

        } catch (Exception $e) {
            Log::error('❌ FAQs fetch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'FAQs fetch');
        }
    }

    /**
     * Get single FAQ with view tracking
     */
    public function showFAQ(Request $request, FAQ $faq): JsonResponse
    {
        $this->logRequestDetails('FAQ View');

        try {
            Log::info('=== VIEWING FAQ ===', [
                'faq_id' => $faq->id,
                'user_id' => $request->user()?->id
            ]);

            // Check if FAQ is published
            if (!$faq->is_published) {
                Log::warning('❌ Attempt to view unpublished FAQ', [
                    'faq_id' => $faq->id,
                    'user_id' => $request->user()?->id
                ]);
                return $this->notFoundResponse('FAQ not found or not available');
            }

            DB::beginTransaction();

            try {
                // Increment view count atomically
                $faq->increment('view_count');
                
                DB::commit();
                
                Log::info('✅ FAQ view count incremented', [
                    'faq_id' => $faq->id,
                    'new_view_count' => $faq->view_count
                ]);

            } catch (Exception $e) {
                DB::rollBack();
                Log::warning('⚠️ Failed to increment view count', [
                    'faq_id' => $faq->id,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the request for view count issues
            }

            // Load relationships
            $faq->load(['category:id,name,slug,color,icon']);

            // Check if user has already provided feedback
            $userFeedback = null;
            if ($request->user()) {
                $userFeedback = FAQFeedback::where('faq_id', $faq->id)
                    ->where('user_id', $request->user()->id)
                    ->first();
            }

            Log::info('✅ FAQ viewed successfully', [
                'faq_id' => $faq->id,
                'view_count' => $faq->view_count,
                'has_user_feedback' => !!$userFeedback
            ]);

            return $this->successResponse([
                'faq' => $faq,
                'user_feedback' => $userFeedback
            ], 'FAQ retrieved successfully');

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('❌ FAQ view failed', [
                'faq_id' => $faq->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->handleException($e, 'FAQ view');
        }
    }

    /**
     * Provide feedback on FAQ
     */
    public function provideFeedback(Request $request, FAQ $faq): JsonResponse
    {
        $this->logRequestDetails('FAQ Feedback');

        try {
            Log::info('=== PROVIDING FAQ FEEDBACK ===', [
                'faq_id' => $faq->id,
                'user_id' => $request->user()->id,
            ]);

            $validator = Validator::make($request->all(), [
                'is_helpful' => 'required|boolean',
                'comment' => 'sometimes|string|max:500',
            ], [
                'is_helpful.required' => 'Please indicate if this FAQ was helpful',
                'is_helpful.boolean' => 'Feedback value must be true or false',
                'comment.max' => 'Comment cannot exceed 500 characters'
            ]);

            if ($validator->fails()) {
                Log::warning('❌ FAQ feedback validation failed', [
                    'faq_id' => $faq->id,
                    'user_id' => $request->user()->id,
                    'errors' => $validator->errors()
                ]);
                return $this->validationErrorResponse($validator, 'Please check your feedback and try again');
            }

            // Check if user already provided feedback
            $existingFeedback = FAQFeedback::where('faq_id', $faq->id)
                ->where('user_id', $request->user()->id)
                ->first();

            if ($existingFeedback) {
                Log::warning('❌ Duplicate feedback attempt', [
                    'faq_id' => $faq->id,
                    'user_id' => $request->user()->id,
                    'existing_feedback_id' => $existingFeedback->id
                ]);
                return $this->errorResponse(
                    'You have already provided feedback for this FAQ',
                    409 // Conflict
                );
            }

            DB::beginTransaction();

            try {
                // Create feedback
                $feedback = FAQFeedback::create([
                    'faq_id' => $faq->id,
                    'user_id' => $request->user()->id,
                    'is_helpful' => $request->boolean('is_helpful'),
                    'comment' => $request->get('comment'),
                    'ip_address' => $request->ip(),
                ]);

                // Update FAQ counters atomically
                if ($request->boolean('is_helpful')) {
                    $faq->increment('helpful_count');
                } else {
                    $faq->increment('not_helpful_count');
                }

                DB::commit();

                Log::info('✅ FAQ feedback submitted successfully', [
                    'feedback_id' => $feedback->id,
                    'faq_id' => $faq->id,
                    'is_helpful' => $request->boolean('is_helpful')
                ]);

                // Load fresh FAQ data with relationships
                $faq->load(['category:id,name,slug,color,icon']);

                return $this->successResponse([
                    'feedback' => $feedback,
                    'faq' => $faq
                ], 'Thank you for your feedback!', 201);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('❌ FAQ feedback failed', [
                'faq_id' => $faq->id ?? 'unknown',
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->handleException($e, 'FAQ feedback submission');
        }
    }

    /**
     * Suggest new FAQ content (for counselors and admins)
     */
    public function suggestContent(Request $request): JsonResponse
    {
        $this->logRequestDetails('FAQ Content Suggestion');

        try {
            Log::info('=== SUGGESTING FAQ CONTENT ===', [
                'user_id' => $request->user()->id,
                'user_role' => $request->user()->role,
            ]);

            $user = $request->user();

            // Check permissions - only counselors and admins can suggest content
            if (!in_array($user->role, ['counselor', 'admin'])) {
                Log::warning('❌ Unauthorized content suggestion attempt', [
                    'user_id' => $user->id,
                    'user_role' => $user->role
                ]);
                return $this->forbiddenResponse('Only counselors and administrators can suggest content');
            }

            $validator = Validator::make($request->all(), [
                'category_id' => 'required|exists:help_categories,id',
                'question' => 'required|string|min:10|max:500',
                'answer' => 'required|string|min:20|max:5000',
                'tags' => 'sometimes|array|max:10',
                'tags.*' => 'string|max:50',
            ], [
                'category_id.required' => 'Please select a category',
                'category_id.exists' => 'Invalid category selected',
                'question.required' => 'Question is required',
                'question.min' => 'Question must be at least 10 characters',
                'question.max' => 'Question cannot exceed 500 characters',
                'answer.required' => 'Answer is required',
                'answer.min' => 'Answer must be at least 20 characters',
                'answer.max' => 'Answer cannot exceed 5000 characters',
                'tags.max' => 'Maximum 10 tags allowed',
                'tags.*.max' => 'Each tag cannot exceed 50 characters'
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Content suggestion validation failed', [
                    'user_id' => $user->id,
                    'errors' => $validator->errors()
                ]);
                return $this->validationErrorResponse($validator, 'Please check your input and try again');
            }

            DB::beginTransaction();

            try {
                // Create suggested FAQ (unpublished)
                $faq = FAQ::create([
                    'category_id' => $request->category_id,
                    'question' => trim($request->question),
                    'answer' => trim($request->answer),
                    'slug' => Str::slug($request->question) . '-' . time(),
                    'tags' => $request->get('tags', []),
                    'is_published' => false, // Requires admin approval
                    'is_featured' => false,
                    'created_by' => $user->id,
                ]);

                if (!$faq) {
                    throw new Exception('Failed to create FAQ suggestion');
                }

                // Load category for response
                $faq->load(['category:id,name,slug,color']);

                // Create notification for admins
                $this->notifyAdminsOfSuggestion($faq, $user);

                DB::commit();

                Log::info('✅ FAQ content suggested successfully', [
                    'faq_id' => $faq->id,
                    'user_id' => $user->id,
                    'question' => $faq->question
                ]);

                return $this->successResponse([
                    'faq' => $faq
                ], 'Content suggestion submitted for review. Thank you for your contribution!', 201);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('❌ FAQ suggestion failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->handleException($e, 'FAQ content suggestion');
        }
    }

    /**
     * Get help statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        $this->logRequestDetails('Help Statistics Fetch');

        try {
            Log::info('=== FETCHING HELP STATS ===', [
                'user_id' => $request->user()?->id
            ]);

            $stats = [
                'total_faqs' => FAQ::where('is_published', true)->count(),
                'total_categories' => HelpCategory::where('is_active', true)->count(),
                'most_helpful_faq' => FAQ::where('is_published', true)
                    ->orderBy('helpful_count', 'desc')
                    ->first(['id', 'question', 'helpful_count']),
                'most_viewed_faq' => FAQ::where('is_published', true)
                    ->orderBy('view_count', 'desc')
                    ->first(['id', 'question', 'view_count']),
                'recent_faqs' => FAQ::where('is_published', true)
                    ->orderBy('published_at', 'desc')
                    ->take(5)
                    ->get(['id', 'question', 'published_at']),
                'categories_with_counts' => HelpCategory::where('is_active', true)
                    ->withCount(['faqs' => function ($query) {
                        $query->where('is_published', true);
                    }])
                    ->orderBy('faqs_count', 'desc')
                    ->get(['id', 'name', 'slug', 'color'])
            ];

            Log::info('✅ Help stats retrieved successfully', [
                'total_faqs' => $stats['total_faqs'],
                'total_categories' => $stats['total_categories']
            ]);

            return $this->successResponse([
                'stats' => $stats
            ], 'Help statistics retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Help stats fetch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->handleException($e, 'Help statistics fetch');
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

            Log::info('✅ Admin notifications created for FAQ suggestion', [
                'faq_id' => $faq->id,
                'notified_admins' => $admins->count()
            ]);

        } catch (Exception $e) {
            Log::error('❌ Failed to notify admins of FAQ suggestion', [
                'faq_id' => $faq->id,
                'error' => $e->getMessage()
            ]);
            // Don't throw - notification failure shouldn't prevent FAQ creation
        }
    }
}