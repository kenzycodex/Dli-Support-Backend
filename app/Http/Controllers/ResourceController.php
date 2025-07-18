<?php
// app/Http/Controllers/ResourceController.php (FIXED - Updated your existing code)

namespace App\Http\Controllers;

use App\Models\ResourceCategory;
use App\Models\Resource;
use App\Models\ResourceFeedback;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Exception;

class ResourceController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get resource categories with resource counts
     */
    public function getCategories(Request $request): JsonResponse
    {
        $this->logRequestDetails('Resource Categories Fetch');

        try {
            Log::info('=== FETCHING RESOURCE CATEGORIES ===', [
                'user_id' => $request->user()?->id,
                'user_role' => $request->user()?->role
            ]);

            $categories = ResourceCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->withCount(['resources' => function ($query) {
                    $query->where('is_published', true);
                }])
                ->get();

            Log::info('✅ Resource categories fetched successfully', [
                'count' => $categories->count()
            ]);

            return $this->successResponse([
                'categories' => $categories
            ], 'Resource categories retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Resource categories fetch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'Resource categories fetch');
        }
    }

    /**
     * Get resources with filtering and search - FIXED with proper response structure
     */
    public function getResources(Request $request): JsonResponse
    {
        $this->logRequestDetails('Resources Fetch');

        try {
            Log::info('=== FETCHING RESOURCES ===', [
                'user_id' => $request->user()?->id,
                'user_role' => $request->user()?->role,
                'filters' => $request->only(['category', 'type', 'difficulty', 'search', 'featured'])
            ]);

            $validator = Validator::make($request->all(), [
                'category' => 'sometimes|string|exists:resource_categories,slug',
                'type' => 'sometimes|string|in:article,video,audio,exercise,tool,worksheet',
                'difficulty' => 'sometimes|string|in:beginner,intermediate,advanced',
                'search' => 'sometimes|string|max:255',
                'featured' => 'sometimes|boolean',
                'sort_by' => 'sometimes|in:featured,rating,downloads,newest,popular',
                'per_page' => 'sometimes|integer|min:1|max:50',
            ], [
                'category.exists' => 'Invalid category selected',
                'type.in' => 'Invalid resource type',
                'difficulty.in' => 'Invalid difficulty level',
                'search.max' => 'Search term cannot exceed 255 characters',
                'sort_by.in' => 'Invalid sort option',
                'per_page.min' => 'Items per page must be at least 1',
                'per_page.max' => 'Items per page cannot exceed 50',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Resources validation failed', [
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check your search criteria');
            }

            // Build query for published resources
            $query = Resource::where('is_published', true)
                ->with(['category:id,name,slug,color,icon']);

            // Apply filters
            if ($request->has('category') && $request->category !== 'all') {
                $category = ResourceCategory::where('slug', $request->category)->first();
                if ($category) {
                    $query->where('category_id', $category->id);
                    Log::info('Applied category filter', ['category_id' => $category->id]);
                }
            }

            if ($request->has('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
                Log::info('Applied type filter', ['type' => $request->type]);
            }

            if ($request->has('difficulty') && $request->difficulty !== 'all') {
                $query->where('difficulty', $request->difficulty);
                Log::info('Applied difficulty filter', ['difficulty' => $request->difficulty]);
            }

            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('tags', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('author_name', 'LIKE', "%{$searchTerm}%");
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
                          ->orderBy('rating', 'desc');
                    break;
                case 'rating':
                    $query->orderBy('rating', 'desc');
                    break;
                case 'downloads':
                    $query->orderBy('download_count', 'desc');
                    break;
                case 'popular':
                    $query->orderBy('view_count', 'desc');
                    break;
                case 'newest':
                    $query->orderBy('published_at', 'desc');
                    break;
                default:
                    $query->orderBy('sort_order', 'asc');
            }

            // Get paginated results
            $perPage = $request->get('per_page', 15);
            $paginatedResources = $query->paginate($perPage);

            // Get featured resources if not filtering
            $featuredResources = [];
            if (!$request->has('search') && !$request->boolean('featured')) {
                $featuredResources = Resource::where('is_published', true)
                    ->where('is_featured', true)
                    ->with(['category:id,name,slug,color,icon'])
                    ->orderBy('sort_order')
                    ->take(3)
                    ->get();
            }

            // Get resource type counts for filters
            $typeCounts = Resource::where('is_published', true)
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray();

            Log::info('✅ Resources fetched successfully', [
                'total' => $paginatedResources->total(),
                'featured_count' => count($featuredResources)
            ]);

            // CRITICAL FIX: Return consistent structure that frontend expects
            return $this->successResponse([
                'resources' => $paginatedResources->items(), // This ensures resources is an array
                'featured_resources' => $featuredResources,
                'type_counts' => $typeCounts,
                'pagination' => [
                    'current_page' => $paginatedResources->currentPage(),
                    'last_page' => $paginatedResources->lastPage(),
                    'per_page' => $paginatedResources->perPage(),
                    'total' => $paginatedResources->total(),
                    'from' => $paginatedResources->firstItem(),
                    'to' => $paginatedResources->lastItem(),
                    'has_more_pages' => $paginatedResources->hasMorePages(),
                ]
            ], 'Resources retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Resources fetch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $request->user()?->id
            ]);
            
            return $this->handleException($e, 'Resources fetch');
        }
    }

    /**
     * Get single resource with view tracking - FIXED
     */
    public function showResource(Request $request, Resource $resource): JsonResponse
    {
        $this->logRequestDetails('Resource View');

        try {
            Log::info('=== VIEWING RESOURCE ===', [
                'resource_id' => $resource->id,
                'user_id' => $request->user()?->id
            ]);

            // Check if resource is published
            if (!$resource->is_published) {
                Log::warning('❌ Attempt to view unpublished resource', [
                    'resource_id' => $resource->id,
                    'user_id' => $request->user()?->id
                ]);
                return $this->notFoundResponse('Resource not found or not available');
            }

            DB::beginTransaction();

            try {
                // Increment view count atomically
                $resource->increment('view_count');
                
                DB::commit();
                
                Log::info('✅ Resource view count incremented', [
                    'resource_id' => $resource->id,
                    'new_view_count' => $resource->view_count
                ]);

            } catch (Exception $e) {
                DB::rollBack();
                Log::warning('⚠️ Failed to increment view count', [
                    'resource_id' => $resource->id,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the request for view count issues
            }

            // Load relationships
            $resource->load(['category:id,name,slug,color,icon']);

            // Check if user has already provided feedback
            $userFeedback = null;
            if ($request->user()) {
                $userFeedback = ResourceFeedback::where('resource_id', $resource->id)
                    ->where('user_id', $request->user()->id)
                    ->first();
            }

            // Get related resources
            $relatedResources = Resource::where('is_published', true)
                ->where('id', '!=', $resource->id)
                ->where('category_id', $resource->category_id)
                ->orderBy('rating', 'desc')
                ->take(3)
                ->get(['id', 'title', 'type', 'rating', 'duration', 'slug']);

            Log::info('✅ Resource viewed successfully', [
                'resource_id' => $resource->id,
                'view_count' => $resource->view_count,
                'has_user_feedback' => !!$userFeedback
            ]);

            return $this->successResponse([
                'resource' => $resource,
                'user_feedback' => $userFeedback,
                'related_resources' => $relatedResources
            ], 'Resource retrieved successfully');

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('❌ Resource view failed', [
                'resource_id' => $resource->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->handleException($e, 'Resource view');
        }
    }

    /**
     * Access resource (track access and return URL) - FIXED
     */
    public function accessResource(Request $request, Resource $resource): JsonResponse
    {
        $this->logRequestDetails('Resource Access');

        try {
            Log::info('=== ACCESSING RESOURCE ===', [
                'resource_id' => $resource->id,
                'user_id' => $request->user()->id,
                'resource_type' => $resource->type
            ]);

            // Check if resource is published
            if (!$resource->is_published) {
                Log::warning('❌ Attempt to access unpublished resource', [
                    'resource_id' => $resource->id,
                    'user_id' => $request->user()->id
                ]);
                return $this->notFoundResponse('Resource not found or not available');
            }

            DB::beginTransaction();

            try {
                // Increment appropriate counter
                if ($resource->type === 'worksheet' && $resource->download_url) {
                    $resource->increment('download_count');
                    $url = $resource->download_url;
                    $action = 'download';
                } else {
                    $resource->increment('view_count');
                    $url = $resource->external_url;
                    $action = 'access';
                }

                DB::commit();

                Log::info("✅ Resource {$action}ed successfully", [
                    'resource_id' => $resource->id,
                    'action' => $action,
                    'user_id' => $request->user()->id
                ]);

                return $this->successResponse([
                    'url' => $url,
                    'action' => $action,
                    'resource' => $resource->only(['id', 'title', 'type'])
                ], "Resource {$action} granted");

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('❌ Resource access failed', [
                'resource_id' => $resource->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->handleException($e, 'Resource access');
        }
    }

    /**
     * Provide feedback/rating on resource - FIXED with correct method call
     */
    public function provideFeedback(Request $request, Resource $resource): JsonResponse
    {
        $this->logRequestDetails('Resource Feedback');

        try {
            Log::info('=== PROVIDING RESOURCE FEEDBACK ===', [
                'resource_id' => $resource->id,
                'user_id' => $request->user()->id,
            ]);

            $validator = Validator::make($request->all(), [
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'sometimes|string|max:1000',
                'is_recommended' => 'sometimes|boolean',
            ], [
                'rating.required' => 'Please provide a rating',
                'rating.integer' => 'Rating must be a number',
                'rating.min' => 'Rating must be at least 1 star',
                'rating.max' => 'Rating cannot exceed 5 stars',
                'comment.max' => 'Comment cannot exceed 1000 characters'
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Resource feedback validation failed', [
                    'resource_id' => $resource->id,
                    'user_id' => $request->user()->id,
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check your feedback and try again');
            }

            // Check if user already provided feedback
            $existingFeedback = ResourceFeedback::where('resource_id', $resource->id)
                ->where('user_id', $request->user()->id)
                ->first();

            DB::beginTransaction();

            try {
                if ($existingFeedback) {
                    // Update existing feedback
                    $existingFeedback->update([
                        'rating' => $request->rating,
                        'comment' => $request->get('comment'),
                        'is_recommended' => $request->get('is_recommended', true),
                    ]);
                    $feedback = $existingFeedback;
                    $message = 'Your feedback has been updated!';
                    
                    Log::info('Updated existing feedback', [
                        'feedback_id' => $feedback->id,
                        'resource_id' => $resource->id,
                    ]);
                } else {
                    // Create new feedback
                    $feedback = ResourceFeedback::create([
                        'resource_id' => $resource->id,
                        'user_id' => $request->user()->id,
                        'rating' => $request->rating,
                        'comment' => $request->get('comment'),
                        'is_recommended' => $request->get('is_recommended', true),
                    ]);
                    $message = 'Thank you for your feedback!';
                    
                    Log::info('Created new feedback', [
                        'feedback_id' => $feedback->id,
                        'resource_id' => $resource->id,
                    ]);
                }

                // FIXED: Update resource rating using private method
                $this->updateResourceRating($resource);

                DB::commit();

                Log::info('✅ Resource feedback submitted successfully', [
                    'feedback_id' => $feedback->id,
                    'resource_id' => $resource->id,
                    'rating' => $request->rating
                ]);

                return $this->successResponse([
                    'feedback' => $feedback,
                    'resource' => $resource->fresh(['category'])
                ], $message, $existingFeedback ? 200 : 201);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('❌ Resource feedback failed', [
                'resource_id' => $resource->id ?? 'unknown',
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->handleException($e, 'Resource feedback');
        }
    }

    /**
     * Bookmark/save resource for later - FIXED
     */
    public function bookmarkResource(Request $request, Resource $resource): JsonResponse
    {
        $this->logRequestDetails('Resource Bookmark');

        try {
            Log::info('=== BOOKMARKING RESOURCE ===', [
                'resource_id' => $resource->id,
                'user_id' => $request->user()->id
            ]);

            // Check if already bookmarked
            $existingBookmark = DB::table('user_bookmarks')
                ->where('user_id', $request->user()->id)
                ->where('resource_id', $resource->id)
                ->first();

            DB::beginTransaction();

            try {
                if ($existingBookmark) {
                    // Remove bookmark
                    DB::table('user_bookmarks')
                        ->where('user_id', $request->user()->id)
                        ->where('resource_id', $resource->id)
                        ->delete();

                    $message = 'Resource removed from bookmarks';
                    $bookmarked = false;
                    
                    Log::info('Removed bookmark', [
                        'resource_id' => $resource->id,
                        'user_id' => $request->user()->id
                    ]);
                } else {
                    // Add bookmark
                    DB::table('user_bookmarks')->insert([
                        'user_id' => $request->user()->id,
                        'resource_id' => $resource->id,
                        'created_at' => now(),
                    ]);

                    $message = 'Resource added to bookmarks';
                    $bookmarked = true;
                    
                    Log::info('Added bookmark', [
                        'resource_id' => $resource->id,
                        'user_id' => $request->user()->id
                    ]);
                }

                DB::commit();

                return $this->successResponse([
                    'bookmarked' => $bookmarked,
                    'resource_id' => $resource->id,
                ], $message);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('❌ Resource bookmark failed', [
                'resource_id' => $resource->id ?? 'unknown',
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->handleException($e, 'Resource bookmark');
        }
    }

    /**
     * Get user's bookmarked resources - FIXED with proper response format
     */
    public function getBookmarks(Request $request): JsonResponse
    {
        $this->logRequestDetails('Bookmarks Fetch');

        try {
            Log::info('=== FETCHING USER BOOKMARKS ===', [
                'user_id' => $request->user()->id
            ]);

            $bookmarksQuery = DB::table('user_bookmarks')
                ->join('resources', 'user_bookmarks.resource_id', '=', 'resources.id')
                ->join('resource_categories', 'resources.category_id', '=', 'resource_categories.id')
                ->where('user_bookmarks.user_id', $request->user()->id)
                ->where('resources.is_published', true)
                ->select([
                    'resources.*',
                    'resource_categories.name as category_name',
                    'resource_categories.slug as category_slug',
                    'resource_categories.color as category_color',
                    'user_bookmarks.created_at as bookmarked_at'
                ])
                ->orderBy('user_bookmarks.created_at', 'desc');

            $paginatedBookmarks = $bookmarksQuery->paginate(20);

            // Transform the data to match expected structure
            $bookmarks = collect($paginatedBookmarks->items())->map(function ($item) {
                $bookmark = (array) $item;
                $bookmark['category'] = [
                    'name' => $bookmark['category_name'],
                    'slug' => $bookmark['category_slug'],
                    'color' => $bookmark['category_color'],
                ];
                unset($bookmark['category_name'], $bookmark['category_slug'], $bookmark['category_color']);
                return $bookmark;
            });

            Log::info('✅ User bookmarks fetched successfully', [
                'user_id' => $request->user()->id,
                'total' => $paginatedBookmarks->total()
            ]);

            // CRITICAL FIX: Return consistent structure
            return $this->successResponse([
                'bookmarks' => $bookmarks->toArray(), // Ensure it's an array
                'pagination' => [
                    'current_page' => $paginatedBookmarks->currentPage(),
                    'last_page' => $paginatedBookmarks->lastPage(),
                    'per_page' => $paginatedBookmarks->perPage(),
                    'total' => $paginatedBookmarks->total(),
                    'from' => $paginatedBookmarks->firstItem(),
                    'to' => $paginatedBookmarks->lastItem(),
                    'has_more_pages' => $paginatedBookmarks->hasMorePages(),
                ]
            ], 'Bookmarks retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Bookmarks fetch failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->handleException($e, 'Bookmarks fetch');
        }
    }

    /**
     * Get resource statistics - FIXED
     */
    public function getStats(Request $request): JsonResponse
    {
        $this->logRequestDetails('Resource Stats Fetch');

        try {
            Log::info('=== FETCHING RESOURCE STATS ===', [
                'user_id' => $request->user()?->id
            ]);

            $stats = [
                'total_resources' => Resource::where('is_published', true)->count(),
                'total_categories' => ResourceCategory::where('is_active', true)->count(),
                'most_popular_resource' => Resource::where('is_published', true)
                    ->orderBy('view_count', 'desc')
                    ->first(['id', 'title', 'view_count', 'type']),
                'highest_rated_resource' => Resource::where('is_published', true)
                    ->orderBy('rating', 'desc')
                    ->first(['id', 'title', 'rating', 'type']),
                'most_downloaded_resource' => Resource::where('is_published', true)
                    ->orderBy('download_count', 'desc')
                    ->first(['id', 'title', 'download_count', 'type']),
                'resources_by_type' => Resource::where('is_published', true)
                    ->selectRaw('type, count(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type')
                    ->toArray(),
                'resources_by_difficulty' => Resource::where('is_published', true)
                    ->selectRaw('difficulty, count(*) as count')
                    ->groupBy('difficulty')
                    ->pluck('count', 'difficulty')
                    ->toArray(),
                'categories_with_counts' => ResourceCategory::where('is_active', true)
                    ->withCount(['resources' => function ($query) {
                        $query->where('is_published', true);
                    }])
                    ->orderBy('resources_count', 'desc')
                    ->get(['id', 'name', 'slug', 'color'])
            ];

            Log::info('✅ Resource stats retrieved successfully');

            return $this->successResponse([
                'stats' => $stats
            ], 'Resource statistics retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Resource stats fetch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->handleException($e, 'Resource stats fetch');
        }
    }

    /**
     * Get resource options for forms - FIXED
     */
    public function getOptions(Request $request): JsonResponse
    {
        $this->logRequestDetails('Resource Options Fetch');

        try {
            Log::info('=== FETCHING RESOURCE OPTIONS ===', [
                'user_id' => $request->user()?->id
            ]);

            $options = [
                'types' => [
                    ['value' => 'article', 'label' => 'Article', 'icon' => 'FileText'],
                    ['value' => 'video', 'label' => 'Video', 'icon' => 'Video'],
                    ['value' => 'audio', 'label' => 'Audio', 'icon' => 'Headphones'],
                    ['value' => 'exercise', 'label' => 'Exercise', 'icon' => 'Brain'],
                    ['value' => 'tool', 'label' => 'Tool', 'icon' => 'Heart'],
                    ['value' => 'worksheet', 'label' => 'Worksheet', 'icon' => 'Download'],
                ],
                'difficulties' => [
                    ['value' => 'beginner', 'label' => 'Beginner', 'color' => 'bg-green-100 text-green-800'],
                    ['value' => 'intermediate', 'label' => 'Intermediate', 'color' => 'bg-yellow-100 text-yellow-800'],
                    ['value' => 'advanced', 'label' => 'Advanced', 'color' => 'bg-red-100 text-red-800'],
                ],
                'categories' => ResourceCategory::where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['id', 'name', 'slug']),
            ];

            Log::info('✅ Resource options retrieved successfully');

            return $this->successResponse($options, 'Resource options retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Resource options fetch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->handleException($e, 'Resource options fetch');
        }
    }

    /**
     * Private helper to update resource rating
     */
    private function updateResourceRating(Resource $resource): void
    {
        try {
            $averageRating = ResourceFeedback::where('resource_id', $resource->id)
                ->avg('rating');

            $resource->update([
                'rating' => round($averageRating, 2)
            ]);

            Log::info('Updated resource rating', [
                'resource_id' => $resource->id,
                'new_rating' => $resource->rating
            ]);

        } catch (Exception $e) {
            Log::error('Failed to update resource rating', [
                'resource_id' => $resource->id,
                'error' => $e->getMessage()
            ]);
            // Don't throw - rating update failure shouldn't prevent feedback
        }
    }
}