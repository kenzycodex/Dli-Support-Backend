<?php
// app/Http/Controllers/Admin/AdminTicketCategoryController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketCategory;
use App\Models\CounselorSpecialization;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Exception;

class AdminTicketCategoryController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all ticket categories with counselor counts
     */
    public function index(Request $request): JsonResponse
    {
        $this->logRequestDetails('Admin Ticket Categories Fetch');

        try {
            Log::info('=== FETCHING ADMIN TICKET CATEGORIES ===', [
                'user_id' => $request->user()->id,
                'filters' => $request->only(['include_inactive', 'with_counselors']),
            ]);

            $query = TicketCategory::withCount([
                'tickets',
                'tickets as open_tickets' => function ($q) {
                    $q->whereIn('status', ['Open', 'In Progress']);
                },
                'counselorSpecializations as counselor_count' => function ($q) {
                    $q->where('is_available', true);
                }
            ]);

            if (!$request->boolean('include_inactive')) {
                $query->where('is_active', true);
            }

            if ($request->boolean('with_counselors')) {
                $query->with(['counselorSpecializations.user:id,name,email,role']);
            }

            $categories = $query->orderBy('sort_order')->orderBy('name')->get();

            Log::info('✅ Admin ticket categories fetched successfully', [
                'count' => $categories->count()
            ]);

            return $this->successResponse([
                'categories' => $categories
            ], 'Ticket categories retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Admin ticket categories fetch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return $this->handleException($e, 'Ticket categories fetch');
        }
    }

    /**
     * Create new ticket category
     */
    public function store(Request $request): JsonResponse
    {
        $this->logRequestDetails('Ticket Category Creation');

        try {
            Log::info('=== CREATING TICKET CATEGORY ===', [
                'user_id' => $request->user()->id,
                'data' => $request->except(['counselors']),
            ]);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:ticket_categories,name',
                'description' => 'nullable|string|max:1000',
                'icon' => 'nullable|string|max:100',
                'color' => 'nullable|string|regex:/^#[A-Fa-f0-9]{6}$/',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
                'auto_assign' => 'boolean',
                'crisis_detection_enabled' => 'boolean',
                'sla_response_hours' => 'nullable|integer|min:1|max:168', // Max 1 week
                'max_priority_level' => 'nullable|integer|min:1|max:4',
                'notification_settings' => 'nullable|array',
                'counselors' => 'nullable|array',
                'counselors.*.user_id' => 'required_with:counselors|exists:users,id',
                'counselors.*.priority_level' => 'required_with:counselors|in:primary,secondary,backup',
                'counselors.*.max_workload' => 'required_with:counselors|integer|min:1|max:50',
                'counselors.*.expertise_rating' => 'nullable|numeric|min:1|max:5',
            ], [
                'name.required' => 'Category name is required',
                'name.unique' => 'A category with this name already exists',
                'color.regex' => 'Color must be a valid hex color code (e.g., #FF0000)',
                'sla_response_hours.max' => 'SLA response time cannot exceed 168 hours (1 week)',
                'counselors.*.user_id.exists' => 'Invalid counselor selected',
                'counselors.*.max_workload.max' => 'Maximum workload cannot exceed 50 tickets',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Ticket category validation failed', [
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check your input and try again');
            }

            DB::beginTransaction();

            try {
                // Create category
                $category = TicketCategory::create([
                    'name' => trim($request->name),
                    'slug' => Str::slug($request->name),
                    'description' => $request->description,
                    'icon' => $request->get('icon', 'MessageSquare'),
                    'color' => $request->get('color', '#3B82F6'),
                    'sort_order' => $request->get('sort_order', 0),
                    'is_active' => $request->get('is_active', true),
                    'auto_assign' => $request->get('auto_assign', true),
                    'crisis_detection_enabled' => $request->get('crisis_detection_enabled', false),
                    'sla_response_hours' => $request->get('sla_response_hours', 24),
                    'max_priority_level' => $request->get('max_priority_level', 3),
                    'notification_settings' => $request->get('notification_settings'),
                    'created_by' => $request->user()->id,
                ]);

                // Assign counselors if provided
                if ($request->has('counselors') && is_array($request->counselors)) {
                    foreach ($request->counselors as $counselorData) {
                        // Validate counselor role
                        $user = User::find($counselorData['user_id']);
                        if (!$user || !in_array($user->role, ['counselor', 'advisor'])) {
                            throw new Exception("User {$counselorData['user_id']} is not a counselor or advisor");
                        }

                        CounselorSpecialization::create([
                            'user_id' => $counselorData['user_id'],
                            'category_id' => $category->id,
                            'priority_level' => $counselorData['priority_level'],
                            'max_workload' => $counselorData['max_workload'],
                            'expertise_rating' => $counselorData['expertise_rating'] ?? 5.0,
                            'is_available' => true,
                            'assigned_by' => $request->user()->id,
                            'assigned_at' => now(),
                        ]);
                    }
                }

                DB::commit();

                // Load relationships for response
                $category->load(['counselorSpecializations.user:id,name,email,role']);

                Log::info('✅ Ticket category created successfully', [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'counselors_assigned' => count($request->counselors ?? []),
                ]);

                return $this->successResponse([
                    'category' => $category
                ], 'Ticket category created successfully', 201);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Ticket category creation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'Ticket category creation');
        }
    }

    /**
     * Get single ticket category with details
     */
    public function show(Request $request, TicketCategory $ticketCategory): JsonResponse
    {
        try {
            Log::info('=== FETCHING TICKET CATEGORY DETAILS ===', [
                'category_id' => $ticketCategory->id,
            ]);

            $ticketCategory->load([
                'counselorSpecializations.user:id,name,email,role',
                'crisisKeywords' => function ($query) {
                    $query->where('is_active', true)->orderBy('severity_level');
                },
                'tickets' => function ($query) {
                    $query->select('id', 'category_id', 'status', 'priority', 'crisis_flag', 'created_at')
                          ->latest()
                          ->limit(10);
                }
            ]);

            // Add statistics
            $stats = [
                'total_tickets' => $ticketCategory->tickets()->count(),
                'open_tickets' => $ticketCategory->tickets()->whereIn('status', ['Open', 'In Progress'])->count(),
                'crisis_tickets' => $ticketCategory->tickets()->where('crisis_flag', true)->count(),
                'avg_resolution_time' => $this->calculateAvgResolutionTime($ticketCategory),
                'counselor_workload' => $this->getCounselorWorkloadStats($ticketCategory),
            ];

            return $this->successResponse([
                'category' => $ticketCategory,
                'stats' => $stats
            ], 'Category details retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Category details fetch failed', [
                'category_id' => $ticketCategory->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            
            return $this->handleException($e, 'Category details fetch');
        }
    }

    /**
     * Update ticket category
     */
    public function update(Request $request, TicketCategory $ticketCategory): JsonResponse
    {
        $this->logRequestDetails('Ticket Category Update');

        try {
            Log::info('=== UPDATING TICKET CATEGORY ===', [
                'category_id' => $ticketCategory->id,
                'category_name' => $ticketCategory->name,
            ]);

            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('ticket_categories')->ignore($ticketCategory->id)
                ],
                'description' => 'nullable|string|max:1000',
                'icon' => 'nullable|string|max:100',
                'color' => 'nullable|string|regex:/^#[A-Fa-f0-9]{6}$/',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
                'auto_assign' => 'boolean',
                'crisis_detection_enabled' => 'boolean',
                'sla_response_hours' => 'nullable|integer|min:1|max:168',
                'max_priority_level' => 'nullable|integer|min:1|max:4',
                'notification_settings' => 'nullable|array',
            ], [
                'name.required' => 'Category name is required',
                'name.unique' => 'A category with this name already exists',
                'color.regex' => 'Color must be a valid hex color code (e.g., #FF0000)',
                'sla_response_hours.max' => 'SLA response time cannot exceed 168 hours (1 week)',
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Ticket category update validation failed', [
                    'category_id' => $ticketCategory->id,
                    'errors' => $validator->errors(),
                ]);
                return $this->validationErrorResponse($validator, 'Please check your input and try again');
            }

            DB::beginTransaction();

            try {
                $ticketCategory->update([
                    'name' => trim($request->name),
                    'slug' => Str::slug($request->name),
                    'description' => $request->description,
                    'icon' => $request->get('icon', $ticketCategory->icon),
                    'color' => $request->get('color', $ticketCategory->color),
                    'sort_order' => $request->get('sort_order', $ticketCategory->sort_order),
                    'is_active' => $request->get('is_active', $ticketCategory->is_active),
                    'auto_assign' => $request->get('auto_assign', $ticketCategory->auto_assign),
                    'crisis_detection_enabled' => $request->get('crisis_detection_enabled', $ticketCategory->crisis_detection_enabled),
                    'sla_response_hours' => $request->get('sla_response_hours', $ticketCategory->sla_response_hours),
                    'max_priority_level' => $request->get('max_priority_level', $ticketCategory->max_priority_level),
                    'notification_settings' => $request->get('notification_settings', $ticketCategory->notification_settings),
                    'updated_by' => $request->user()->id,
                ]);

                DB::commit();

                Log::info('✅ Ticket category updated successfully', [
                    'category_id' => $ticketCategory->id,
                    'category_name' => $ticketCategory->name,
                ]);

                return $this->successResponse([
                    'category' => $ticketCategory->fresh(['counselorSpecializations.user'])
                ], 'Ticket category updated successfully');

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Ticket category update failed', [
                'category_id' => $ticketCategory->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'Ticket category update');
        }
    }

    /**
     * Delete ticket category
     */
    public function destroy(Request $request, TicketCategory $ticketCategory): JsonResponse
    {
        $this->logRequestDetails('Ticket Category Deletion');

        try {
            Log::info('=== DELETING TICKET CATEGORY ===', [
                'category_id' => $ticketCategory->id,
                'category_name' => $ticketCategory->name,
            ]);

            // Check if category has tickets
            $ticketCount = $ticketCategory->tickets()->count();
            if ($ticketCount > 0) {
                Log::warning('❌ Cannot delete category with existing tickets', [
                    'category_id' => $ticketCategory->id,
                    'ticket_count' => $ticketCount,
                ]);
                return $this->errorResponse(
                    'Cannot delete category with existing tickets. Please move or resolve tickets first.',
                    422
                );
            }

            $categoryName = $ticketCategory->name;
            $categoryId = $ticketCategory->id;

            DB::beginTransaction();

            try {
                // Delete counselor specializations
                $ticketCategory->counselorSpecializations()->delete();
                
                // Delete crisis keywords
                $ticketCategory->crisisKeywords()->delete();
                
                // Delete the category
                $deleted = $ticketCategory->delete();

                if (!$deleted) {
                    throw new Exception('Failed to delete category from database');
                }

                DB::commit();

                Log::info('✅ Ticket category deleted successfully', [
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                ]);

                return $this->deleteSuccessResponse('Ticket Category', $categoryName);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Ticket category deletion failed', [
                'category_id' => $ticketCategory->id ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->handleException($e, 'Ticket category deletion');
        }
    }

    /**
     * Reorder categories
     */
    public function reorder(Request $request): JsonResponse
    {
        try {
            Log::info('=== REORDERING TICKET CATEGORIES ===', [
                'user_id' => $request->user()->id,
            ]);

            $validator = Validator::make($request->all(), [
                'categories' => 'required|array',
                'categories.*.id' => 'required|exists:ticket_categories,id',
                'categories.*.sort_order' => 'required|integer|min:0',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            DB::beginTransaction();

            try {
                foreach ($request->categories as $categoryData) {
                    TicketCategory::where('id', $categoryData['id'])
                        ->update([
                            'sort_order' => $categoryData['sort_order'],
                            'updated_by' => $request->user()->id,
                        ]);
                }

                DB::commit();

                Log::info('✅ Categories reordered successfully');

                return $this->successResponse([], 'Categories reordered successfully');

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Category reorder failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->handleException($e, 'Category reorder');
        }
    }

    /**
     * Get category statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            Log::info('=== FETCHING CATEGORY STATISTICS ===');

            $stats = [
                'overview' => [
                    'total_categories' => TicketCategory::count(),
                    'active_categories' => TicketCategory::where('is_active', true)->count(),
                    'categories_with_auto_assign' => TicketCategory::where('auto_assign', true)->count(),
                    'categories_with_crisis_detection' => TicketCategory::where('crisis_detection_enabled', true)->count(),
                ],
                'category_performance' => TicketCategory::withCount([
                    'tickets',
                    'tickets as open_tickets' => function ($q) {
                        $q->whereIn('status', ['Open', 'In Progress']);
                    },
                    'tickets as resolved_tickets' => function ($q) {
                        $q->where('status', 'Resolved');
                    },
                    'counselorSpecializations as counselor_count'
                ])->get(),
                'counselor_distribution' => $this->getCounselorDistribution(),
                'workload_analysis' => $this->getWorkloadAnalysis(),
            ];

            return $this->successResponse($stats, 'Category statistics retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Category stats fetch failed', [
                'error' => $e->getMessage(),
            ]);
            
            return $this->handleException($e, 'Category stats fetch');
        }
    }

    /**
     * Private helper methods
     */
    private function calculateAvgResolutionTime(TicketCategory $category): ?float
    {
        $resolvedTickets = $category->tickets()
            ->whereNotNull('resolved_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours')
            ->first();

        return $resolvedTickets?->avg_hours ? round($resolvedTickets->avg_hours, 2) : null;
    }

    private function getCounselorWorkloadStats(TicketCategory $category): array
    {
        return $category->counselorSpecializations()
            ->with('user:id,name')
            ->get()
            ->map(function ($spec) {
                return [
                    'counselor_name' => $spec->user->name,
                    'current_workload' => $spec->current_workload,
                    'max_workload' => $spec->max_workload,
                    'utilization_rate' => $spec->max_workload > 0 ? 
                        round(($spec->current_workload / $spec->max_workload) * 100, 1) : 0,
                    'priority_level' => $spec->priority_level,
                    'is_available' => $spec->is_available,
                ];
            })->toArray();
    }

    private function getCounselorDistribution(): array
    {
        return CounselorSpecialization::selectRaw('category_id, count(*) as counselor_count')
            ->join('ticket_categories', 'counselor_specializations.category_id', '=', 'ticket_categories.id')
            ->where('counselor_specializations.is_available', true)
            ->groupBy('category_id')
            ->with('category:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'category_name' => $item->category->name,
                    'counselor_count' => $item->counselor_count,
                ];
            })->toArray();
    }

    private function getWorkloadAnalysis(): array
    {
        $specializations = CounselorSpecialization::with('user:id,name', 'category:id,name')
            ->where('is_available', true)
            ->get();

        return [
            'total_capacity' => $specializations->sum('max_workload'),
            'current_utilization' => $specializations->sum('current_workload'),
            'average_utilization_rate' => $specializations->avg(function ($spec) {
                return $spec->max_workload > 0 ? ($spec->current_workload / $spec->max_workload) * 100 : 0;
            }),
            'overloaded_counselors' => $specializations->filter(function ($spec) {
                return $spec->current_workload >= $spec->max_workload;
            })->count(),
            'available_capacity' => $specializations->sum(function ($spec) {
                return max(0, $spec->max_workload - $spec->current_workload);
            }),
        ];
    }
}