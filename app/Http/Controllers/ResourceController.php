<?php
// app/Http/Controllers/ResourceController.php (FIXED)

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

            $categories = ResourceCategory::active()
                ->ordered()
                ->withCount(['resources' => function ($query) {
                    $query->published();
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
     * Get resources with filtering and search - FIXED
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
                return $this->validationErrorResponse($validator);
            }

            // Build query
            $query = Resource::published()->with(['category:id,name,slug,color,icon']);

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
            $resources = $query->paginate($perPage);

            // Get featured resources if not filtering
            $featuredResources = [];
            if (!$request->has('search') && !$request->boolean('featured')) {
                $featuredResources = Resource::published()
                    ->where('is_featured', true)
                    ->with(['category:id,name,slug,color,icon'])
                    ->orderBy('sort_order')
                    ->take(3)
                    ->get();
            }

            // Get resource type counts for filters
            $typeCounts = Resource::published()
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray();

            Log::info('✅ Resources fetched successfully', [
                'total' => $resources->total(),
                'featured_count' => count($featuredResources)
            ]);

            return $this->paginatedResponse($resources, 'Resources retrieved successfully')
                ->setData(array_merge($this->paginatedResponse($resources)->getData(true), [
                    'data' => array_merge($this->paginatedResponse($resources)->getData(true)['data'], [
                        'featured_resources' => $featuredResources,
                        'type_counts' => $typeCounts,
                    ])
                ]));

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

            // Increment view count (use DB transaction to avoid race conditions)
            DB::transaction(function() use ($resource) {
                $resource->increment('view_count');
            });

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
            $relatedResources = Resource::published()
                ->where('id', '!=', $resource->id)
                ->where('category_id', $resource->category_id)
                ->orderBy('rating', 'desc')
                ->take(3)
                ->get(['id', 'title', 'type', 'rating', 'duration', 'slug']);

            Log::info('✅ Resource viewed successfully', [
                'resource_id' => $resource->id,
                'new_view_count' => $resource->view_count
            ]);

            return $this->successResponse([
                'resource' => $resource,
                'user_feedback' => $userFeedback,
                'related_resources' => $relatedResources
            ], 'Resource retrieved successfully');

        } catch (Exception $e) {
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

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
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
     * Provide feedback/rating on resource - FIXED
     */
    public function provideFeedback(Request $request, Resource $resource): JsonResponse
    {
        $this->logRequestDetails('Resource Feedback');

        try {
            Log::info('=== PROVIDING RESOURCE FEEDBACK ===', [
                'resource_id' => $resource->id,
                'user_id' => $request->user()->id,
                'request_data' => $request->except(['_token'])
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

                // Update resource rating (this should be a method on the Resource model)
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
                ], $message);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
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

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
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
     * Get user's bookmarked resources - FIXED
     */
    public function getBookmarks(Request $request): JsonResponse
    {
        $this->logRequestDetails('Bookmarks Fetch');

        try {
            Log::info('=== FETCHING USER BOOKMARKS ===', [
                'user_id' => $request->user()->id
            ]);

            $bookmarks = DB::table('user_bookmarks')
                ->join('resources', 'user_bookmarks.resource_id', '=', 'resources.id')
                ->join('resource_categories', 'resources.category_id', '=', 'resource_categories.id')
                ->where('user_bookmarks.user_id', $request->user()->id)
                ->where('resources.is_published', true)
                ->select([
                    'resources.*',
                    'resource_categories.name as category_name',
                    'resource_categories.slug as category_slug',
                    'user_bookmarks.created_at as bookmarked_at'
                ])
                ->orderBy('user_bookmarks.created_at', 'desc')
                ->paginate(20);

            Log::info('✅ User bookmarks fetched successfully', [
                'user_id' => $request->user()->id,
                'total' => $bookmarks->total()
            ]);

            return $this->paginatedResponse($bookmarks, 'Bookmarks retrieved successfully');

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
                'total_resources' => Resource::published()->count(),
                'total_categories' => ResourceCategory::active()->count(),
                'most_popular_resource' => Resource::published()
                    ->orderBy('view_count', 'desc')
                    ->first(['id', 'title', 'view_count', 'type']),
                'highest_rated_resource' => Resource::published()
                    ->orderBy('rating', 'desc')
                    ->first(['id', 'title', 'rating', 'type']),
                'most_downloaded_resource' => Resource::published()
                    ->orderBy('download_count', 'desc')
                    ->first(['id', 'title', 'download_count', 'type']),
                'resources_by_type' => Resource::published()
                    ->selectRaw('type, count(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type')
                    ->toArray(),
                'resources_by_difficulty' => Resource::published()
                    ->selectRaw('difficulty, count(*) as count')
                    ->groupBy('difficulty')
                    ->pluck('count', 'difficulty')
                    ->toArray(),
                'categories_with_counts' => ResourceCategory::active()
                    ->withCount(['resources' => function ($query) {
                        $query->published();
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
                'types' => $this->getAvailableTypes(),
                'difficulties' => $this->getAvailableDifficulties(),
                'categories' => ResourceCategory::active()
                    ->ordered()
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

    /**
     * Get available resource types
     */
    private function getAvailableTypes(): array
    {
        return [
            'article' => 'Article',
            'video' => 'Video',
            'audio' => 'Audio',
            'exercise' => 'Exercise',
            'tool' => 'Tool',
            'worksheet' => 'Worksheet',
        ];
    }

    /**
     * Get available difficulty levels
     */
    private function getAvailableDifficulties(): array
    {
        return [
            'beginner' => 'Beginner',
            'intermediate' => 'Intermediate',
            'advanced' => 'Advanced',
        ];
    }
}