<?php
// app/Http/Controllers/HelpController.php (FIXED)

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
        try {
            Log::info('=== FETCHING HELP CATEGORIES ===', [
                'user_id' => $request->user()?->id,
                'user_role' => $request->user()?->role
            ]);

            $categories = HelpCategory::active()
                ->ordered()
                ->withCount(['faqs' => function ($query) {
                    $query->published();
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
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->serverErrorResponse(
                'Failed to fetch help categories',
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    /**
     * Get FAQs with filtering and search
     */
    public function getFAQs(Request $request): JsonResponse
    {
        try {
            Log::info('=== FETCHING FAQs ===', [
                'user_id' => $request->user()?->id,
                'user_role' => $request->user()?->role,
                'request_data' => $request->all()
            ]);

            $validator = Validator::make($request->all(), [
                'category' => 'sometimes|string|exists:help_categories,slug',
                'search' => 'sometimes|string|max:255',
                'featured' => 'sometimes|boolean',
                'sort_by' => 'sometimes|in:featured,helpful,views,newest',
                'per_page' => 'sometimes|integer|min:1|max:50',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ FAQ fetch validation failed', [
                    'errors' => $validator->errors()
                ]);
                return $this->validationErrorResponse($validator);
            }

            // Build query
            $query = FAQ::published()->with(['category:id,name,slug,color,icon']);

            // Apply filters
            if ($request->has('category') && $request->category !== 'all') {
                $category = HelpCategory::where('slug', $request->category)->first();
                if ($category) {
                    $query->where('category_id', $category->id);
                    Log::info('Applied category filter', ['category_id' => $category->id]);
                }
            }

            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('question', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('answer', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('tags', 'LIKE', "%{$searchTerm}%");
                });
                Log::info('Applied search filter', ['search' => $searchTerm]);
            }

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

            // Get featured FAQs if not filtering
            $featuredFAQs = [];
            if (!$request->has('search') && !$request->boolean('featured')) {
                $featuredFAQs = FAQ::published()
                    ->where('is_featured', true)
                    ->with(['category:id,name,slug,color,icon'])
                    ->orderBy('sort_order')
                    ->take(3)
                    ->get();
            }

            Log::info('✅ FAQs fetched successfully', [
                'total' => $faqs->total(),
                'featured_count' => count($featuredFAQs)
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
                'user_id' => $request->user()?->id
            ]);
            
            return $this->serverErrorResponse(
                'Failed to fetch FAQs',
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    /**
     * Get single FAQ with view tracking
     */
    public function showFAQ(Request $request, FAQ $faq): JsonResponse
    {
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

            // Increment view count (use DB transaction to avoid race conditions)
            DB::transaction(function() use ($faq) {
                $faq->increment('view_count');
            });

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
                'new_view_count' => $faq->view_count
            ]);

            return $this->successResponse([
                'faq' => $faq,
                'user_feedback' => $userFeedback
            ], 'FAQ retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ FAQ view failed', [
                'faq_id' => $faq->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->serverErrorResponse(
                'Failed to load FAQ',
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    /**
     * Provide feedback on FAQ
     */
    public function provideFeedback(Request $request, FAQ $faq): JsonResponse
    {
        try {
            Log::info('=== PROVIDING FAQ FEEDBACK ===', [
                'faq_id' => $faq->id,
                'user_id' => $request->user()->id,
                'request_data' => $request->except(['_token'])
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

                // Update FAQ counters
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

                return $this->successResponse([
                    'feedback' => $feedback,
                    'faq' => $faq->fresh(['category'])
                ], 'Thank you for your feedback!');

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
            
            return $this->serverErrorResponse(
                'Failed to submit feedback. Please try again.',
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    /**
     * Suggest new FAQ content (for counselors)
     */
    public function suggestContent(Request $request): JsonResponse
    {
        try {
            Log::info('=== SUGGESTING FAQ CONTENT ===', [
                'user_id' => $request->user()->id,
                'user_role' => $request->user()->role,
                'request_data' => $request->except(['_token'])
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
                    'created_by' => $user->id,
                ]);

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
                ], 'Content suggestion submitted for review. Thank you!', 201);

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
            
            return $this->serverErrorResponse(
                'Failed to submit suggestion. Please try again.',
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    /**
     * Get help statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            Log::info('=== FETCHING HELP STATS ===', [
                'user_id' => $request->user()?->id
            ]);

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

            Log::info('✅ Help stats retrieved successfully');

            return $this->successResponse([
                'stats' => $stats
            ], 'Help statistics retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Help stats fetch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->serverErrorResponse(
                'Failed to fetch statistics',
                config('app.debug') ? $e->getMessage() : null
            );
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