<?php
// app/Http/Controllers/ResourceController.php

namespace App\Http\Controllers;

use App\Models\ResourceCategory;
use App\Models\Resource;
use App\Models\ResourceFeedback;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Exception;

class ResourceController extends Controller
{
    /**
     * Get resource categories with resource counts
     */
    public function getCategories(Request $request): JsonResponse
    {
        try {
            $categories = ResourceCategory::active()
                ->ordered()
                ->withCount(['resources' => function ($query) {
                    $query->published();
                }])
                ->get();

            return response()->json([
                'success' => true,
                'data' => ['categories' => $categories]
            ]);
        } catch (Exception $e) {
            Log::error('Resource categories fetch failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch resource categories.',
            ], 500);
        }
    }

    /**
     * Get resources with filtering and search
     */
    public function getResources(Request $request): JsonResponse
    {
        try {
            Log::info('=== FETCHING RESOURCES ===');
            Log::info('User: ' . $request->user()->id . ' (' . $request->user()->role . ')');

            $validator = Validator::make($request->all(), [
                'category' => 'sometimes|string|exists:resource_categories,slug',
                'type' => 'sometimes|string|in:article,video,audio,exercise,tool,worksheet',
                'difficulty' => 'sometimes|string|in:beginner,intermediate,advanced',
                'search' => 'sometimes|string|max:255',
                'featured' => 'sometimes|boolean',
                'sort_by' => 'sometimes|in:featured,rating,downloads,newest,popular',
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
            $query = Resource::published()->with(['category:id,name,slug,color,icon']);

            // Apply filters
            if ($request->has('category') && $request->category !== 'all') {
                $category = ResourceCategory::where('slug', $request->category)->first();
                if ($category) {
                    $query->where('category_id', $category->id);
                }
            }

            if ($request->has('type') && $request->type !== 'all') {
                $query->byType($request->type);
            }

            if ($request->has('difficulty') && $request->difficulty !== 'all') {
                $query->byDifficulty($request->difficulty);
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
                    ->featured()
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

            Log::info('Found ' . $resources->total() . ' resources');

            return response()->json([
                'success' => true,
                'data' => [
                    'resources' => $resources->items(),
                    'featured_resources' => $featuredResources,
                    'type_counts' => $typeCounts,
                    'pagination' => [
                        'current_page' => $resources->currentPage(),
                        'last_page' => $resources->lastPage(),
                        'per_page' => $resources->perPage(),
                        'total' => $resources->total(),
                    ]
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Resources fetch failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch resources.',
            ], 500);
        }
    }

    /**
     * Get single resource with view tracking
     */
    public function showResource(Request $request, Resource $resource): JsonResponse
    {
        try {
            Log::info('=== VIEWING RESOURCE ===');
            Log::info('Resource ID: ' . $resource->id);

            // Check if resource is published
            if (!$resource->is_published) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found or not published.'
                ], 404);
            }

            // Increment view count
            $resource->incrementViews();

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

            return response()->json([
                'success' => true,
                'data' => [
                    'resource' => $resource,
                    'user_feedback' => $userFeedback,
                    'related_resources' => $relatedResources
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Resource view failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch resource.',
            ], 500);
        }
    }

    /**
     * Access resource (track access and return URL)
     */
    public function accessResource(Request $request, Resource $resource): JsonResponse
    {
        try {
            Log::info('=== ACCESSING RESOURCE ===');
            Log::info('Resource ID: ' . $resource->id);
            Log::info('User: ' . $request->user()->id);

            // Check if resource is published
            if (!$resource->is_published) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found or not published.'
                ], 404);
            }

            // Increment appropriate counter
            if ($resource->type === 'worksheet' && $resource->download_url) {
                $resource->incrementDownloads();
                $url = $resource->download_url;
                $action = 'download';
            } else {
                $resource->incrementViews();
                $url = $resource->external_url;
                $action = 'access';
            }

            // Log access for analytics
            Log::info("✅ Resource {$action}ed: {$resource->title} by user {$request->user()->id}");

            return response()->json([
                'success' => true,
                'message' => "Resource {$action} granted",
                'data' => [
                    'url' => $url,
                    'action' => $action,
                    'resource' => $resource->only(['id', 'title', 'type'])
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Resource access failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to access resource.',
            ], 500);
        }
    }

    /**
     * Provide feedback/rating on resource
     */
    public function provideFeedback(Request $request, Resource $resource): JsonResponse
    {
        try {
            Log::info('=== PROVIDING RESOURCE FEEDBACK ===');
            Log::info('Resource ID: ' . $resource->id);
            Log::info('User: ' . $request->user()->id);

            $validator = Validator::make($request->all(), [
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'sometimes|string|max:1000',
                'is_recommended' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if user already provided feedback
            $existingFeedback = ResourceFeedback::where('resource_id', $resource->id)
                ->where('user_id', $request->user()->id)
                ->first();

            DB::beginTransaction();

            if ($existingFeedback) {
                // Update existing feedback
                $existingFeedback->update([
                    'rating' => $request->rating,
                    'comment' => $request->get('comment'),
                    'is_recommended' => $request->get('is_recommended', true),
                ]);
                $feedback = $existingFeedback;
                $message = 'Your feedback has been updated!';
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
            }

            // Update resource rating
            $resource->updateRating();

            DB::commit();

            Log::info('✅ Resource feedback submitted successfully');

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => ['feedback' => $feedback]
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Resource feedback failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit feedback.',
            ], 500);
        }
    }

    /**
     * Bookmark/save resource for later
     */
    public function bookmarkResource(Request $request, Resource $resource): JsonResponse
    {
        try {
            Log::info('=== BOOKMARKING RESOURCE ===');
            Log::info('Resource ID: ' . $resource->id);
            Log::info('User: ' . $request->user()->id);

            // Check if already bookmarked
            $existingBookmark = DB::table('user_bookmarks')
                ->where('user_id', $request->user()->id)
                ->where('resource_id', $resource->id)
                ->first();

            if ($existingBookmark) {
                // Remove bookmark
                DB::table('user_bookmarks')
                    ->where('user_id', $request->user()->id)
                    ->where('resource_id', $resource->id)
                    ->delete();

                $message = 'Resource removed from bookmarks';
                $bookmarked = false;
            } else {
                // Add bookmark
                DB::table('user_bookmarks')->insert([
                    'user_id' => $request->user()->id,
                    'resource_id' => $resource->id,
                    'created_at' => now(),
                ]);

                $message = 'Resource added to bookmarks';
                $bookmarked = true;
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => ['bookmarked' => $bookmarked]
            ]);
        } catch (Exception $e) {
            Log::error('Resource bookmark failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to bookmark resource.',
            ], 500);
        }
    }

    /**
     * Get user's bookmarked resources
     */
    public function getBookmarks(Request $request): JsonResponse
    {
        try {
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

            return response()->json([
                'success' => true,
                'data' => [
                    'bookmarks' => $bookmarks->items(),
                    'pagination' => [
                        'current_page' => $bookmarks->currentPage(),
                        'last_page' => $bookmarks->lastPage(),
                        'per_page' => $bookmarks->perPage(),
                        'total' => $bookmarks->total(),
                    ]
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Bookmarks fetch failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bookmarks.',
            ], 500);
        }
    }

    /**
     * Get resource statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
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

            return response()->json([
                'success' => true,
                'data' => ['stats' => $stats]
            ]);
        } catch (Exception $e) {
            Log::error('Resource stats failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics.',
            ], 500);
        }
    }

    /**
     * Get resource options for forms
     */
    public function getOptions(Request $request): JsonResponse
    {
        try {
            $options = [
                'types' => Resource::getAvailableTypes(),
                'difficulties' => Resource::getAvailableDifficulties(),
                'categories' => ResourceCategory::active()
                    ->ordered()
                    ->get(['id', 'name', 'slug']),
            ];

            return response()->json([
                'success' => true,
                'data' => $options
            ]);
        } catch (Exception $e) {
            Log::error('Resource options fetch failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch options.',
            ], 500);
        }
    }
}