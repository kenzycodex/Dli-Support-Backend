<?php
// app/Http/Controllers/Admin/AdminResourceController.php (FIXED)

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResourceCategory;
use App\Models\Resource;
use App\Traits\ApiResponseTrait;
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
    use ApiResponseTrait;

    /**
     * Get all resource categories (including inactive)
     */
    public function getCategories(Request $request): JsonResponse
    {
        $this->logRequestDetails('Admin Resource Categories Fetch');

        try {
            Log::info('=== FETCHING ADMIN RESOURCE CATEGORIES ===', [
                'user_id' => $request->user()->id,
                'user_role' => $request->user()->role,
            ]);

            $categories = ResourceCategory::withCount(['resources'])
                ->orderBy('sort_order')
                ->get();

            Log::info('✅ Admin resource categories fetched successfully', [
                'count' => $categories->count()
            ]);

            return $this->successResponse([
                'categories' => $categories
            ], 'Resource categories retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Admin resource categories fetch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'Resource categories fetch');
        }
    }

    /**
     * Create new resource category - FIXED
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $this->logRequestDetails('Resource Category Creation');

        try {
            Log::info('=== CREATING RESOURCE CATEGORY ===', [
                'user_id' => $request->user()->id,
                'user_role' => $request->user()->role,
            ]);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:resource_categories,name',
                'description' => 'nullable|string|max:1000',
                'icon' => 'nullable|string|max:100',
                'color' => 'nullable|string|regex:/^#[A-Fa-f0-9]{6}$/',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
            ], [
                'name.required' => 'Category name is required',
                'name.unique' => 'A category with this name already exists',
                'name.max' => 'Category name cannot exceed 255 characters',
                'description.max' => 'Description cannot exceed 1000 characters',
                'color.regex' => 'Color must be a valid hex color code (e.g., #FF0000)',
                'sort_order.min' => 'Sort order must be a positive number',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Resource category validation failed', [
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check your input and try again');
            }

            DB::beginTransaction();

            try {
                $category = ResourceCategory::create([
                    'name' => trim($request->name),
                    'slug' => Str::slug($request->name),
                    'description' => $request->description,
                    'icon' => $request->get('icon', 'BookOpen'),
                    'color' => $request->get('color', '#3B82F6'),
                    'sort_order' => $request->get('sort_order', 0),
                    'is_active' => $request->get('is_active', true),
                ]);

                DB::commit();

                Log::info('✅ Resource category created successfully', [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                ]);

                return $this->successResponse([
                    'category' => $category
                ], 'Resource category created successfully', 201);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('🚨 Resource category creation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'Resource category creation');
        }
    }

    /**
     * Update resource category - FIXED
     */
    public function updateCategory(Request $request, ResourceCategory $category): JsonResponse
    {
        $this->logRequestDetails('Resource Category Update');

        try {
            Log::info('=== UPDATING RESOURCE CATEGORY ===', [
                'category_id' => $category->id,
                'category_name' => $category->name,
            ]);

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
            ], [
                'name.required' => 'Category name is required',
                'name.unique' => 'A category with this name already exists',
                'name.max' => 'Category name cannot exceed 255 characters',
                'description.max' => 'Description cannot exceed 1000 characters',
                'color.regex' => 'Color must be a valid hex color code (e.g., #FF0000)',
                'sort_order.min' => 'Sort order must be a positive number',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Resource category update validation failed', [
                    'category_id' => $category->id,
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check your input and try again');
            }

            DB::beginTransaction();

            try {
                $category->update([
                    'name' => trim($request->name),
                    'slug' => Str::slug($request->name),
                    'description' => $request->description,
                    'icon' => $request->get('icon', $category->icon),
                    'color' => $request->get('color', $category->color),
                    'sort_order' => $request->get('sort_order', $category->sort_order),
                    'is_active' => $request->get('is_active', $category->is_active),
                ]);

                DB::commit();

                Log::info('✅ Resource category updated successfully', [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                ]);

                return $this->successResponse([
                    'category' => $category->fresh()
                ], 'Resource category updated successfully');

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('🚨 Resource category update failed', [
                'category_id' => $category->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'Resource category update');
        }
    }

    /**
     * Delete resource category - FIXED
     */
    public function destroyCategory(Request $request, ResourceCategory $category): JsonResponse
    {
        $this->logRequestDetails('Resource Category Deletion');

        try {
            Log::info('=== DELETING RESOURCE CATEGORY ===', [
                'category_id' => $category->id,
                'category_name' => $category->name,
            ]);

            // Check if category has resources
            if ($category->resources()->count() > 0) {
                Log::warning('❌ Cannot delete category with existing resources', [
                    'category_id' => $category->id,
                    'resource_count' => $category->resources()->count(),
                ]);
                return $this->errorResponse(
                    'Cannot delete category with existing resources. Please move or delete resources first.',
                    422
                );
            }

            $categoryName = $category->name;
            $categoryId = $category->id;

            DB::beginTransaction();

            try {
                $deleted = $category->delete();

                if (!$deleted) {
                    throw new Exception('Failed to delete category from database');
                }

                DB::commit();

                Log::info('✅ Resource category deleted successfully', [
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                ]);

                return $this->deleteSuccessResponse('Resource Category', $categoryName);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('🚨 Resource category deletion failed', [
                'category_id' => $category->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'Resource category deletion');
        }
    }

    /**
     * Get all resources (including unpublished) - FIXED
     */
    public function getResources(Request $request): JsonResponse
    {
        $this->logRequestDetails('Admin Resources Fetch');

        try {
            Log::info('=== FETCHING ADMIN RESOURCES ===', [
                'user_id' => $request->user()->id,
                'filters' => $request->only(['category_id', 'type', 'difficulty', 'status']),
            ]);

            $validator = Validator::make($request->all(), [
                'category_id' => 'sometimes|exists:resource_categories,id',
                'type' => 'sometimes|string|in:article,video,audio,exercise,tool,worksheet',
                'difficulty' => 'sometimes|string|in:beginner,intermediate,advanced',
                'status' => 'sometimes|in:all,published,unpublished',
                'search' => 'sometimes|string|max:255',
                'sort_by' => 'sometimes|in:newest,oldest,rating,views,downloads,category',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'category_id.exists' => 'Invalid category selected',
                'type.in' => 'Invalid resource type',
                'difficulty.in' => 'Invalid difficulty level',
                'status.in' => 'Invalid status filter',
                'search.max' => 'Search term cannot exceed 255 characters',
                'sort_by.in' => 'Invalid sort option',
                'per_page.min' => 'Items per page must be at least 1',
                'per_page.max' => 'Items per page cannot exceed 100',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Admin resources validation failed', [
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator);
            }

            // Build query
            $query = Resource::with(['category:id,name,slug,color', 'creator:id,name,email']);

            // Apply filters
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }

            if ($request->has('difficulty') && $request->difficulty !== 'all') {
                $query->where('difficulty', $request->difficulty);
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
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('tags', 'LIKE', "%{$searchTerm}%");
                });
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

            Log::info('✅ Admin resources fetched successfully', [
                'total' => $resources->total(),
                'per_page' => $perPage,
            ]);

            return $this->paginatedResponse($resources, 'Resources retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Admin resources fetch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'Admin resources fetch');
        }
    }

    /**
     * Create new resource - FIXED
     */
    public function storeResource(Request $request): JsonResponse
    {
        $this->logRequestDetails('Resource Creation');

        try {
            Log::info('=== CREATING RESOURCE ===', [
                'user_id' => $request->user()->id,
                'user_role' => $request->user()->role,
            ]);

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
            ], [
                'category_id.required' => 'Please select a category',
                'category_id.exists' => 'Invalid category selected',
                'title.required' => 'Title is required',
                'title.min' => 'Title must be at least 5 characters',
                'title.max' => 'Title cannot exceed 255 characters',
                'description.required' => 'Description is required',
                'description.min' => 'Description must be at least 20 characters',
                'description.max' => 'Description cannot exceed 2000 characters',
                'type.required' => 'Please select a resource type',
                'type.in' => 'Invalid resource type',
                'difficulty.required' => 'Please select a difficulty level',
                'difficulty.in' => 'Invalid difficulty level',
                'external_url.required' => 'External URL is required',
                'external_url.url' => 'Please provide a valid URL',
                'download_url.url' => 'Please provide a valid download URL',
                'thumbnail_url.url' => 'Please provide a valid thumbnail URL',
                'tags.max' => 'Maximum 10 tags allowed',
                'tags.*.max' => 'Each tag cannot exceed 50 characters',
                'sort_order.min' => 'Sort order must be a positive number',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Resource creation validation failed', [
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check your input and try again');
            }

            DB::beginTransaction();

            try {
                $resource = Resource::create([
                    'category_id' => $request->category_id,
                    'title' => trim($request->title),
                    'description' => trim($request->description),
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

                DB::commit();

                Log::info('✅ Resource created successfully', [
                    'resource_id' => $resource->id,
                    'title' => $resource->title,
                ]);

                return $this->successResponse([
                    'resource' => $resource
                ], 'Resource created successfully', 201);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('🚨 Resource creation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'Resource creation');
        }
    }

    /**
     * Update resource - FIXED
     */
    public function updateResource(Request $request, Resource $resource): JsonResponse
    {
        $this->logRequestDetails('Resource Update');

        try {
            Log::info('=== UPDATING RESOURCE ===', [
                'resource_id' => $resource->id,
                'title' => $resource->title,
            ]);

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
            ], [
                'category_id.required' => 'Please select a category',
                'category_id.exists' => 'Invalid category selected',
                'title.required' => 'Title is required',
                'title.min' => 'Title must be at least 5 characters',
                'title.max' => 'Title cannot exceed 255 characters',
                'description.required' => 'Description is required',
                'description.min' => 'Description must be at least 20 characters',
                'description.max' => 'Description cannot exceed 2000 characters',
                'type.required' => 'Please select a resource type',
                'type.in' => 'Invalid resource type',
                'difficulty.required' => 'Please select a difficulty level',
                'difficulty.in' => 'Invalid difficulty level',
                'external_url.required' => 'External URL is required',
                'external_url.url' => 'Please provide a valid URL',
                'download_url.url' => 'Please provide a valid download URL',
                'thumbnail_url.url' => 'Please provide a valid thumbnail URL',
                'tags.max' => 'Maximum 10 tags allowed',
                'tags.*.max' => 'Each tag cannot exceed 50 characters',
                'sort_order.min' => 'Sort order must be a positive number',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Resource update validation failed', [
                    'resource_id' => $resource->id,
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check your input and try again');
            }

            DB::beginTransaction();

            try {
                $wasPublished = $resource->is_published;
                $willBePublished = $request->get('is_published', $resource->is_published);

                $resource->update([
                    'category_id' => $request->category_id,
                    'title' => trim($request->title),
                    'description' => trim($request->description),
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

                DB::commit();

                Log::info('✅ Resource updated successfully', [
                    'resource_id' => $resource->id,
                    'title' => $resource->title,
                ]);

                return $this->successResponse([
                    'resource' => $resource->fresh(['category'])
                ], 'Resource updated successfully');

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('🚨 Resource update failed', [
                'resource_id' => $resource->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'Resource update');
        }
    }

    /**
     * Delete resource - FIXED
     */
    public function destroyResource(Request $request, Resource $resource): JsonResponse
    {
        $this->logRequestDetails('Resource Deletion');

        try {
            Log::info('=== DELETING RESOURCE ===', [
                'resource_id' => $resource->id,
                'title' => $resource->title,
            ]);

            $resourceTitle = $resource->title;
            $resourceId = $resource->id;

            DB::beginTransaction();

            try {
                $deleted = $resource->delete();

                if (!$deleted) {
                    throw new Exception('Failed to delete resource from database');
                }

                DB::commit();

                Log::info('✅ Resource deleted successfully', [
                    'resource_id' => $resourceId,
                    'title' => $resourceTitle,
                ]);

                return $this->deleteSuccessResponse('Resource', $resourceId);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('🚨 Resource deletion failed', [
                'resource_id' => $resource->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'Resource deletion');
        }
    }

    /**
     * Bulk actions on resources - FIXED
     */
    public function bulkActionResources(Request $request): JsonResponse
    {
        $this->logRequestDetails('Bulk Resource Action');

        try {
            Log::info('=== BULK RESOURCE ACTION ===', [
                'user_id' => $request->user()->id,
                'action' => $request->input('action'),
            ]);

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:publish,unpublish,feature,unfeature,delete,move_category',
                'resource_ids' => 'required|array|min:1',
                'resource_ids.*' => 'exists:resources,id',
                'category_id' => 'required_if:action,move_category|exists:resource_categories,id',
            ], [
                'action.required' => 'Please select an action',
                'action.in' => 'Invalid action selected',
                'resource_ids.required' => 'Please select at least one resource',
                'resource_ids.min' => 'Please select at least one resource',
                'resource_ids.*.exists' => 'One or more selected resources are invalid',
                'category_id.required_if' => 'Please select a category for moving resources',
                'category_id.exists' => 'Invalid category selected',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Bulk action validation failed', [
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check your selection and try again');
            }

            $resourceIds = $request->resource_ids;
            $action = $request->action;
            $affected = 0;

            DB::beginTransaction();

            try {
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

                Log::info("✅ Bulk action '{$action}' applied successfully", [
                    'action' => $action,
                    'affected_count' => $affected,
                ]);

                return $this->successResponse([
                    'affected_count' => $affected,
                    'action' => $action,
                ], "Successfully applied '{$action}' to {$affected} resources");

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('🚨 Bulk resource action failed', [
                'action' => $request->input('action'),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'Bulk resource action');
        }
    }

    /**
     * Get resource analytics - FIXED
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $this->logRequestDetails('Resource Analytics Fetch');

        try {
            Log::info('=== FETCHING RESOURCE ANALYTICS ===', [
                'user_id' => $request->user()->id,
            ]);

            $analytics = [
                'overview' => [
                    'total_resources' => Resource::count(),
                    'published_resources' => Resource::where('is_published', true)->count(),
                    'unpublished_resources' => Resource::where('is_published', false)->count(),
                    'featured_resources' => Resource::where('is_featured', true)->count(),
                    'total_categories' => ResourceCategory::count(),
                    'active_categories' => ResourceCategory::where('is_active', true)->count(),
                ],
                'engagement' => [
                    'total_views' => Resource::sum('view_count'),
                    'total_downloads' => Resource::sum('download_count'),
                    'average_rating' => Resource::where('rating', '>', 0)->avg('rating'),
                    'total_feedback' => DB::table('resource_feedback')->count(),
                ],
                'content_breakdown' => [
                    'by_type' => Resource::where('is_published', true)
                        ->selectRaw('type, count(*) as count')
                        ->groupBy('type')
                        ->pluck('count', 'type')
                        ->toArray(),
                    'by_difficulty' => Resource::where('is_published', true)
                        ->selectRaw('difficulty, count(*) as count')
                        ->groupBy('difficulty')
                        ->pluck('count', 'difficulty')
                        ->toArray(),
                ],
                'top_resources' => [
                    'most_viewed' => Resource::where('is_published', true)
                        ->orderBy('view_count', 'desc')
                        ->take(5)
                        ->get(['id', 'title', 'view_count', 'type']),
                    'most_downloaded' => Resource::where('is_published', true)
                        ->orderBy('download_count', 'desc')
                        ->take(5)
                        ->get(['id', 'title', 'download_count', 'type']),
                    'highest_rated' => Resource::where('is_published', true)
                        ->orderBy('rating', 'desc')
                        ->take(5)
                        ->get(['id', 'title', 'rating', 'type']),
                ],
                'categories_performance' => ResourceCategory::withCount(['resources'])
                    ->withSum('resources', 'view_count')
                    ->withSum('resources', 'download_count')
                    ->withAvg('resources', 'rating')
                    ->get(['id', 'name', 'slug']),
                'recent_activity' => [
                    'recent_resources' => Resource::orderBy('created_at', 'desc')
                        ->take(5)
                        ->get(['id', 'title', 'created_at', 'is_published', 'type']),
                    'recent_feedback' => DB::table('resource_feedback')
                        ->join('resources', 'resource_feedback.resource_id', '=', 'resources.id')
                        ->join('users', 'resource_feedback.user_id', '=', 'users.id')
                        ->orderBy('resource_feedback.created_at', 'desc')
                        ->take(5)
                        ->get(['resources.title', 'users.name', 'resource_feedback.rating', 'resource_feedback.created_at']),
                ]
            ];

            Log::info('✅ Resource analytics retrieved successfully');

            return $this->successResponse([
                'analytics' => $analytics
            ], 'Resource analytics retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Resource analytics fetch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'Resource analytics fetch');
        }
    }

    /**
     * Export resources data - FIXED
     */
    public function exportResources(Request $request): JsonResponse
    {
        $this->logRequestDetails('Resource Export');

        try {
            Log::info('=== EXPORTING RESOURCES ===', [
                'user_id' => $request->user()->id,
                'filters' => $request->input('filters', []),
            ]);

            $validator = Validator::make($request->all(), [
                'format' => 'sometimes|in:csv,json',
                'filters' => 'sometimes|array',
                'filters.category_id' => 'sometimes|exists:resource_categories,id',
                'filters.type' => 'sometimes|string',
                'filters.is_published' => 'sometimes|boolean',
            ], [
                'format.in' => 'Invalid export format. Use csv or json',
                'filters.category_id.exists' => 'Invalid category filter',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Resource export validation failed', [
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check your export parameters');
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

            return $this->successResponse([
                'resources' => $exportData,
                'filename' => $filename,
                'format' => $format,
                'count' => $resources->count(),
                'exported_at' => now()->toISOString(),
            ], "Successfully exported {$resources->count()} resources");

        } catch (Exception $e) {
            Log::error('🚨 Resource export failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'Resource export');
        }
    }
}