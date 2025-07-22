<?php
// app/Http/Controllers/Admin/AdminCounselorSpecializationController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CounselorSpecialization;
use App\Models\TicketCategory;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Exception;

class AdminCounselorSpecializationController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all counselor specializations
     */
    public function index(Request $request): JsonResponse
    {
        try {
            Log::info('=== FETCHING COUNSELOR SPECIALIZATIONS ===', [
                'user_id' => $request->user()->id,
                'filters' => $request->only(['category_id', 'user_id', 'is_available']),
            ]);

            $query = CounselorSpecialization::with([
                'user:id,name,email,role,status',
                'category:id,name,slug,color,icon',
                'assignedBy:id,name'
            ]);

            // Apply filters
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->has('is_available')) {
                $query->where('is_available', $request->boolean('is_available'));
            }

            $specializations = $query->orderBy('category_id')
                ->orderBy('priority_level')
                ->orderBy('current_workload')
                ->get();

            // Group by category for better organization
            $groupedByCategory = $specializations->groupBy('category.name');

            return $this->successResponse([
                'specializations' => $specializations,
                'grouped_by_category' => $groupedByCategory,
                'summary' => [
                    'total_specializations' => $specializations->count(),
                    'available_counselors' => $specializations->where('is_available', true)->count(),
                    'categories_covered' => $specializations->pluck('category_id')->unique()->count(),
                    'total_capacity' => $specializations->sum('max_workload'),
                    'current_utilization' => $specializations->sum('current_workload'),
                ]
            ], 'Counselor specializations retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Counselor specializations fetch failed', [
                'error' => $e->getMessage(),
            ]);
            
            return $this->handleException($e, 'Counselor specializations fetch');
        }
    }

    /**
     * Assign counselor to category
     */
    public function store(Request $request): JsonResponse
    {
        $this->logRequestDetails('Counselor Specialization Creation');

        try {
            Log::info('=== ASSIGNING COUNSELOR TO CATEGORY ===', [
                'user_id' => $request->user()->id,
                'data' => $request->all(),
            ]);

            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'category_id' => 'required|exists:ticket_categories,id',
                'priority_level' => 'required|in:primary,secondary,backup',
                'max_workload' => 'required|integer|min:1|max:50',
                'expertise_rating' => 'nullable|numeric|min:1|max:5',
                'availability_schedule' => 'nullable|array',
                'notes' => 'nullable|string|max:1000',
            ], [
                'user_id.exists' => 'Invalid user selected',
                'category_id.exists' => 'Invalid category selected',
                'max_workload.max' => 'Maximum workload cannot exceed 50 tickets',
                'expertise_rating.max' => 'Expertise rating cannot exceed 5.0',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            // Validate user role
            $user = User::find($request->user_id);
            if (!in_array($user->role, ['counselor', 'advisor'])) {
                return $this->errorResponse('Only counselors and advisors can be assigned to categories', 422);
            }

            // Check for existing specialization
            $existingSpecialization = CounselorSpecialization::where('user_id', $request->user_id)
                ->where('category_id', $request->category_id)
                ->first();

            if ($existingSpecialization) {
                return $this->errorResponse('This counselor is already assigned to this category', 422);
            }

            DB::beginTransaction();

            try {
                $specialization = CounselorSpecialization::create([
                    'user_id' => $request->user_id,
                    'category_id' => $request->category_id,
                    'priority_level' => $request->priority_level,
                    'max_workload' => $request->max_workload,
                    'current_workload' => 0,
                    'is_available' => true,
                    'availability_schedule' => $request->availability_schedule,
                    'expertise_rating' => $request->get('expertise_rating', 5.0),
                    'notes' => $request->notes,
                    'assigned_by' => $request->user()->id,
                    'assigned_at' => now(),
                ]);

                DB::commit();

                $specialization->load(['user:id,name,email,role', 'category:id,name,slug,color']);

                Log::info('✅ Counselor assigned to category successfully', [
                    'specialization_id' => $specialization->id,
                    'counselor' => $user->name,
                    'category' => $specialization->category->name,
                ]);

                return $this->successResponse([
                    'specialization' => $specialization
                ], 'Counselor assigned to category successfully', 201);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Counselor specialization creation failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->handleException($e, 'Counselor specialization creation');
        }
    }

    /**
     * Update counselor specialization
     */
    public function update(Request $request, CounselorSpecialization $counselorSpecialization): JsonResponse
    {
        try {
            Log::info('=== UPDATING COUNSELOR SPECIALIZATION ===', [
                'specialization_id' => $counselorSpecialization->id,
            ]);

            $validator = Validator::make($request->all(), [
                'priority_level' => 'sometimes|in:primary,secondary,backup',
                'max_workload' => 'sometimes|integer|min:1|max:50',
                'current_workload' => 'sometimes|integer|min:0',
                'is_available' => 'sometimes|boolean',
                'availability_schedule' => 'sometimes|nullable|array',
								'expertise_rating' => 'sometimes|numeric|min:1|max:5',
                'notes' => 'sometimes|nullable|string|max:1000',
            ], [
                'max_workload.max' => 'Maximum workload cannot exceed 50 tickets',
                'expertise_rating.max' => 'Expertise rating cannot exceed 5.0',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            DB::beginTransaction();

            try {
                $counselorSpecialization->update($request->only([
                    'priority_level',
                    'max_workload',
                    'current_workload',
                    'is_available',
                    'availability_schedule',
                    'expertise_rating',
                    'notes',
                ]));

                DB::commit();

                $counselorSpecialization->load(['user:id,name,email,role', 'category:id,name,slug,color']);

                Log::info('✅ Counselor specialization updated successfully', [
                    'specialization_id' => $counselorSpecialization->id,
                ]);

                return $this->successResponse([
                    'specialization' => $counselorSpecialization
                ], 'Specialization updated successfully');

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Counselor specialization update failed', [
                'specialization_id' => $counselorSpecialization->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return $this->handleException($e, 'Counselor specialization update');
        }
    }

    /**
     * Remove counselor from category
     */
    public function destroy(Request $request, CounselorSpecialization $counselorSpecialization): JsonResponse
    {
        try {
            Log::info('=== REMOVING COUNSELOR FROM CATEGORY ===', [
                'specialization_id' => $counselorSpecialization->id,
                'counselor' => $counselorSpecialization->user->name,
                'category' => $counselorSpecialization->category->name,
            ]);

            // Check if counselor has active tickets in this category
            $activeTickets = $counselorSpecialization->user->assignedTickets()
                ->where('category_id', $counselorSpecialization->category_id)
                ->whereIn('status', ['Open', 'In Progress'])
                ->count();

            if ($activeTickets > 0) {
                return $this->errorResponse(
                    "Cannot remove counselor from category. They have {$activeTickets} active tickets in this category. Please reassign or resolve tickets first.",
                    422
                );
            }

            $counselorName = $counselorSpecialization->user->name;
            $categoryName = $counselorSpecialization->category->name;

            DB::beginTransaction();

            try {
                $counselorSpecialization->delete();

                DB::commit();

                Log::info('✅ Counselor removed from category successfully', [
                    'counselor' => $counselorName,
                    'category' => $categoryName,
                ]);

                return $this->successResponse([], "Counselor removed from {$categoryName} successfully");

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Counselor specialization removal failed', [
                'specialization_id' => $counselorSpecialization->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return $this->handleException($e, 'Counselor specialization removal');
        }
    }

    /**
     * Bulk assign counselors to categories
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        try {
            Log::info('=== BULK ASSIGNING COUNSELORS ===', [
                'user_id' => $request->user()->id,
            ]);

            $validator = Validator::make($request->all(), [
                'assignments' => 'required|array|min:1',
                'assignments.*.user_id' => 'required|exists:users,id',
                'assignments.*.category_id' => 'required|exists:ticket_categories,id',
                'assignments.*.priority_level' => 'required|in:primary,secondary,backup',
                'assignments.*.max_workload' => 'required|integer|min:1|max:50',
                'assignments.*.expertise_rating' => 'nullable|numeric|min:1|max:5',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $successCount = 0;
            $errors = [];

            DB::beginTransaction();

            try {
                foreach ($request->assignments as $index => $assignment) {
                    try {
                        // Validate user role
                        $user = User::find($assignment['user_id']);
                        if (!in_array($user->role, ['counselor', 'advisor'])) {
                            $errors[] = "Assignment {$index}: {$user->name} is not a counselor or advisor";
                            continue;
                        }

                        // Check for existing specialization
                        $existing = CounselorSpecialization::where('user_id', $assignment['user_id'])
                            ->where('category_id', $assignment['category_id'])
                            ->first();

                        if ($existing) {
                            $errors[] = "Assignment {$index}: {$user->name} is already assigned to this category";
                            continue;
                        }

                        CounselorSpecialization::create([
                            'user_id' => $assignment['user_id'],
                            'category_id' => $assignment['category_id'],
                            'priority_level' => $assignment['priority_level'],
                            'max_workload' => $assignment['max_workload'],
                            'current_workload' => 0,
                            'is_available' => true,
                            'expertise_rating' => $assignment['expertise_rating'] ?? 5.0,
                            'assigned_by' => $request->user()->id,
                            'assigned_at' => now(),
                        ]);

                        $successCount++;

                    } catch (Exception $assignmentError) {
                        $errors[] = "Assignment {$index}: " . $assignmentError->getMessage();
                    }
                }

                DB::commit();

                Log::info('✅ Bulk assignment completed', [
                    'success_count' => $successCount,
                    'error_count' => count($errors),
                ]);

                return $this->successResponse([
                    'success_count' => $successCount,
                    'total_count' => count($request->assignments),
                    'errors' => $errors
                ], "Successfully assigned {$successCount} counselors");

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Bulk assignment failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->handleException($e, 'Bulk assignment');
        }
    }

    /**
     * Update counselor availability
     */
    public function updateAvailability(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'specializations' => 'required|array|min:1',
                'specializations.*.id' => 'required|exists:counselor_specializations,id',
                'specializations.*.is_available' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $updatedCount = 0;

            DB::beginTransaction();

            try {
                foreach ($request->specializations as $data) {
                    CounselorSpecialization::where('id', $data['id'])
                        ->update(['is_available' => $data['is_available']]);
                    $updatedCount++;
                }

                DB::commit();

                return $this->successResponse([
                    'updated_count' => $updatedCount
                ], "Updated availability for {$updatedCount} specializations");

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return $this->handleException($e, 'Availability update');
        }
    }

    /**
     * Get available counselors for a category
     */
    public function getAvailableCounselors(Request $request, TicketCategory $category): JsonResponse
    {
        try {
            Log::info('=== FETCHING AVAILABLE COUNSELORS FOR CATEGORY ===', [
                'category_id' => $category->id,
                'category_name' => $category->name,
            ]);

            $counselors = $category->counselorSpecializations()
                ->with('user:id,name,email,role,status')
                ->where('is_available', true)
                ->whereColumn('current_workload', '<', 'max_workload')
                ->whereHas('user', function ($query) {
                    $query->where('status', 'active');
                })
                ->orderBy('priority_level')
                ->orderBy('current_workload')
                ->orderByDesc('expertise_rating')
                ->get()
                ->map(function ($specialization) {
                    return [
                        'id' => $specialization->user->id,
                        'name' => $specialization->user->name,
                        'email' => $specialization->user->email,
                        'role' => $specialization->user->role,
                        'priority_level' => $specialization->priority_level,
                        'current_workload' => $specialization->current_workload,
                        'max_workload' => $specialization->max_workload,
                        'utilization_rate' => $specialization->getWorkloadPercentage(),
                        'expertise_rating' => $specialization->expertise_rating,
                        'assignment_score' => $specialization->getAssignmentScore(),
                        'can_take_ticket' => $specialization->canTakeTicket(),
                    ];
                });

            return $this->successResponse([
                'counselors' => $counselors,
                'best_available' => $counselors->where('can_take_ticket', true)->first(),
                'total_available' => $counselors->where('can_take_ticket', true)->count(),
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'auto_assign' => $category->auto_assign,
                ]
            ], 'Available counselors retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Available counselors fetch failed', [
                'category_id' => $category->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            
            return $this->handleException($e, 'Available counselors fetch');
        }
    }

    /**
     * Get workload statistics
     */
    public function getWorkloadStats(Request $request): JsonResponse
    {
        try {
            Log::info('=== FETCHING WORKLOAD STATISTICS ===');

            $stats = [
                'overview' => [
                    'total_counselors' => User::whereIn('role', ['counselor', 'advisor'])->count(),
                    'assigned_counselors' => CounselorSpecialization::distinct('user_id')->count(),
                    'available_counselors' => CounselorSpecialization::where('is_available', true)->distinct('user_id')->count(),
                    'total_capacity' => CounselorSpecialization::sum('max_workload'),
                    'current_utilization' => CounselorSpecialization::sum('current_workload'),
                ],
                'by_category' => TicketCategory::withCount([
                    'counselorSpecializations as total_counselors',
                    'counselorSpecializations as available_counselors' => function ($query) {
                        $query->where('is_available', true);
                    }
                ])->get()->map(function ($category) {
                    $capacity = $category->counselorSpecializations()->sum('max_workload');
                    $utilization = $category->counselorSpecializations()->sum('current_workload');
                    
                    return [
                        'category_name' => $category->name,
                        'total_counselors' => $category->total_counselors,
                        'available_counselors' => $category->available_counselors,
                        'total_capacity' => $capacity,
                        'current_utilization' => $utilization,
                        'utilization_rate' => $capacity > 0 ? round(($utilization / $capacity) * 100, 1) : 0,
                    ];
                }),
                'counselor_workloads' => CounselorSpecialization::with(['user:id,name', 'category:id,name'])
                    ->get()
                    ->groupBy('user_id')
                    ->map(function ($specializations, $userId) {
                        $user = $specializations->first()->user;
                        $totalCapacity = $specializations->sum('max_workload');
                        $currentLoad = $specializations->sum('current_workload');
                        
                        return [
                            'counselor_name' => $user->name,
                            'total_capacity' => $totalCapacity,
                            'current_workload' => $currentLoad,
                            'utilization_rate' => $totalCapacity > 0 ? round(($currentLoad / $totalCapacity) * 100, 1) : 0,
                            'categories' => $specializations->map(function ($spec) {
                                return [
                                    'category_name' => $spec->category->name,
                                    'current_workload' => $spec->current_workload,
                                    'max_workload' => $spec->max_workload,
                                    'priority_level' => $spec->priority_level,
                                    'is_available' => $spec->is_available,
                                ];
                            })->values(),
                        ];
                    })->values(),
            ];

            return $this->successResponse($stats, 'Workload statistics retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Workload stats fetch failed', [
                'error' => $e->getMessage(),
            ]);
            
            return $this->handleException($e, 'Workload stats fetch');
        }
    }

    /**
     * Reset workload counters (for maintenance)
     */
    public function resetWorkloads(Request $request): JsonResponse
    {
        try {
            Log::info('=== RESETTING WORKLOAD COUNTERS ===', [
                'user_id' => $request->user()->id,
            ]);

            DB::beginTransaction();

            try {
                // Recalculate workloads based on actual open tickets
                $specializations = CounselorSpecialization::all();
                
                foreach ($specializations as $specialization) {
                    $actualWorkload = $specialization->user->assignedTickets()
                        ->where('category_id', $specialization->category_id)
                        ->whereIn('status', ['Open', 'In Progress'])
                        ->count();
                    
                    $specialization->update(['current_workload' => $actualWorkload]);
                }

                DB::commit();

                Log::info('✅ Workload counters reset successfully');

                return $this->successResponse([], 'Workload counters reset successfully');

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Workload reset failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->handleException($e, 'Workload reset');
        }
    }
}