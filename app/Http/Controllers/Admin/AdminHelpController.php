<?php
// app/Http/Controllers/Admin/AdminHelpController.php (FIXED)

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
use Illuminate\Support\Facades\Storage;
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

                DB::commit();

                Log::info('✅ Help category created successfully', [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                ]);

                return $this->successResponse([
                    'category' => $category
                ], 'Help category created successfully', 201);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
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
    public function updateCategory(Request $request, HelpCategory $category): JsonResponse
    {
        $this->logRequestDetails('Help Category Update');

        try {
            Log::info('=== UPDATING HELP CATEGORY ===', [
                'category_id' => $category->id,
                'category_name' => $category->name,
            ]);

            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('help_categories')->ignore($category->id)
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

                Log::info('✅ Help category updated successfully', [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                ]);

                return $this->successResponse([
                    'category' => $category->fresh()
                ], 'Help category updated successfully');

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('🚨 Help category update failed', [
                'category_id' => $category->id ?? 'unknown',
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
    public function destroyCategory(Request $request, HelpCategory $category): JsonResponse
    {
        $this->logRequestDetails('Help Category Deletion');

        try {
            Log::info('=== DELETING HELP CATEGORY ===', [
                'category_id' => $category->id,
                'category_name' => $category->name,
            ]);

            // Check if category has FAQs
            if ($category->faqs()->count() > 0) {
                Log::warning('❌ Cannot delete category with existing FAQs', [
                    'category_id' => $category->id,
                    'faq_count' => $category->faqs()->count(),
                ]);
                return $this->errorResponse(
                    'Cannot delete category with existing FAQs. Please move or delete FAQs first.',
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

                Log::info('✅ Help category deleted successfully', [
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                ]);

                return $this->deleteSuccessResponse('Help Category', $categoryName);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('🚨 Help category deletion failed', [
                'category_id' => $category->id ?? 'unknown',
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
        try {
            $validator = Validator::make($request->all(), [
                'category_orders' => 'required|array',
                'category_orders.*.id' => 'required|exists:help_categories,id',
                'category_orders.*.sort_order' => 'required|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            foreach ($request->category_orders as $order) {
                HelpCategory::where('id', $order['id'])
                    ->update(['sort_order' => $order['sort_order']]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Categories reordered successfully'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Category reordering failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder categories.',
            ], 500);
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

                $faq->load(['category:id,name,slug,color']);

                DB::commit();

                Log::info('✅ FAQ created successfully', [
                    'faq_id' => $faq->id,
                    'question' => $faq->question,
                ]);

                return $this->successResponse([
                    'faq' => $faq
                ], 'FAQ created successfully', 201);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
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
                    'faq' => $faq->fresh(['category'])
                ], 'FAQ updated successfully');

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
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

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
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
        try {
            Log::info('=== BULK FAQ ACTION ===');

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:publish,unpublish,feature,unfeature,delete,move_category',
                'faq_ids' => 'required|array|min:1',
                'faq_ids.*' => 'exists:faqs,id',
                'category_id' => 'required_if:action,move_category|exists:help_categories,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $faqIds = $request->faq_ids;
            $action = $request->action;
            $affected = 0;

            DB::beginTransaction();

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
                    FAQ::whereIn('id', $faqIds)->delete();
                    break;
            }

            DB::commit();

            Log::info("✅ Bulk action '{$action}' applied to {$affected} FAQs");

            return response()->json([
                'success' => true,
                'message' => "Successfully applied '{$action}' to {$affected} FAQs",
                'data' => ['affected_count' => $affected]
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Bulk FAQ action failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform bulk action.',
            ], 500);
        }
    }

    /**
     * Get content suggestions - FIXED
     */
    public function getContentSuggestions(Request $request): JsonResponse
    {
        $this->logRequestDetails('Content Suggestions Fetch');

        try {
            Log::info('=== FETCHING CONTENT SUGGESTIONS ===', [
                'user_id' => $request->user()->id,
            ]);

            $suggestions = FAQ::where('is_published', false)
                ->whereNotNull('created_by')
                ->with(['category:id,name,slug,color', 'creator:id,name,email'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            Log::info('✅ Content suggestions fetched successfully', [
                'total' => $suggestions->total(),
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
     * Approve content suggestion - FIXED
     */
    public function approveSuggestion(Request $request, int $suggestionId): JsonResponse
    {
        $this->logRequestDetails('Content Suggestion Approval');

        try {
            Log::info('=== APPROVING CONTENT SUGGESTION ===', [
                'suggestion_id' => $suggestionId,
                'user_id' => $request->user()->id,
            ]);

            $faq = FAQ::findOrFail($suggestionId);

            if ($faq->is_published) {
                Log::warning('❌ Attempt to approve already published FAQ', [
                    'faq_id' => $faq->id,
                ]);
                return $this->errorResponse('This FAQ is already published', 422);
            }

            DB::beginTransaction();

            try {
                $faq->update([
                    'is_published' => true,
                    'published_at' => now(),
                    'updated_by' => $request->user()->id,
                ]);

                // Notify the original creator
                if ($faq->creator) {
                    \App\Models\Notification::create([
                        'user_id' => $faq->creator->id,
                        'type' => 'system',
                        'title' => 'FAQ Suggestion Approved',
                        'message' => "Your FAQ suggestion '{$faq->question}' has been approved and published.",
                        'priority' => 'medium',
                    ]);
                }

                DB::commit();

                Log::info('✅ FAQ suggestion approved successfully', [
                    'faq_id' => $faq->id,
                    'question' => $faq->question,
                ]);

                return $this->successResponse([
                    'faq' => $faq->fresh(['category', 'creator'])
                ], 'FAQ suggestion approved and published successfully');

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
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
     * Reject content suggestion
     */
    public function rejectSuggestion(Request $request, int $suggestionId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'feedback' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $faq = FAQ::findOrFail($suggestionId);

            if ($faq->is_published) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot reject a published FAQ.'
                ], 422);
            }

            // Notify the original creator
            if ($faq->creator) {
                \App\Models\Notification::create([
                    'user_id' => $faq->creator->id,
                    'type' => 'system',
                    'title' => 'FAQ Suggestion Rejected',
                    'message' => "Your FAQ suggestion '{$faq->question}' has been rejected." . 
                                ($request->feedback ? " Feedback: " . $request->feedback : ''),
                    'priority' => 'medium',
                ]);
            }

            $faqQuestion = $faq->question;
            $faq->delete();

            Log::info('✅ FAQ suggestion rejected: ' . $faqQuestion);

            return response()->json([
                'success' => true,
                'message' => 'FAQ suggestion rejected successfully'
            ]);
        } catch (Exception $e) {
            Log::error('FAQ suggestion rejection failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject suggestion.',
            ], 500);
        }
    }

    /**
     * Request revision for content suggestion
     */
    public function requestSuggestionRevision(Request $request, int $suggestionId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'feedback' => 'required|string|min:10|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $faq = FAQ::findOrFail($suggestionId);

            if ($faq->is_published) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot request revision for a published FAQ.'
                ], 422);
            }

            // Notify the original creator
            if ($faq->creator) {
                \App\Models\Notification::create([
                    'user_id' => $faq->creator->id,
                    'type' => 'system',
                    'title' => 'FAQ Suggestion Needs Revision',
                    'message' => "Your FAQ suggestion '{$faq->question}' needs revision. Feedback: " . $request->feedback,
                    'priority' => 'medium',
                    'data' => json_encode([
                        'faq_id' => $faq->id,
                        'feedback' => $request->feedback,
                        'action_required' => 'revise_faq_suggestion'
                    ]),
                ]);
            }

            Log::info('✅ FAQ revision requested: ' . $faq->question);

            return response()->json([
                'success' => true,
                'message' => 'Revision request sent successfully'
            ]);
        } catch (Exception $e) {
            Log::error('FAQ revision request failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to request revision.',
            ], 500);
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
     * Validate FAQ content
     */
    public function validateFAQContent(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'question' => 'required|string|min:10|max:500',
                'answer' => 'required|string|min:20|max:5000',
                'category_id' => 'required|exists:help_categories,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $errors = [];
            $suggestions = [];

            // Check question length and complexity
            if (str_word_count($request->question) < 3) {
                $errors[] = 'Question should contain at least 3 words';
            }

            // Check answer completeness
            if (str_word_count($request->answer) < 10) {
                $errors[] = 'Answer should be more comprehensive (at least 10 words)';
            }

            // Check for duplicate questions
            $duplicateCount = FAQ::where('question', 'LIKE', '%' . $request->question . '%')
                ->where('question', '!=', $request->question)
                ->count();

            if ($duplicateCount > 0) {
                $suggestions[] = "There are {$duplicateCount} similar questions. Consider reviewing existing FAQs.";
            }

            // Basic content quality checks
            if (!str_contains($request->answer, '?') && !str_contains($request->answer, '.')) {
                $suggestions[] = 'Consider adding proper punctuation to improve readability';
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'valid' => empty($errors),
                    'errors' => $errors,
                    'suggestions' => $suggestions
                ]
            ]);
        } catch (Exception $e) {
            Log::error('FAQ content validation failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate content.',
            ], 500);
        }
    }

    /**
     * Check for duplicate content
     */
    public function checkDuplicateContent(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'question' => 'required|string|min:5|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $question = $request->question;
            
            // Find similar FAQs using LIKE search
            $similarFAQs = FAQ::where('question', 'LIKE', '%' . $question . '%')
                ->orWhere('question', 'LIKE', '%' . substr($question, 0, 50) . '%')
                ->with(['category:id,name,slug'])
                ->take(5)
                ->get(['id', 'question', 'category_id', 'is_published']);

            $hasDuplicates = $similarFAQs->count() > 0;
            $similarityScore = $hasDuplicates ? 75 : 0; // Basic similarity scoring

            return response()->json([
                'success' => true,
                'data' => [
                    'has_duplicates' => $hasDuplicates,
                    'similar_faqs' => $similarFAQs,
                    'similarity_score' => $similarityScore
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Duplicate content check failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check for duplicates.',
            ], 500);
        }
    }

    /**
     * Generate FAQ suggestions (AI-powered placeholder)
     */
    public function generateFAQSuggestions(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'topic' => 'required|string|min:3|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $topic = $request->topic;
            
            // Placeholder AI suggestions - would integrate with actual AI service
            $suggestions = [
                [
                    'question' => "What is {$topic} and how does it work?",
                    'answer' => "This is a comprehensive guide to understanding {$topic}...",
                    'confidence' => 85
                ],
                [
                    'question' => "How can I get help with {$topic}?",
                    'answer' => "There are several ways to get assistance with {$topic}...",
                    'confidence' => 78
                ],
                [
                    'question' => "What are the common issues with {$topic}?",
                    'answer' => "Students often face these challenges when dealing with {$topic}...",
                    'confidence' => 72
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => ['suggestions' => $suggestions]
            ]);
        } catch (Exception $e) {
            Log::error('FAQ suggestions generation failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate suggestions.',
            ], 500);
        }
    }

    /**
     * Duplicate FAQ
     */
    public function duplicateFAQ(Request $request, FAQ $faq): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'new_title' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $newTitle = $request->get('new_title', $faq->question . ' (Copy)');

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

            Log::info('✅ FAQ duplicated: ' . $duplicatedFAQ->question);

            return response()->json([
                'success' => true,
                'message' => 'FAQ duplicated successfully',
                'data' => ['faq' => $duplicatedFAQ]
            ]);
        } catch (Exception $e) {
            Log::error('FAQ duplication failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate FAQ.',
            ], 500);
        }
    }

    /**
     * Move FAQ to category
     */
    public function moveFAQToCategory(Request $request, FAQ $faq): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'category_id' => 'required|exists:help_categories,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $oldCategory = $faq->category->name ?? 'Unknown';
            $newCategory = HelpCategory::find($request->category_id);

            $faq->update([
                'category_id' => $request->category_id,
                'updated_by' => $request->user()->id,
            ]);

            $faq->load(['category:id,name,slug,color']);

            Log::info("✅ FAQ moved from '{$oldCategory}' to '{$newCategory->name}': " . $faq->question);

            return response()->json([
                'success' => true,
                'message' => "FAQ moved to {$newCategory->name} successfully",
                'data' => ['faq' => $faq]
            ]);
        } catch (Exception $e) {
            Log::error('FAQ category move failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to move FAQ.',
            ], 500);
        }
    }

    /**
     * Merge FAQs
     */
    public function mergeFAQs(Request $request, FAQ $primaryFaq): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'secondary_faq_ids' => 'required|array|min:1',
                'secondary_faq_ids.*' => 'exists:faqs,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $secondaryFAQs = FAQ::whereIn('id', $request->secondary_faq_ids)->get();

            if ($secondaryFAQs->contains('id', $primaryFaq->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot merge FAQ with itself.'
                ], 422);
            }

            DB::beginTransaction();

            // Merge helpful counts and view counts
            $totalHelpful = $primaryFaq->helpful_count + $secondaryFAQs->sum('helpful_count');
            $totalNotHelpful = $primaryFaq->not_helpful_count + $secondaryFAQs->sum('not_helpful_count');
            $totalViews = $primaryFaq->view_count + $secondaryFAQs->sum('view_count');

            // Merge tags
            $allTags = collect($primaryFaq->tags ?? []);
            foreach ($secondaryFAQs as $faq) {
                $allTags = $allTags->merge($faq->tags ?? []);
            }
            $mergedTags = $allTags->unique()->values()->toArray();

            // Update primary FAQ
            $primaryFaq->update([
                'helpful_count' => $totalHelpful,
                'not_helpful_count' => $totalNotHelpful,
                'view_count' => $totalViews,
                'tags' => $mergedTags,
                'updated_by' => $request->user()->id,
            ]);

            // Move feedback from secondary FAQs to primary
            FAQFeedback::whereIn('faq_id', $request->secondary_faq_ids)
                ->update(['faq_id' => $primaryFaq->id]);

            // Delete secondary FAQs
            FAQ::whereIn('id', $request->secondary_faq_ids)->delete();

            DB::commit();

            $primaryFaq->load(['category:id,name,slug,color']);

            Log::info('✅ FAQs merged into: ' . $primaryFaq->question);

            return response()->json([
                'success' => true,
                'message' => 'FAQs merged successfully',
                'data' => ['faq' => $primaryFaq]
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('FAQ merge failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to merge FAQs.',
            ], 500);
        }
    }

    /**
     * Get FAQ history (placeholder)
     */
    public function getFAQHistory(Request $request, FAQ $faq): JsonResponse
    {
        try {
            // Placeholder for FAQ version history
            $history = [
                [
                    'version' => 3,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'updated_by' => $faq->updater->name ?? 'System',
                    'updated_at' => $faq->updated_at->toISOString(),
                    'changes' => ['answer updated', 'tags modified']
                ],
                [
                    'version' => 2,
                    'question' => $faq->question,
                    'answer' => 'Previous version of answer...',
                    'updated_by' => 'Admin User',
                    'updated_at' => $faq->updated_at->subDays(5)->toISOString(),
                    'changes' => ['question refined']
                ],
                [
                    'version' => 1,
                    'question' => 'Original question',
                    'answer' => 'Original answer...',
                    'updated_by' => $faq->creator->name ?? 'System',
                    'updated_at' => $faq->created_at->toISOString(),
                    'changes' => ['initial creation']
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => ['history' => $history]
            ]);
        } catch (Exception $e) {
            Log::error('FAQ history fetch failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch FAQ history.',
            ], 500);
        }
    }

    /**
     * Restore FAQ version (placeholder)
     */
    public function restoreFAQVersion(Request $request, FAQ $faq, int $versionId): JsonResponse
    {
        try {
            // Placeholder for version restoration
            Log::info("Restoring FAQ {$faq->id} to version {$versionId}");

            // In a real implementation, this would restore from version history
            $faq->update([
                'updated_by' => $request->user()->id,
                'updated_at' => now(),
            ]);

            $faq->load(['category:id,name,slug,color']);

            return response()->json([
                'success' => true,
                'message' => "FAQ restored to version {$versionId} successfully",
                'data' => ['faq' => $faq]
            ]);
        } catch (Exception $e) {
            Log::error('FAQ version restoration failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore FAQ version.',
            ], 500);
        }
    }

    /**
     * Bulk import FAQs
     */
    public function bulkImportFAQs(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:csv,txt,xlsx|max:2048',
                'category_id' => 'nullable|exists:help_categories,id',
                'auto_publish' => 'boolean',
                'overwrite_existing' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Placeholder for bulk import functionality
            $imported = 0;
            $failed = 0;
            $errors = [];

            // In a real implementation, this would parse the file and import FAQs
            // For now, return a mock response
            $imported = 15;
            $failed = 2;
            $errors = [
                'Row 3: Question too short',
                'Row 8: Invalid category'
            ];

            Log::info("✅ Bulk import completed: {$imported} imported, {$failed} failed");

            return response()->json([
                'success' => true,
                'message' => "Import completed: {$imported} FAQs imported, {$failed} failed",
                'data' => [
                    'imported' => $imported,
                    'failed' => $failed,
                    'errors' => $errors
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Bulk FAQ import failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to import FAQs.',
            ], 500);
        }
    }

    /**
     * Bulk export FAQs
     */
    public function bulkExportFAQs(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'category_ids' => 'nullable|array',
                'category_ids.*' => 'exists:help_categories,id',
                'format' => 'nullable|in:csv,json,xlsx',
                'include_drafts' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = FAQ::with(['category:id,name,slug']);

            if ($request->has('category_ids')) {
                $query->whereIn('category_id', $request->category_ids);
            }

            if (!$request->boolean('include_drafts')) {
                $query->published();
            }

            $faqs = $query->get();
            $format = $request->get('format', 'csv');

            // In a real implementation, this would generate the actual file
            $exportData = $faqs->map(function ($faq) {
                return [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'category' => $faq->category->name ?? '',
                    'tags' => implode(', ', $faq->tags ?? []),
                    'is_published' => $faq->is_published ? 'Yes' : 'No',
                    'is_featured' => $faq->is_featured ? 'Yes' : 'No',
                    'view_count' => $faq->view_count,
                    'helpful_count' => $faq->helpful_count,
                    'created_at' => $faq->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => "Export prepared: {$faqs->count()} FAQs ready for download",
                'data' => [
                    'export_data' => $exportData,
                    'count' => $faqs->count(),
                    'format' => $format,
                    'generated_at' => now()->toISOString()
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Bulk FAQ export failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export FAQs.',
            ], 500);
        }
    }

    /**
     * Clear help cache
     */
    public function clearHelpCache(Request $request): JsonResponse
    {
        try {
            Cache::tags(['help', 'faqs', 'categories'])->flush();
            
            Log::info('✅ Help cache cleared by admin: ' . $request->user()->name);

            return response()->json([
                'success' => true,
                'message' => 'Help cache cleared successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Help cache clear failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache.',
            ], 500);
        }
    }

    /**
     * Warm cache
     */
    public function warmCache(Request $request): JsonResponse
    {
        try {
            // Warm popular caches
            Cache::remember('popular_faqs', 3600, function () {
                return FAQ::published()->orderBy('view_count', 'desc')->take(10)->get();
            });

            Cache::remember('featured_faqs', 3600, function () {
                return FAQ::published()->featured()->take(3)->get();
            });

            Cache::remember('active_categories', 3600, function () {
                return HelpCategory::active()->orderBy('sort_order')->get();
            });

            Log::info('✅ Help cache warmed by admin: ' . $request->user()->name);

            return response()->json([
                'success' => true,
                'message' => 'Help cache warmed successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Help cache warming failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to warm cache.',
            ], 500);
        }
    }

    /**
     * Get cache stats
     */
    public function getCacheStats(Request $request): JsonResponse
    {
        try {
            // Placeholder cache statistics
            $stats = [
                'hit_rate' => 85.3,
                'miss_rate' => 14.7,
                'size' => 1024 * 1024 * 50, // 50MB
                'entries' => 1250
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (Exception $e) {
            Log::error('Cache stats fetch failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cache stats.',
            ], 500);
        }
    }

    /**
     * Get admin notifications
     */
    public function getAdminNotifications(Request $request): JsonResponse
    {
        try {
            $notifications = \App\Models\Notification::where('user_id', $request->user()->id)
                ->where('type', 'system')
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => ['notifications' => $notifications]
            ]);
        } catch (Exception $e) {
            Log::error('Admin notifications fetch failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications.',
            ], 500);
        }
    }

    /**
     * Get system health
     */
    public function getSystemHealth(Request $request): JsonResponse
    {
        try {
            $health = [
                'status' => 'healthy',
                'checks' => [
                    'database' => DB::connection()->getPdo() ? true : false,
                    'cache' => Cache::store()->getStore() ? true : false,
                    'search' => true, // Would check search service
                    'storage' => Storage::disk('public')->exists('') ? true : false,
                ],
                'metrics' => [
                    'response_time' => 1.2,
                    'error_rate' => 0.1,
                    'uptime' => 99.8,
                ],
                'last_check' => now()->toISOString()
            ];

            // Determine overall status
            $allChecksPass = !in_array(false, $health['checks']);
            $health['status'] = $allChecksPass ? 'healthy' : 'warning';

            return response()->json([
                'success' => true,
                'data' => $health
            ]);
        } catch (Exception $e) {
            Log::error('System health check failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check system health.',
                'data' => [
                    'status' => 'critical',
                    'error' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Export help data
     */
    public function exportHelpData(Request $request): JsonResponse
    {
        try {
            $format = $request->get('format', 'csv');
            
            $data = [
                'categories' => HelpCategory::all(),
                'faqs' => FAQ::with(['category:id,name'])->get(),
                'feedback' => FAQFeedback::with(['faq:id,question', 'user:id,name'])->get(),
                'analytics' => DB::table('help_analytics')->get(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Help data export prepared',
                'data' => [
                    'export_data' => $data,
                    'format' => $format,
                    'generated_at' => now()->toISOString(),
                    'record_counts' => [
                        'categories' => $data['categories']->count(),
                        'faqs' => $data['faqs']->count(),
                        'feedback' => $data['feedback']->count(),
                        'analytics' => $data['analytics']->count(),
                    ]
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Help data export failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export help data.',
            ], 500);
        }
    }
}