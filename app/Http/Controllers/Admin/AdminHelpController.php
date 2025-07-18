<?php
// app/Http/Controllers/Admin/AdminHelpController.php (FIXED - Complete rewrite with ApiResponseTrait)

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpCategory;
use App\Models\FAQ;
use App\Models\FAQFeedback;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Exception;

class AdminHelpController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all help categories (including inactive)
     */
    public function getCategories(Request $request): JsonResponse
    {
        $this->logRequestDetails('Admin Help Categories Fetch');

        try {
            Log::info('=== FETCHING ADMIN HELP CATEGORIES ===', [
                'user_id' => $request->user()->id,
                'user_role' => $request->user()->role,
            ]);

            $categories = HelpCategory::withCount(['faqs'])
                ->orderBy('sort_order')
                ->get();

            Log::info('✅ Admin help categories fetched successfully', [
                'count' => $categories->count()
            ]);

            return $this->successResponse([
                'categories' => $categories
            ], 'Help categories retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Admin help categories fetch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'Help categories fetch');
        }
    }

    /**
     * Create new help category
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $this->logRequestDetails('Help Category Creation');

        try {
            Log::info('=== CREATING HELP CATEGORY ===', [
                'user_id' => $request->user()->id,
                'user_role' => $request->user()->role,
            ]);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:help_categories,name',
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
                Log::warning('❌ Help category validation failed', [
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check your input and try again');
            }

            DB::beginTransaction();

            try {
                $category = HelpCategory::create([
                    'name' => trim($request->name),
                    'slug' => Str::slug($request->name),
                    'description' => $request->description,
                    'icon' => $request->get('icon', 'HelpCircle'),
                    'color' => $request->get('color', '#3B82F6'),
                    'sort_order' => $request->get('sort_order', 0),
                    'is_active' => $request->get('is_active', true),
                ]);

                if (!$category) {
                    throw new Exception('Failed to create help category');
                }

                DB::commit();

                Log::info('✅ Help category created successfully', [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                ]);

                return $this->successResponse([
                    'category' => $category
                ], 'Help category created successfully', 201);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Help category creation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'Help category creation');
        }
    }

    /**
     * Update help category
     */
    public function updateCategory(Request $request, HelpCategory $helpCategory): JsonResponse
    {
        $this->logRequestDetails('Help Category Update');

        try {
            Log::info('=== UPDATING HELP CATEGORY ===', [
                'category_id' => $helpCategory->id,
                'category_name' => $helpCategory->name,
            ]);

            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('help_categories')->ignore($helpCategory->id)
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
                Log::warning('❌ Help category update validation failed', [
                    'category_id' => $helpCategory->id,
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check your input and try again');
            }

            DB::beginTransaction();

            try {
                $helpCategory->update([
                    'name' => trim($request->name),
                    'slug' => Str::slug($request->name),
                    'description' => $request->description,
                    'icon' => $request->get('icon', $helpCategory->icon),
                    'color' => $request->get('color', $helpCategory->color),
                    'sort_order' => $request->get('sort_order', $helpCategory->sort_order),
                    'is_active' => $request->get('is_active', $helpCategory->is_active),
                ]);

                DB::commit();

                Log::info('✅ Help category updated successfully', [
                    'category_id' => $helpCategory->id,
                    'category_name' => $helpCategory->name,
                ]);

                return $this->successResponse([
                    'category' => $helpCategory->fresh()
                ], 'Help category updated successfully');

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Help category update failed', [
                'category_id' => $helpCategory->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'Help category update');
        }
    }

    /**
     * Delete help category
     */
    public function destroyCategory(Request $request, HelpCategory $helpCategory): JsonResponse
    {
        $this->logRequestDetails('Help Category Deletion');

        try {
            Log::info('=== DELETING HELP CATEGORY ===', [
                'category_id' => $helpCategory->id,
                'category_name' => $helpCategory->name,
            ]);

            // Check if category has FAQs
            $faqCount = $helpCategory->faqs()->count();
            if ($faqCount > 0) {
                Log::warning('❌ Cannot delete category with existing FAQs', [
                    'category_id' => $helpCategory->id,
                    'faq_count' => $faqCount,
                ]);
                return $this->errorResponse(
                    'Cannot delete category with existing FAQs. Please move or delete FAQs first.',
                    422
                );
            }

            $categoryName = $helpCategory->name;
            $categoryId = $helpCategory->id;

            DB::beginTransaction();

            try {
                $deleted = $helpCategory->delete();

                if (!$deleted) {
                    throw new Exception('Failed to delete category from database');
                }

                DB::commit();

                Log::info('✅ Help category deleted successfully', [
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                ]);

                return $this->deleteSuccessResponse('Help Category', $categoryName);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Help category deletion failed', [
                'category_id' => $helpCategory->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'Help category deletion');
        }
    }

    /**
     * Reorder categories
     */
    public function reorderCategories(Request $request): JsonResponse
    {
        $this->logRequestDetails('Category Reordering');

        try {
            Log::info('=== REORDERING CATEGORIES ===');

            $validator = Validator::make($request->all(), [
                'category_orders' => 'required|array',
                'category_orders.*.id' => 'required|exists:help_categories,id',
                'category_orders.*.sort_order' => 'required|integer|min:0',
            ], [
                'category_orders.required' => 'Category order data is required',
                'category_orders.array' => 'Invalid category order format',
                'category_orders.*.id.required' => 'Category ID is required',
                'category_orders.*.id.exists' => 'Invalid category ID',
                'category_orders.*.sort_order.required' => 'Sort order is required',
                'category_orders.*.sort_order.integer' => 'Sort order must be a number',
                'category_orders.*.sort_order.min' => 'Sort order must be positive',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator, 'Invalid reorder data');
            }

            DB::beginTransaction();

            try {
                foreach ($request->category_orders as $order) {
                    HelpCategory::where('id', $order['id'])
                        ->update(['sort_order' => $order['sort_order']]);
                }

                DB::commit();

                Log::info('✅ Categories reordered successfully', [
                    'updated_count' => count($request->category_orders)
                ]);

                return $this->successResponse([
                    'updated_count' => count($request->category_orders)
                ], 'Categories reordered successfully');

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Category reordering failed: ' . $e->getMessage());
            return $this->handleException($e, 'Category reordering');
        }
    }

    /**
     * Get all FAQs (including unpublished) - FIXED
     */
    public function getFAQs(Request $request): JsonResponse
    {
        $this->logRequestDetails('Admin FAQs Fetch');

        try {
            Log::info('=== FETCHING ADMIN FAQs ===', [
                'user_id' => $request->user()->id,
                'filters' => $request->only(['category_id', 'status', 'search', 'sort_by']),
            ]);

            $validator = Validator::make($request->all(), [
                'category_id' => 'sometimes|exists:help_categories,id',
                'status' => 'sometimes|in:all,published,unpublished,suggested',
                'search' => 'sometimes|string|max:255',
                'sort_by' => 'sometimes|in:newest,oldest,helpful,views,category',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'category_id.exists' => 'Invalid category selected',
                'status.in' => 'Invalid status filter',
                'search.max' => 'Search term cannot exceed 255 characters',
                'sort_by.in' => 'Invalid sort option',
                'per_page.min' => 'Items per page must be at least 1',
                'per_page.max' => 'Items per page cannot exceed 100',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Admin FAQs validation failed', [
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator);
            }

            // Build query
            $query = FAQ::with(['category:id,name,slug,color', 'creator:id,name,email']);

            // Apply filters
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('status') && $request->status !== 'all') {
                switch ($request->status) {
                    case 'published':
                        $query->where('is_published', true);
                        break;
                    case 'unpublished':
                        $query->where('is_published', false);
                        break;
                    case 'suggested':
                        $query->where('is_published', false)
                              ->whereNotNull('created_by');
                        break;
                }
            }

            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('question', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('answer', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('tags', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'newest');
            switch ($sortBy) {
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'helpful':
                    $query->orderBy('helpful_count', 'desc');
                    break;
                case 'views':
                    $query->orderBy('view_count', 'desc');
                    break;
                case 'category':
                    $query->join('help_categories', 'faqs.category_id', '=', 'help_categories.id')
                          ->orderBy('help_categories.name', 'asc')
                          ->select('faqs.*');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }

            $perPage = $request->get('per_page', 20);
            $faqs = $query->paginate($perPage);

            Log::info('✅ Admin FAQs fetched successfully', [
                'total' => $faqs->total(),
                'per_page' => $perPage,
            ]);

            return $this->paginatedResponse($faqs, 'FAQs retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Admin FAQs fetch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'Admin FAQs fetch');
        }
    }

    /**
     * Create new FAQ - FIXED
     */
    public function storeFAQ(Request $request): JsonResponse
    {
        $this->logRequestDetails('FAQ Creation');

        try {
            Log::info('=== CREATING FAQ ===', [
                'user_id' => $request->user()->id,
                'user_role' => $request->user()->role,
            ]);

            $validator = Validator::make($request->all(), [
                'category_id' => 'required|exists:help_categories,id',
                'question' => 'required|string|min:10|max:500',
                'answer' => 'required|string|min:20|max:5000',
                'tags' => 'sometimes|array|max:10',
                'tags.*' => 'string|max:50',
                'sort_order' => 'nullable|integer|min:0',
                'is_published' => 'boolean',
                'is_featured' => 'boolean',
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
                'tags.*.max' => 'Each tag cannot exceed 50 characters',
                'sort_order.min' => 'Sort order must be a positive number',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ FAQ creation validation failed', [
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check your input and try again');
            }

            DB::beginTransaction();

            try {
                $faq = FAQ::create([
                    'category_id' => $request->category_id,
                    'question' => trim($request->question),
                    'answer' => trim($request->answer),
                    'slug' => Str::slug($request->question) . '-' . time(),
                    'tags' => $request->get('tags', []),
                    'sort_order' => $request->get('sort_order', 0),
                    'is_published' => $request->get('is_published', false),
                    'is_featured' => $request->get('is_featured', false),
                    'created_by' => $request->user()->id,
                    'published_at' => $request->get('is_published') ? now() : null,
                ]);

                if (!$faq) {
                    throw new Exception('Failed to create FAQ record');
                }

                $faq->load(['category:id,name,slug,color']);

                DB::commit();

                Log::info('✅ FAQ created successfully', [
                    'faq_id' => $faq->id,
                    'question' => $faq->question,
                ]);

                return $this->successResponse([
                    'faq' => $faq
                ], 'FAQ created successfully', 201);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 FAQ creation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'FAQ creation');
        }
    }

    /**
     * Update FAQ - FIXED
     */
    public function updateFAQ(Request $request, FAQ $faq): JsonResponse
    {
        $this->logRequestDetails('FAQ Update');

        try {
            Log::info('=== UPDATING FAQ ===', [
                'faq_id' => $faq->id,
                'question' => $faq->question,
            ]);

            $validator = Validator::make($request->all(), [
                'category_id' => 'required|exists:help_categories,id',
                'question' => 'required|string|min:10|max:500',
                'answer' => 'required|string|min:20|max:5000',
                'tags' => 'sometimes|array|max:10',
                'tags.*' => 'string|max:50',
                'sort_order' => 'nullable|integer|min:0',
                'is_published' => 'boolean',
                'is_featured' => 'boolean',
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
                'tags.*.max' => 'Each tag cannot exceed 50 characters',
                'sort_order.min' => 'Sort order must be a positive number',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ FAQ update validation failed', [
                    'faq_id' => $faq->id,
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check your input and try again');
            }

            DB::beginTransaction();

            try {
                $wasPublished = $faq->is_published;
                $willBePublished = $request->get('is_published', $faq->is_published);

                $faq->update([
                    'category_id' => $request->category_id,
                    'question' => trim($request->question),
                    'answer' => trim($request->answer),
                    'slug' => Str::slug($request->question) . '-' . $faq->id,
                    'tags' => $request->get('tags', $faq->tags),
                    'sort_order' => $request->get('sort_order', $faq->sort_order),
                    'is_published' => $willBePublished,
                    'is_featured' => $request->get('is_featured', $faq->is_featured),
                    'updated_by' => $request->user()->id,
                    'published_at' => (!$wasPublished && $willBePublished) ? now() : $faq->published_at,
                ]);

                $faq->load(['category:id,name,slug,color']);

                DB::commit();

                Log::info('✅ FAQ updated successfully', [
                    'faq_id' => $faq->id,
                    'question' => $faq->question,
                ]);

                return $this->successResponse([
                    'faq' => $faq
                ], 'FAQ updated successfully');

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 FAQ update failed', [
                'faq_id' => $faq->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'FAQ update');
        }
    }

    /**
     * Delete FAQ - FIXED
     */
    public function destroyFAQ(Request $request, FAQ $faq): JsonResponse
    {
        $this->logRequestDetails('FAQ Deletion');

        try {
            Log::info('=== DELETING FAQ ===', [
                'faq_id' => $faq->id,
                'question' => $faq->question,
            ]);

            $faqQuestion = $faq->question;
            $faqId = $faq->id;

            DB::beginTransaction();

            try {
                // Delete related feedback first
                FAQFeedback::where('faq_id', $faq->id)->delete();

                $deleted = $faq->delete();

                if (!$deleted) {
                    throw new Exception('Failed to delete FAQ from database');
                }

                DB::commit();

                Log::info('✅ FAQ deleted successfully', [
                    'faq_id' => $faqId,
                    'question' => $faqQuestion,
                ]);

                return $this->deleteSuccessResponse('FAQ', $faqId);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 FAQ deletion failed', [
                'faq_id' => $faq->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'FAQ deletion');
        }
    }

    /**
     * Bulk actions on FAQs
     */
    public function bulkActionFAQs(Request $request): JsonResponse
    {
        $this->logRequestDetails('Bulk FAQ Action');

        try {
            Log::info('=== BULK FAQ ACTION ===');

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:publish,unpublish,feature,unfeature,delete,move_category',
                'faq_ids' => 'required|array|min:1',
                'faq_ids.*' => 'exists:faqs,id',
                'category_id' => 'required_if:action,move_category|exists:help_categories,id',
            ], [
                'action.required' => 'Action is required',
                'action.in' => 'Invalid action selected',
                'faq_ids.required' => 'Please select at least one FAQ',
                'faq_ids.min' => 'Please select at least one FAQ',
                'faq_ids.*.exists' => 'One or more selected FAQs are invalid',
                'category_id.required_if' => 'Category is required when moving FAQs',
                'category_id.exists' => 'Invalid category selected',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator, 'Please check your selection and try again');
            }

            $faqIds = $request->faq_ids;
            $action = $request->action;
            $affected = 0;

            DB::beginTransaction();

            try {
                switch ($action) {
                    case 'publish':
                        $affected = FAQ::whereIn('id', $faqIds)->update([
                            'is_published' => true,
                            'published_at' => now(),
                            'updated_by' => $request->user()->id,
                        ]);
                        break;

                    case 'unpublish':
                        $affected = FAQ::whereIn('id', $faqIds)->update([
                            'is_published' => false,
                            'updated_by' => $request->user()->id,
                        ]);
                        break;

                    case 'feature':
                        $affected = FAQ::whereIn('id', $faqIds)->update([
                            'is_featured' => true,
                            'updated_by' => $request->user()->id,
                        ]);
                        break;

                    case 'unfeature':
                        $affected = FAQ::whereIn('id', $faqIds)->update([
                            'is_featured' => false,
                            'updated_by' => $request->user()->id,
                        ]);
                        break;

                    case 'move_category':
                        $affected = FAQ::whereIn('id', $faqIds)->update([
                            'category_id' => $request->category_id,
                            'updated_by' => $request->user()->id,
                        ]);
                        break;

                    case 'delete':
                        $affected = FAQ::whereIn('id', $faqIds)->count();
                        
                        // Delete related feedback first
                        FAQFeedback::whereIn('faq_id', $faqIds)->delete();
                        
                        // Delete FAQs
                        FAQ::whereIn('id', $faqIds)->delete();
                        break;
                }

                DB::commit();

                Log::info("✅ Bulk action '{$action}' applied to {$affected} FAQs");

                return $this->successResponse([
                    'affected_count' => $affected,
                    'action' => $action
                ], "Successfully applied '{$action}' to {$affected} FAQs");

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Bulk FAQ action failed: ' . $e->getMessage());
            return $this->handleException($e, 'Bulk FAQ action');
        }
    }

    /**
     * FIXED: Get content suggestions - Properly filter and display suggested FAQs
     */
    public function getContentSuggestions(Request $request): JsonResponse
    {
        $this->logRequestDetails('Content Suggestions Fetch');

        try {
            Log::info('=== FETCHING CONTENT SUGGESTIONS ===', [
                'user_id' => $request->user()->id,
                'user_role' => $request->user()->role,
            ]);

            // Validation for filters
            $validator = Validator::make($request->all(), [
                'status' => 'sometimes|in:all,pending,approved,rejected',
                'category_id' => 'sometimes|exists:help_categories,id',
                'search' => 'sometimes|string|max:255',
                'sort_by' => 'sometimes|in:newest,oldest,category',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            // Build query for content suggestions
            $query = FAQ::where('is_published', false)
                ->whereNotNull('created_by')
                ->where('created_by', '!=', $request->user()->id) // Exclude self-created
                ->with([
                    'category:id,name,slug,color', 
                    'creator:id,name,email,role'
                ]);

            // Apply filters
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('question', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('answer', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('tags', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'newest');
            switch ($sortBy) {
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'category':
                    $query->join('help_categories', 'faqs.category_id', '=', 'help_categories.id')
                          ->orderBy('help_categories.name', 'asc')
                          ->select('faqs.*');
                    break;
                default: // newest
                    $query->orderBy('created_at', 'desc');
            }

            // Get paginated results
            $perPage = $request->get('per_page', 20);
            $suggestions = $query->paginate($perPage);

            // Add additional metadata to each suggestion
            $suggestions->getCollection()->transform(function ($suggestion) {
                $suggestion->time_ago = $this->getTimeAgo($suggestion->created_at);
                $suggestion->suggestion_type = 'content_suggestion';
                
                // Add helpfulness rate
                $totalVotes = $suggestion->helpful_count + $suggestion->not_helpful_count;
                $suggestion->helpfulness_rate = $totalVotes > 0 
                    ? round(($suggestion->helpful_count / $totalVotes) * 100, 1) 
                    : 0;

                return $suggestion;
            });

            Log::info('✅ Content suggestions fetched successfully', [
                'total_suggestions' => $suggestions->total(),
                'current_page' => $suggestions->currentPage(),
                'per_page' => $suggestions->perPage(),
            ]);

            return $this->paginatedResponse($suggestions, 'Content suggestions retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Content suggestions fetch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'Content suggestions fetch');
        }
    }

    /**
     * FIXED: Approve content suggestion with proper notification
     */
    public function approveSuggestion(Request $request, int $suggestionId): JsonResponse
    {
        $this->logRequestDetails('Content Suggestion Approval');

        try {
            Log::info('=== APPROVING CONTENT SUGGESTION ===', [
                'suggestion_id' => $suggestionId,
                'user_id' => $request->user()->id,
                'user_role' => $request->user()->role,
            ]);

            $faq = FAQ::findOrFail($suggestionId);

            // Validate that this is actually a suggestion
            if ($faq->is_published) {
                Log::warning('❌ Attempt to approve already published FAQ', [
                    'faq_id' => $faq->id,
                    'is_published' => $faq->is_published,
                ]);
                return $this->errorResponse('This FAQ is already published', 422);
            }

            if (!$faq->created_by) {
                Log::warning('❌ Attempt to approve FAQ without creator', [
                    'faq_id' => $faq->id,
                    'created_by' => $faq->created_by,
                ]);
                return $this->errorResponse('This FAQ is not a content suggestion', 422);
            }

            DB::beginTransaction();

            try {
                // Update FAQ to published status
                $faq->update([
                    'is_published' => true,
                    'published_at' => now(),
                    'updated_by' => $request->user()->id,
                ]);

                // Create notification for the original creator
                if ($faq->creator) {
                    \App\Models\Notification::create([
                        'user_id' => $faq->creator->id,
                        'type' => 'system',
                        'title' => 'FAQ Suggestion Approved! 🎉',
                        'message' => "Great news! Your FAQ suggestion '{$faq->question}' has been approved and published. Thank you for contributing to our help center!",
                        'priority' => 'medium',
                        'data' => json_encode([
                            'faq_id' => $faq->id,
                            'faq_question' => $faq->question,
                            'approved_by' => $request->user()->name,
                            'approved_at' => now()->toISOString(),
                            'action_type' => 'faq_suggestion_approved'
                        ]),
                    ]);
                    Log::info('✅ Creator notification sent for approved suggestion');
                }

                Log::info('📝 FAQ suggestion approved', [
                    'faq_id' => $faq->id,
                    'question' => $faq->question,
                    'creator_id' => $faq->created_by,
                    'creator_name' => $faq->creator->name ?? 'Unknown',
                    'approved_by' => $request->user()->name,
                    'category' => $faq->category->name ?? 'Unknown',
                ]);

                DB::commit();

                // Load fresh data with relationships
                $faq->load(['category:id,name,slug,color', 'creator:id,name,email']);

                Log::info('✅ FAQ suggestion approved successfully', [
                    'faq_id' => $faq->id,
                    'question' => $faq->question,
                    'now_published' => $faq->is_published,
                ]);

                return $this->successResponse([
                    'faq' => $faq,
                    'approved_by' => $request->user()->name,
                    'approved_at' => now()->toISOString(),
                ], 'FAQ suggestion approved and published successfully');

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('❌ FAQ suggestion not found', [
                'suggestion_id' => $suggestionId,
            ]);
            return $this->notFoundResponse('FAQ suggestion not found');

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 FAQ suggestion approval failed', [
                'suggestion_id' => $suggestionId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'FAQ suggestion approval');
        }
    }

    /**
     * FIXED: Reject content suggestion with optional feedback
     */
    public function rejectSuggestion(Request $request, int $suggestionId): JsonResponse
    {
        $this->logRequestDetails('Content Suggestion Rejection');

        try {
            Log::info('=== REJECTING CONTENT SUGGESTION ===', [
                'suggestion_id' => $suggestionId,
                'user_id' => $request->user()->id,
            ]);

            $validator = Validator::make($request->all(), [
                'feedback' => 'nullable|string|max:1000',
                'reason' => 'nullable|string|max:500',
            ], [
                'feedback.max' => 'Feedback cannot exceed 1000 characters',
                'reason.max' => 'Reason cannot exceed 500 characters',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $faq = FAQ::findOrFail($suggestionId);

            // Validate that this is actually a suggestion
            if ($faq->is_published) {
                return $this->errorResponse('Cannot reject a published FAQ', 422);
            }

            if (!$faq->created_by) {
                return $this->errorResponse('This FAQ is not a content suggestion', 422);
            }

            DB::beginTransaction();

            try {
                $feedback = $request->get('feedback', '');
                $reason = $request->get('reason', 'Content did not meet publication standards');
                $faqQuestion = $faq->question;
                $creatorId = $faq->created_by;
                $creatorName = $faq->creator->name ?? 'Unknown';

                // Create notification for the original creator
                if ($faq->creator) {
                    $notificationMessage = "Your FAQ suggestion '{$faqQuestion}' has been reviewed and not approved for publication.";
                    
                    if ($feedback) {
                        $notificationMessage .= " Feedback: " . $feedback;
                    }
                    
                    if ($reason) {
                        $notificationMessage .= " Reason: " . $reason;
                    }

                    \App\Models\Notification::create([
                        'user_id' => $faq->creator->id,
                        'type' => 'system',
                        'title' => 'FAQ Suggestion Update',
                        'message' => $notificationMessage,
                        'priority' => 'medium',
                        'data' => json_encode([
                            'faq_id' => $faq->id,
                            'faq_question' => $faqQuestion,
                            'rejected_by' => $request->user()->name,
                            'rejected_at' => now()->toISOString(),
                            'feedback' => $feedback,
                            'reason' => $reason,
                            'action_type' => 'faq_suggestion_rejected'
                        ]),
                    ]);
                    Log::info('✅ Creator notification sent for rejected suggestion');
                }

                // Log the rejection
                Log::info('📝 FAQ suggestion rejected', [
                    'faq_id' => $faq->id,
                    'question' => $faqQuestion,
                    'creator_id' => $creatorId,
                    'creator_name' => $creatorName,
                    'rejected_by' => $request->user()->name,
                    'reason' => $reason,
                    'feedback' => $feedback,
                ]);

                // Delete the FAQ suggestion
                $deleted = $faq->delete();

                if (!$deleted) {
                    throw new Exception('Failed to delete FAQ suggestion from database');
                }

                DB::commit();

                Log::info('✅ FAQ suggestion rejected and deleted successfully', [
                    'suggestion_id' => $suggestionId,
                    'question' => $faqQuestion,
                    'rejected_by' => $request->user()->name,
                ]);

                return $this->successResponse([
                    'rejected_faq' => [
                        'id' => $suggestionId,
                        'question' => $faqQuestion,
                        'creator_name' => $creatorName,
                    ],
                    'rejected_by' => $request->user()->name,
                    'rejected_at' => now()->toISOString(),
                    'feedback_provided' => !empty($feedback),
                ], 'FAQ suggestion rejected successfully');

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('❌ FAQ suggestion not found for rejection', [
                'suggestion_id' => $suggestionId,
            ]);
            return $this->notFoundResponse('FAQ suggestion not found');

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 FAQ suggestion rejection failed', [
                'suggestion_id' => $suggestionId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'FAQ suggestion rejection');
        }
    }

    /**
     * FIXED: Request revision for content suggestion with detailed feedback
     */
    public function requestSuggestionRevision(Request $request, int $suggestionId): JsonResponse
    {
        $this->logRequestDetails('Content Suggestion Revision Request');

        try {
            Log::info('=== REQUESTING SUGGESTION REVISION ===', [
                'suggestion_id' => $suggestionId,
                'user_id' => $request->user()->id,
            ]);

            $validator = Validator::make($request->all(), [
                'feedback' => 'required|string|min:10|max:1000',
                'revision_notes' => 'nullable|string|max:500',
            ], [
                'feedback.required' => 'Feedback is required for revision requests',
                'feedback.min' => 'Feedback must be at least 10 characters',
                'feedback.max' => 'Feedback cannot exceed 1000 characters',
                'revision_notes.max' => 'Revision notes cannot exceed 500 characters',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $faq = FAQ::findOrFail($suggestionId);

            // Validate that this is actually a suggestion
            if ($faq->is_published) {
                return $this->errorResponse('Cannot request revision for a published FAQ', 422);
            }

            if (!$faq->created_by) {
                return $this->errorResponse('This FAQ is not a content suggestion', 422);
            }

            DB::beginTransaction();

            try {
                $feedback = $request->feedback;
                $revisionNotes = $request->get('revision_notes', '');

                // Add revision metadata to FAQ
                $faq->update([
                    'updated_by' => $request->user()->id,
                    'tags' => array_merge($faq->tags ?? [], ['revision-requested']),
                ]);

                // Create detailed notification for the original creator
                $notificationMessage = "Your FAQ suggestion '{$faq->question}' needs revision before it can be published. ";
                $notificationMessage .= "Please review the feedback and update your suggestion accordingly. ";
                $notificationMessage .= "Feedback: " . $feedback;
                
                if ($revisionNotes) {
                    $notificationMessage .= " Additional notes: " . $revisionNotes;
                }

                if ($faq->creator) {
                    \App\Models\Notification::create([
                        'user_id' => $faq->creator->id,
                        'type' => 'system',
                        'title' => 'FAQ Suggestion Needs Revision',
                        'message' => $notificationMessage,
                        'priority' => 'medium',
                        'data' => json_encode([
                            'faq_id' => $faq->id,
                            'faq_question' => $faq->question,
                            'revision_requested_by' => $request->user()->name,
                            'revision_requested_at' => now()->toISOString(),
                            'feedback' => $feedback,
                            'revision_notes' => $revisionNotes,
                            'action_required' => 'revise_faq_suggestion',
                            'action_type' => 'faq_revision_requested'
                        ]),
                    ]);
                    Log::info('✅ Revision request notification sent to creator');
                }

                // Log the revision request
                Log::info('📝 FAQ revision requested', [
                    'faq_id' => $faq->id,
                    'question' => $faq->question,
                    'creator_id' => $faq->created_by,
                    'creator_name' => $faq->creator->name ?? 'Unknown',
                    'requested_by' => $request->user()->name,
                    'feedback' => $feedback,
                    'revision_notes' => $revisionNotes,
                ]);

                DB::commit();

                Log::info('✅ FAQ revision requested successfully', [
                    'suggestion_id' => $suggestionId,
                    'question' => $faq->question,
                ]);

                return $this->successResponse([
                    'faq' => $faq->fresh(['category', 'creator']),
                    'revision_details' => [
                        'requested_by' => $request->user()->name,
                        'requested_at' => now()->toISOString(),
                        'feedback' => $feedback,
                        'revision_notes' => $revisionNotes,
                    ]
                ], 'Revision request sent successfully');

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('❌ FAQ suggestion not found for revision request', [
                'suggestion_id' => $suggestionId,
            ]);
            return $this->notFoundResponse('FAQ suggestion not found');

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 FAQ revision request failed', [
                'suggestion_id' => $suggestionId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'FAQ revision request');
        }
    }

    /**
     * FIXED: Get content suggestions statistics
     */
    public function getContentSuggestionsStats(Request $request): JsonResponse
    {
        $this->logRequestDetails('Content Suggestions Stats');

        try {
            Log::info('=== FETCHING CONTENT SUGGESTIONS STATS ===');

            $stats = [
                'total_suggestions' => FAQ::where('is_published', false)
                    ->whereNotNull('created_by')
                    ->count(),
                
                'pending_suggestions' => FAQ::where('is_published', false)
                    ->whereNotNull('created_by')
                    ->where('created_by', '!=', $request->user()->id)
                    ->count(),
                
                'suggestions_this_month' => FAQ::where('is_published', false)
                    ->whereNotNull('created_by')
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count(),
                
                'suggestions_this_week' => FAQ::where('is_published', false)
                    ->whereNotNull('created_by')
                    ->where('created_at', '>=', now()->startOfWeek())
                    ->count(),
                
                'suggestions_by_category' => FAQ::where('is_published', false)
                    ->whereNotNull('created_by')
                    ->join('help_categories', 'faqs.category_id', '=', 'help_categories.id')
                    ->selectRaw('help_categories.name as category_name, COUNT(*) as count')
                    ->groupBy('help_categories.name')
                    ->pluck('count', 'category_name')
                    ->toArray(),
                
                'top_contributors' => FAQ::where('is_published', false)
                    ->whereNotNull('created_by')
                    ->join('users', 'faqs.created_by', '=', 'users.id')
                    ->selectRaw('users.name, users.email, COUNT(*) as suggestion_count')
                    ->groupBy('users.id', 'users.name', 'users.email')
                    ->orderBy('suggestion_count', 'desc')
                    ->take(5)
                    ->get()
                    ->toArray(),
                
                'recent_suggestions' => FAQ::where('is_published', false)
                    ->whereNotNull('created_by')
                    ->with(['creator:id,name,email', 'category:id,name,color'])
                    ->orderBy('created_at', 'desc')
                    ->take(5)
                    ->get(['id', 'question', 'created_by', 'category_id', 'created_at'])
                    ->toArray(),
            ];

            Log::info('✅ Content suggestions stats retrieved', [
                'total_suggestions' => $stats['total_suggestions'],
                'pending_suggestions' => $stats['pending_suggestions'],
            ]);

            return $this->successResponse([
                'stats' => $stats,
                'generated_at' => now()->toISOString(),
            ], 'Content suggestions statistics retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Content suggestions stats failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'Content suggestions statistics');
        }
    }

    /**
     * Get help analytics - FIXED
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $this->logRequestDetails('Help Analytics Fetch');

        try {
            Log::info('=== FETCHING HELP ANALYTICS ===', [
                'user_id' => $request->user()->id,
            ]);

            $timeRange = $request->get('time_range', '30'); // days
            $startDate = now()->subDays($timeRange);

            $analytics = [
                'overview' => [
                    'total_faqs' => FAQ::count(),
                    'published_faqs' => FAQ::where('is_published', true)->count(),
                    'unpublished_faqs' => FAQ::where('is_published', false)->count(),
                    'featured_faqs' => FAQ::where('is_featured', true)->count(),
                    'total_categories' => HelpCategory::count(),
                    'active_categories' => HelpCategory::where('is_active', true)->count(),
                    'total_views' => FAQ::sum('view_count'),
                    'avg_helpfulness' => FAQ::whereNotNull('helpful_count')
                        ->where('helpful_count', '>', 0)
                        ->selectRaw('AVG((helpful_count / GREATEST(helpful_count + not_helpful_count, 1)) * 100) as avg_helpfulness')
                        ->first()
                        ->avg_helpfulness ?? 0,
                ],
                'engagement' => [
                    'total_helpful_votes' => FAQ::sum('helpful_count'),
                    'total_unhelpful_votes' => FAQ::sum('not_helpful_count'),
                    'recent_feedback' => FAQFeedback::where('created_at', '>=', $startDate)->count(),
                ],
                'top_faqs' => [
                    'most_viewed' => FAQ::where('is_published', true)
                        ->orderBy('view_count', 'desc')
                        ->take(10)
                        ->get(['id', 'question', 'view_count', 'helpful_count', 'category_id']),
                    'most_helpful' => FAQ::where('is_published', true)
                        ->orderBy('helpful_count', 'desc')
                        ->take(10)
                        ->get(['id', 'question', 'helpful_count', 'view_count', 'category_id']),
                ],
                'categories_performance' => HelpCategory::withCount(['faqs'])
                    ->withSum('faqs', 'view_count')
                    ->withSum('faqs', 'helpful_count')
                    ->get(['id', 'name', 'slug']),
                'recent_activity' => [
                    'recent_faqs' => FAQ::orderBy('created_at', 'desc')
                        ->take(5)
                        ->get(['id', 'question', 'created_at', 'is_published']),
                    'pending_suggestions' => FAQ::where('is_published', false)
                        ->whereNotNull('created_by')
                        ->count(),
                ]
            ];

            Log::info('✅ Help analytics retrieved successfully');

            return $this->successResponse($analytics, 'Help analytics retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Help analytics fetch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'Help analytics fetch');
        }
    }

    /**
     * UTILITY: Get time ago string for suggestions
     */
    private function getTimeAgo(string $dateString): string
    {
        try {
            $date = new \DateTime($dateString);
            $now = new \DateTime();
            $diff = $now->diff($date);
            
            if ($diff->days > 30) {
                return $date->format('M j, Y');
            } elseif ($diff->days > 0) {
                return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
            } elseif ($diff->h > 0) {
                return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
            } elseif ($diff->i > 0) {
                return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
            } else {
                return 'Just now';
            }
        } catch (Exception $e) {
            Log::warning('Failed to calculate time ago', ['date' => $dateString, 'error' => $e->getMessage()]);
            return 'Unknown time';
        }
    }

    /**
     * Additional utility methods for FAQ management
     */
    public function duplicateFAQ(Request $request, FAQ $faq): JsonResponse
    {
        $this->logRequestDetails('FAQ Duplication');

        try {
            $validator = Validator::make($request->all(), [
                'new_title' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $newTitle = $request->get('new_title', $faq->question . ' (Copy)');

            DB::beginTransaction();

            try {
                $duplicatedFAQ = FAQ::create([
                    'category_id' => $faq->category_id,
                    'question' => $newTitle,
                    'answer' => $faq->answer,
                    'slug' => Str::slug($newTitle) . '-' . time(),
                    'tags' => $faq->tags,
                    'sort_order' => $faq->sort_order,
                    'is_published' => false, // Duplicates start as unpublished
                    'is_featured' => false,
                    'created_by' => $request->user()->id,
                ]);

                $duplicatedFAQ->load(['category:id,name,slug,color']);

                DB::commit();

                Log::info('✅ FAQ duplicated: ' . $duplicatedFAQ->question);

                return $this->successResponse([
                    'faq' => $duplicatedFAQ
                ], 'FAQ duplicated successfully', 201);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('FAQ duplication failed: ' . $e->getMessage());
            return $this->handleException($e, 'FAQ duplication');
        }
    }

    /**
     * Move FAQ to category
     */
    public function moveFAQToCategory(Request $request, FAQ $faq): JsonResponse
    {
        $this->logRequestDetails('FAQ Category Move');

        try {
            $validator = Validator::make($request->all(), [
                'category_id' => 'required|exists:help_categories,id',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            DB::beginTransaction();

            try {
                $oldCategory = $faq->category->name ?? 'Unknown';
                $newCategory = HelpCategory::find($request->category_id);

                $faq->update([
                    'category_id' => $request->category_id,
                    'updated_by' => $request->user()->id,
                ]);

                $faq->load(['category:id,name,slug,color']);

                DB::commit();

                Log::info("✅ FAQ moved from '{$oldCategory}' to '{$newCategory->name}': " . $faq->question);

                return $this->successResponse([
                    'faq' => $faq
                ], "FAQ moved to {$newCategory->name} successfully");

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('FAQ category move failed: ' . $e->getMessage());
            return $this->handleException($e, 'FAQ category move');
        }
    }

    /**
     * Clear help cache
     */
    public function clearHelpCache(Request $request): JsonResponse
    {
        $this->logRequestDetails('Help Cache Clear');

        try {
            Cache::tags(['help', 'faqs', 'categories'])->flush();
            
            Log::info('✅ Help cache cleared by admin: ' . $request->user()->name);

            return $this->successResponse([
                'cleared_at' => now()->toISOString(),
                'cleared_by' => $request->user()->name
            ], 'Help cache cleared successfully');

        } catch (Exception $e) {
            Log::error('Help cache clear failed: ' . $e->getMessage());
            return $this->handleException($e, 'Help cache clear');
        }
    }

    /**
     * Warm cache
     */
    public function warmCache(Request $request): JsonResponse
    {
        $this->logRequestDetails('Cache Warming');

        try {
            // Warm popular caches
            Cache::remember('popular_faqs', 3600, function () {
                return FAQ::where('is_published', true)->orderBy('view_count', 'desc')->take(10)->get();
            });

            Cache::remember('featured_faqs', 3600, function () {
                return FAQ::where('is_published', true)->where('is_featured', true)->take(3)->get();
            });

            Cache::remember('active_categories', 3600, function () {
                return HelpCategory::where('is_active', true)->orderBy('sort_order')->get();
            });

            Log::info('✅ Help cache warmed by admin: ' . $request->user()->name);

            return $this->successResponse([
                'warmed_at' => now()->toISOString(),
                'warmed_by' => $request->user()->name
            ], 'Help cache warmed successfully');

        } catch (Exception $e) {
            Log::error('Help cache warming failed: ' . $e->getMessage());
            return $this->handleException($e, 'Help cache warming');
        }
    }

    /**
     * Export help data
     */
    public function exportHelpData(Request $request): JsonResponse
    {
        $this->logRequestDetails('Help Data Export');

        try {
            $format = $request->get('format', 'csv');
            
            $data = [
                'categories' => HelpCategory::all(),
                'faqs' => FAQ::with(['category:id,name'])->get(),
                'feedback' => FAQFeedback::with(['faq:id,question', 'user:id,name'])->get(),
            ];

            return $this->successResponse([
                'export_data' => $data,
                'format' => $format,
                'generated_at' => now()->toISOString(),
                'record_counts' => [
                    'categories' => $data['categories']->count(),
                    'faqs' => $data['faqs']->count(),
                    'feedback' => $data['feedback']->count(),
                ]
            ], 'Help data export prepared successfully');

        } catch (Exception $e) {
            Log::error('Help data export failed: ' . $e->getMessage());
            return $this->handleException($e, 'Help data export');
        }
    }
}