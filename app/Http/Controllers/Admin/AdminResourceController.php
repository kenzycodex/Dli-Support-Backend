<?php
// app/Http/Controllers/Admin/AdminResourceController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResourceCategory;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Exception;

class AdminResourceController extends Controller
{
    /**
     * Get all resource categories (including inactive)
     */
    public function getCategories(Request $request): JsonResponse
    {
        try {
            $categories = ResourceCategory::withCount(['resources'])
                ->orderBy('sort_order')
                ->get();

            return response()->json([
                'success' => true,
                'data' => ['categories' => $categories]
            ]);
        } catch (Exception $e) {
            Log::error('Admin resource categories fetch failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch resource categories.',
            ], 500);
        }
    }

    /**
     * Create new resource category
     */
    public function storeCategory(Request $request): JsonResponse
    {
        try {
            Log::info('=== CREATING RESOURCE CATEGORY ===');
            Log::info('User: ' . $request->user()->id . ' (' . $request->user()->role . ')');

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:resource_categories,name',
                'description' => 'nullable|string|max:1000',
                'icon' => 'nullable|string|max:100',
                'color' => 'nullable|string|regex:/^#[A-Fa-f0-9]{6}$/',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $category = ResourceCategory::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'icon' => $request->get('icon', 'BookOpen'),
                'color' => $request->get('color', '#3B82F6'),
                'sort_order' => $request->get('sort_order', 0),
                'is_active' => $request->get('is_active', true),
            ]);

            Log::info('✅ Resource category created: ' . $category->name);

            return response()->json([
                'success' => true,
                'message' => 'Resource category created successfully',
                'data' => ['category' => $category]
            ], 201);
        } catch (Exception $e) {
            Log::error('Resource category creation failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create resource category.',
            ], 500);
        }
    }

    /**
     * Update resource category
     */
    public function updateCategory(Request $request, ResourceCategory $category): JsonResponse
    {
        try {
            Log::info('=== UPDATING RESOURCE CATEGORY ===');
            Log::info('Category ID: ' . $category->id);

            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('resource_categories')->ignore($category->id)
                ],
                'description' => 'nullable|string|max:1000',
                'icon' => 'nullable|string|max:100',
                'color' => 'nullable|string|regex:/^#[A-Fa-f0-9]{6}$/',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $category->update([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'icon' => $request->get('icon', $category->icon),
                'color' => $request->get('color', $category->color),
                'sort_order' => $request->get('sort_order', $category->sort_order),
                'is_active' => $request->get('is_active', $category->is_active),
            ]);

            Log::info('✅ Resource category updated: ' . $category->name);

            return response()->json([
                'success' => true,
                'message' => 'Resource category updated successfully',
                'data' => ['category' => $category]
            ]);
        } catch (Exception $e) {
            Log::error('Resource category update failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update resource category.',
            ], 500);
        }
    }

    /**
     * Delete resource category
     */
    public function destroyCategory(Request $request, ResourceCategory $category): JsonResponse
    {
        try {
            Log::info('=== DELETING RESOURCE CATEGORY ===');
            Log::info('Category ID: ' . $category->id);

            // Check if category has resources
            if ($category->resources()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category with existing resources. Please move or delete resources first.'
                ], 422);
            }

            $categoryName = $category->name;
            $category->delete();

            Log::info('✅ Resource category deleted: ' . $categoryName);

            return response()->json([
                'success' => true,
                'message' => 'Resource category deleted successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Resource category deletion failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete resource category.',
            ], 500);
        }
    }

    /**
     * Get all resources (including unpublished)
     */
    public function getResources(Request $request): JsonResponse
    {
			try {
				$validator = Validator::make($request->all(), [
						'category_id' => 'sometimes|exists:resource_categories,id',
						'type' => 'sometimes|string|in:article,video,audio,exercise,tool,worksheet',
						'difficulty' => 'sometimes|string|in:beginner,intermediate,advanced',
						'status' => 'sometimes|in:all,published,unpublished',
						'search' => 'sometimes|string|max:255',
						'sort_by' => 'sometimes|in:newest,oldest,rating,views,downloads,category',
						'per_page' => 'sometimes|integer|min:1|max:100',
				]);

				if ($validator->fails()) {
						return response()->json([
								'success' => false,
								'message' => 'Validation failed',
								'errors' => $validator->errors()
						], 422);
				}

				// Build query
				$query = Resource::with(['category:id,name,slug,color', 'creator:id,name,email']);

				// Apply filters
				if ($request->has('category_id')) {
						$query->where('category_id', $request->category_id);
				}

				if ($request->has('type') && $request->type !== 'all') {
						$query->byType($request->type);
				}

				if ($request->has('difficulty') && $request->difficulty !== 'all') {
						$query->byDifficulty($request->difficulty);
				}

				if ($request->has('status') && $request->status !== 'all') {
						switch ($request->status) {
								case 'published':
										$query->where('is_published', true);
										break;
								case 'unpublished':
										$query->where('is_published', false);
										break;
						}
				}

				if ($request->has('search') && !empty($request->search)) {
						$query->search($request->search);
				}

				// Apply sorting
				$sortBy = $request->get('sort_by', 'newest');
				switch ($sortBy) {
						case 'oldest':
								$query->orderBy('created_at', 'asc');
								break;
						case 'rating':
								$query->orderBy('rating', 'desc');
								break;
						case 'views':
								$query->orderBy('view_count', 'desc');
								break;
						case 'downloads':
								$query->orderBy('download_count', 'desc');
								break;
						case 'category':
								$query->join('resource_categories', 'resources.category_id', '=', 'resource_categories.id')
											->orderBy('resource_categories.name', 'asc')
											->select('resources.*');
								break;
						default:
								$query->orderBy('created_at', 'desc');
				}

				$perPage = $request->get('per_page', 20);
				$resources = $query->paginate($perPage);

				return response()->json([
						'success' => true,
						'data' => [
								'resources' => $resources->items(),
								'pagination' => [
										'current_page' => $resources->currentPage(),
										'last_page' => $resources->lastPage(),
										'per_page' => $resources->perPage(),
										'total' => $resources->total(),
								]
						]
				]);
		} catch (Exception $e) {
				Log::error('Admin resources fetch failed: ' . $e->getMessage());
				
				return response()->json([
						'success' => false,
						'message' => 'Failed to fetch resources.',
				], 500);
		}
}

/**
 * Create new resource
 */
public function storeResource(Request $request): JsonResponse
{
		try {
				Log::info('=== CREATING RESOURCE ===');
				Log::info('User: ' . $request->user()->id . ' (' . $request->user()->role . ')');

				$validator = Validator::make($request->all(), [
						'category_id' => 'required|exists:resource_categories,id',
						'title' => 'required|string|min:5|max:255',
						'description' => 'required|string|min:20|max:2000',
						'type' => 'required|in:article,video,audio,exercise,tool,worksheet',
						'subcategory' => 'nullable|string|max:100',
						'difficulty' => 'required|in:beginner,intermediate,advanced',
						'duration' => 'nullable|string|max:50',
						'external_url' => 'required|url|max:500',
						'download_url' => 'nullable|url|max:500',
						'thumbnail_url' => 'nullable|url|max:500',
						'tags' => 'sometimes|array|max:10',
						'tags.*' => 'string|max:50',
						'author_name' => 'nullable|string|max:255',
						'author_bio' => 'nullable|string|max:1000',
						'sort_order' => 'nullable|integer|min:0',
						'is_published' => 'boolean',
						'is_featured' => 'boolean',
				]);

				if ($validator->fails()) {
						return response()->json([
								'success' => false,
								'message' => 'Validation failed',
								'errors' => $validator->errors()
						], 422);
				}

				$resource = Resource::create([
						'category_id' => $request->category_id,
						'title' => $request->title,
						'description' => $request->description,
						'slug' => Str::slug($request->title) . '-' . time(),
						'type' => $request->type,
						'subcategory' => $request->subcategory,
						'difficulty' => $request->difficulty,
						'duration' => $request->duration,
						'external_url' => $request->external_url,
						'download_url' => $request->download_url,
						'thumbnail_url' => $request->thumbnail_url,
						'tags' => $request->get('tags', []),
						'author_name' => $request->author_name,
						'author_bio' => $request->author_bio,
						'sort_order' => $request->get('sort_order', 0),
						'is_published' => $request->get('is_published', false),
						'is_featured' => $request->get('is_featured', false),
						'created_by' => $request->user()->id,
						'published_at' => $request->get('is_published') ? now() : null,
				]);

				$resource->load(['category:id,name,slug,color']);

				Log::info('✅ Resource created: ' . $resource->title);

				return response()->json([
						'success' => true,
						'message' => 'Resource created successfully',
						'data' => ['resource' => $resource]
				], 201);
		} catch (Exception $e) {
				Log::error('Resource creation failed: ' . $e->getMessage());
				
				return response()->json([
						'success' => false,
						'message' => 'Failed to create resource.',
				], 500);
		}
}

/**
 * Update resource
 */
public function updateResource(Request $request, Resource $resource): JsonResponse
{
		try {
				Log::info('=== UPDATING RESOURCE ===');
				Log::info('Resource ID: ' . $resource->id);

				$validator = Validator::make($request->all(), [
						'category_id' => 'required|exists:resource_categories,id',
						'title' => 'required|string|min:5|max:255',
						'description' => 'required|string|min:20|max:2000',
						'type' => 'required|in:article,video,audio,exercise,tool,worksheet',
						'subcategory' => 'nullable|string|max:100',
						'difficulty' => 'required|in:beginner,intermediate,advanced',
						'duration' => 'nullable|string|max:50',
						'external_url' => 'required|url|max:500',
						'download_url' => 'nullable|url|max:500',
						'thumbnail_url' => 'nullable|url|max:500',
						'tags' => 'sometimes|array|max:10',
						'tags.*' => 'string|max:50',
						'author_name' => 'nullable|string|max:255',
						'author_bio' => 'nullable|string|max:1000',
						'sort_order' => 'nullable|integer|min:0',
						'is_published' => 'boolean',
						'is_featured' => 'boolean',
				]);

				if ($validator->fails()) {
						return response()->json([
								'success' => false,
								'message' => 'Validation failed',
								'errors' => $validator->errors()
						], 422);
				}

				$wasPublished = $resource->is_published;
				$willBePublished = $request->get('is_published', $resource->is_published);

				$resource->update([
						'category_id' => $request->category_id,
						'title' => $request->title,
						'description' => $request->description,
						'slug' => Str::slug($request->title) . '-' . $resource->id,
						'type' => $request->type,
						'subcategory' => $request->subcategory,
						'difficulty' => $request->difficulty,
						'duration' => $request->duration,
						'external_url' => $request->external_url,
						'download_url' => $request->download_url,
						'thumbnail_url' => $request->thumbnail_url,
						'tags' => $request->get('tags', $resource->tags),
						'author_name' => $request->author_name,
						'author_bio' => $request->author_bio,
						'sort_order' => $request->get('sort_order', $resource->sort_order),
						'is_published' => $willBePublished,
						'is_featured' => $request->get('is_featured', $resource->is_featured),
						'updated_by' => $request->user()->id,
						'published_at' => (!$wasPublished && $willBePublished) ? now() : $resource->published_at,
				]);

				$resource->load(['category:id,name,slug,color']);

				Log::info('✅ Resource updated: ' . $resource->title);

				return response()->json([
						'success' => true,
						'message' => 'Resource updated successfully',
						'data' => ['resource' => $resource]
				]);
		} catch (Exception $e) {
				Log::error('Resource update failed: ' . $e->getMessage());
				
				return response()->json([
						'success' => false,
						'message' => 'Failed to update resource.',
				], 500);
		}
}

/**
 * Delete resource
 */
public function destroyResource(Request $request, Resource $resource): JsonResponse
{
		try {
				Log::info('=== DELETING RESOURCE ===');
				Log::info('Resource ID: ' . $resource->id);

				$resourceTitle = $resource->title;
				$resource->delete();

				Log::info('✅ Resource deleted: ' . $resourceTitle);

				return response()->json([
						'success' => true,
						'message' => 'Resource deleted successfully'
				]);
		} catch (Exception $e) {
				Log::error('Resource deletion failed: ' . $e->getMessage());
				
				return response()->json([
						'success' => false,
						'message' => 'Failed to delete resource.',
				], 500);
		}
}

/**
 * Bulk actions on resources
 */
public function bulkActionResources(Request $request): JsonResponse
{
		try {
				Log::info('=== BULK RESOURCE ACTION ===');

				$validator = Validator::make($request->all(), [
						'action' => 'required|in:publish,unpublish,feature,unfeature,delete,move_category',
						'resource_ids' => 'required|array|min:1',
						'resource_ids.*' => 'exists:resources,id',
						'category_id' => 'required_if:action,move_category|exists:resource_categories,id',
				]);

				if ($validator->fails()) {
						return response()->json([
								'success' => false,
								'message' => 'Validation failed',
								'errors' => $validator->errors()
						], 422);
				}

				$resourceIds = $request->resource_ids;
				$action = $request->action;
				$affected = 0;

				DB::beginTransaction();

				switch ($action) {
						case 'publish':
								$affected = Resource::whereIn('id', $resourceIds)->update([
										'is_published' => true,
										'published_at' => now(),
										'updated_by' => $request->user()->id,
								]);
								break;

						case 'unpublish':
								$affected = Resource::whereIn('id', $resourceIds)->update([
										'is_published' => false,
										'updated_by' => $request->user()->id,
								]);
								break;

						case 'feature':
								$affected = Resource::whereIn('id', $resourceIds)->update([
										'is_featured' => true,
										'updated_by' => $request->user()->id,
								]);
								break;

						case 'unfeature':
								$affected = Resource::whereIn('id', $resourceIds)->update([
										'is_featured' => false,
										'updated_by' => $request->user()->id,
								]);
								break;

						case 'move_category':
								$affected = Resource::whereIn('id', $resourceIds)->update([
										'category_id' => $request->category_id,
										'updated_by' => $request->user()->id,
								]);
								break;

						case 'delete':
								$affected = Resource::whereIn('id', $resourceIds)->count();
								Resource::whereIn('id', $resourceIds)->delete();
								break;
				}

				DB::commit();

				Log::info("✅ Bulk action '{$action}' applied to {$affected} resources");

				return response()->json([
						'success' => true,
						'message' => "Successfully applied '{$action}' to {$affected} resources"
				]);
		} catch (Exception $e) {
				DB::rollBack();
				Log::error('Bulk resource action failed: ' . $e->getMessage());
				
				return response()->json([
						'success' => false,
						'message' => 'Failed to perform bulk action.',
				], 500);
		}
}

/**
 * Get resource analytics
 */
public function getAnalytics(Request $request): JsonResponse
{
		try {
				$analytics = [
						'overview' => [
								'total_resources' => Resource::count(),
								'published_resources' => Resource::published()->count(),
								'unpublished_resources' => Resource::where('is_published', false)->count(),
								'featured_resources' => Resource::featured()->count(),
								'total_categories' => ResourceCategory::count(),
								'active_categories' => ResourceCategory::active()->count(),
						],
						'engagement' => [
								'total_views' => Resource::sum('view_count'),
								'total_downloads' => Resource::sum('download_count'),
								'average_rating' => Resource::where('rating', '>', 0)->avg('rating'),
								'total_feedback' => DB::table('resource_feedback')->count(),
						],
						'content_breakdown' => [
								'by_type' => Resource::published()
										->selectRaw('type, count(*) as count')
										->groupBy('type')
										->pluck('count', 'type')
										->toArray(),
								'by_difficulty' => Resource::published()
										->selectRaw('difficulty, count(*) as count')
										->groupBy('difficulty')
										->pluck('count', 'difficulty')
										->toArray(),
						],
						'top_resources' => [
								'most_viewed' => Resource::published()->orderBy('view_count', 'desc')->take(5)->get(['id', 'title', 'view_count', 'type']),
								'most_downloaded' => Resource::published()->orderBy('download_count', 'desc')->take(5)->get(['id', 'title', 'download_count', 'type']),
								'highest_rated' => Resource::published()->orderBy('rating', 'desc')->take(5)->get(['id', 'title', 'rating', 'type']),
						],
						'categories_performance' => ResourceCategory::withCount(['resources'])
								->withSum('resources', 'view_count')
								->withSum('resources', 'download_count')
								->withAvg('resources', 'rating')
								->get(['id', 'name', 'slug']),
						'recent_activity' => [
								'recent_resources' => Resource::orderBy('created_at', 'desc')->take(5)->get(['id', 'title', 'created_at', 'is_published', 'type']),
								'recent_feedback' => DB::table('resource_feedback')
										->join('resources', 'resource_feedback.resource_id', '=', 'resources.id')
										->join('users', 'resource_feedback.user_id', '=', 'users.id')
										->orderBy('resource_feedback.created_at', 'desc')
										->take(5)
										->get(['resources.title', 'users.name', 'resource_feedback.rating', 'resource_feedback.created_at']),
						]
				];

				return response()->json([
						'success' => true,
						'data' => ['analytics' => $analytics]
				]);
		} catch (Exception $e) {
				Log::error('Resource analytics failed: ' . $e->getMessage());
				
				return response()->json([
						'success' => false,
						'message' => 'Failed to fetch analytics.',
				], 500);
		}
}

/**
 * Export resources data
 */
public function exportResources(Request $request): JsonResponse
{
		try {
				Log::info('=== EXPORTING RESOURCES ===');

				$validator = Validator::make($request->all(), [
						'format' => 'sometimes|in:csv,json',
						'filters' => 'sometimes|array',
				]);

				if ($validator->fails()) {
						return response()->json([
								'success' => false,
								'message' => 'Validation failed',
								'errors' => $validator->errors()
						], 422);
				}

				$query = Resource::with(['category:id,name,slug']);

				// Apply filters if provided
				$filters = $request->get('filters', []);
				if (isset($filters['category_id'])) {
						$query->where('category_id', $filters['category_id']);
				}
				if (isset($filters['type'])) {
						$query->where('type', $filters['type']);
				}
				if (isset($filters['is_published'])) {
						$query->where('is_published', $filters['is_published']);
				}

				$resources = $query->orderBy('created_at', 'desc')->get();

				// Prepare export data
				$exportData = $resources->map(function ($resource) {
						return [
								'id' => $resource->id,
								'title' => $resource->title,
								'description' => $resource->description,
								'category' => $resource->category->name,
								'type' => $resource->type,
								'difficulty' => $resource->difficulty,
								'duration' => $resource->duration,
								'author_name' => $resource->author_name,
								'rating' => $resource->rating,
								'view_count' => $resource->view_count,
								'download_count' => $resource->download_count,
								'is_published' => $resource->is_published ? 'Yes' : 'No',
								'is_featured' => $resource->is_featured ? 'Yes' : 'No',
								'external_url' => $resource->external_url,
								'download_url' => $resource->download_url,
								'created_at' => $resource->created_at->format('Y-m-d H:i:s'),
								'published_at' => $resource->published_at ? $resource->published_at->format('Y-m-d H:i:s') : '',
						];
				});

				$format = $request->get('format', 'csv');
				$filename = "resources_export_" . now()->format('Y-m-d_H-i-s') . ".{$format}";

				Log::info("✅ Exporting {$resources->count()} resources in {$format} format");

				return response()->json([
						'success' => true,
						'message' => "Successfully exported {$resources->count()} resources",
						'data' => [
								'resources' => $exportData,
								'filename' => $filename,
								'format' => $format,
								'count' => $resources->count()
						]
				]);
		} catch (Exception $e) {
				Log::error('Resource export failed: ' . $e->getMessage());
				
				return response()->json([
						'success' => false,
						'message' => 'Failed to export resources.',
				], 500);
		}
}
}