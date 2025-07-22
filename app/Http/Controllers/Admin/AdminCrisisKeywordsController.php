<?php
// app/Http/Controllers/Admin/AdminCrisisKeywordsController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrisisKeyword;
use App\Models\TicketCategory;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Exception;

class AdminCrisisKeywordsController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all crisis keywords
     */
    public function index(Request $request): JsonResponse
    {
        try {
            Log::info('=== FETCHING CRISIS KEYWORDS ===', [
                'user_id' => $request->user()->id,
                'filters' => $request->only(['category_id', 'severity_level', 'is_active']),
            ]);

            $query = CrisisKeyword::with(['category:id,name,slug,color'])
                ->withCount(['category as category_name' => function($q) {
                    $q->select('name');
                }]);

            // Apply filters
            if ($request->has('category_id')) {
                if ($request->category_id === 'global') {
                    $query->whereNull('category_id');
                } else {
                    $query->where('category_id', $request->category_id);
                }
            }

            if ($request->has('severity_level') && $request->severity_level !== 'all') {
                $query->where('severity_level', $request->severity_level);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $keywords = $query->orderBy('severity_level')
                ->orderByDesc('trigger_count')
                ->orderBy('keyword')
                ->get();

            // Group by severity for better organization
            $groupedBySeverity = $keywords->groupBy('severity_level');

            // Get statistics
            $stats = [
                'total_keywords' => $keywords->count(),
                'active_keywords' => $keywords->where('is_active', true)->count(),
                'global_keywords' => $keywords->whereNull('category_id')->count(),
                'category_specific' => $keywords->whereNotNull('category_id')->count(),
                'by_severity' => [
                    'critical' => $keywords->where('severity_level', 'critical')->count(),
                    'high' => $keywords->where('severity_level', 'high')->count(),
                    'medium' => $keywords->where('severity_level', 'medium')->count(),
                    'low' => $keywords->where('severity_level', 'low')->count(),
                ],
                'total_triggers' => $keywords->sum('trigger_count'),
                'most_triggered' => $keywords->sortByDesc('trigger_count')->take(5)->values(),
            ];

            return $this->successResponse([
                'keywords' => $keywords,
                'grouped_by_severity' => $groupedBySeverity,
                'stats' => $stats
            ], 'Crisis keywords retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Crisis keywords fetch failed', [
                'error' => $e->getMessage(),
            ]);
            
            return $this->handleException($e, 'Crisis keywords fetch');
        }
    }

    /**
     * Create new crisis keyword
     */
    public function store(Request $request): JsonResponse
    {
        $this->logRequestDetails('Crisis Keyword Creation');

        try {
            Log::info('=== CREATING CRISIS KEYWORD ===', [
                'user_id' => $request->user()->id,
                'data' => $request->except(['response_action', 'notification_rules']),
            ]);

            $validator = Validator::make($request->all(), [
                'keyword' => 'required|string|max:255',
                'severity_level' => 'required|in:low,medium,high,critical',
                'category_id' => 'nullable|exists:ticket_categories,id',
                'is_active' => 'boolean',
                'exact_match' => 'boolean',
                'case_sensitive' => 'boolean',
                'response_action' => 'nullable|string|max:1000',
                'notification_rules' => 'nullable|array',
                'notification_rules.notify_admins' => 'boolean',
                'notification_rules.notify_counselors' => 'boolean',
                'notification_rules.auto_escalate' => 'boolean',
                'notification_rules.email_alerts' => 'boolean',
            ], [
                'keyword.required' => 'Keyword is required',
                'keyword.max' => 'Keyword cannot exceed 255 characters',
                'severity_level.required' => 'Severity level is required',
                'severity_level.in' => 'Invalid severity level',
                'category_id.exists' => 'Invalid category selected',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator, 'Please check your input and try again');
            }

            // Check for duplicate keywords
            $existingKeyword = CrisisKeyword::where('keyword', strtolower(trim($request->keyword)))
                ->where('category_id', $request->category_id)
                ->first();

            if ($existingKeyword) {
                return $this->errorResponse('A keyword with this text already exists for this category', 422);
            }

            DB::beginTransaction();

            try {
                $keyword = CrisisKeyword::create([
                    'keyword' => strtolower(trim($request->keyword)),
                    'severity_level' => $request->severity_level,
                    'category_id' => $request->category_id,
                    'is_active' => $request->get('is_active', true),
                    'exact_match' => $request->get('exact_match', false),
                    'case_sensitive' => $request->get('case_sensitive', false),
                    'trigger_count' => 0,
                    'response_action' => $request->response_action,
                    'notification_rules' => $request->notification_rules,
                    'created_by' => $request->user()->id,
                ]);

                DB::commit();

                $keyword->load(['category:id,name,slug,color']);

                Log::info('✅ Crisis keyword created successfully', [
                    'keyword_id' => $keyword->id,
                    'keyword_text' => $keyword->keyword,
                    'severity' => $keyword->severity_level,
                ]);

                return $this->successResponse([
                    'keyword' => $keyword
                ], 'Crisis keyword created successfully', 201);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Crisis keyword creation failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->handleException($e, 'Crisis keyword creation');
        }
    }

    /**
     * Update crisis keyword
     */
    public function update(Request $request, CrisisKeyword $crisisKeyword): JsonResponse
    {
        try {
            Log::info('=== UPDATING CRISIS KEYWORD ===', [
                'keyword_id' => $crisisKeyword->id,
                'keyword_text' => $crisisKeyword->keyword,
            ]);

            $validator = Validator::make($request->all(), [
                'keyword' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('crisis_keywords')
                        ->ignore($crisisKeyword->id)
                        ->where('category_id', $request->category_id)
                ],
                'severity_level' => 'required|in:low,medium,high,critical',
                'category_id' => 'nullable|exists:ticket_categories,id',
                'is_active' => 'boolean',
                'exact_match' => 'boolean',
                'case_sensitive' => 'boolean',
                'response_action' => 'nullable|string|max:1000',
                'notification_rules' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            DB::beginTransaction();

            try {
                $crisisKeyword->update([
                    'keyword' => strtolower(trim($request->keyword)),
                    'severity_level' => $request->severity_level,
                    'category_id' => $request->category_id,
                    'is_active' => $request->get('is_active', $crisisKeyword->is_active),
                    'exact_match' => $request->get('exact_match', $crisisKeyword->exact_match),
                    'case_sensitive' => $request->get('case_sensitive', $crisisKeyword->case_sensitive),
                    'response_action' => $request->response_action,
                    'notification_rules' => $request->notification_rules,
                    'updated_by' => $request->user()->id,
                ]);

                DB::commit();

                $crisisKeyword->load(['category:id,name,slug,color']);

                Log::info('✅ Crisis keyword updated successfully', [
                    'keyword_id' => $crisisKeyword->id,
                ]);

                return $this->successResponse([
                    'keyword' => $crisisKeyword
                ], 'Crisis keyword updated successfully');

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Crisis keyword update failed', [
                'keyword_id' => $crisisKeyword->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return $this->handleException($e, 'Crisis keyword update');
        }
    }

    /**
     * Delete crisis keyword
     */
    public function destroy(Request $request, CrisisKeyword $crisisKeyword): JsonResponse
    {
        try {
            Log::info('=== DELETING CRISIS KEYWORD ===', [
                'keyword_id' => $crisisKeyword->id,
                'keyword_text' => $crisisKeyword->keyword,
            ]);

            $keywordText = $crisisKeyword->keyword;
            $keywordId = $crisisKeyword->id;

            DB::beginTransaction();

            try {
                $deleted = $crisisKeyword->delete();

                if (!$deleted) {
                    throw new Exception('Failed to delete keyword from database');
                }

                DB::commit();

                Log::info('✅ Crisis keyword deleted successfully', [
                    'keyword_id' => $keywordId,
                    'keyword_text' => $keywordText,
                ]);

                return $this->deleteSuccessResponse('Crisis Keyword', $keywordText);

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Crisis keyword deletion failed', [
                'keyword_id' => $crisisKeyword->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return $this->handleException($e, 'Crisis keyword deletion');
        }
    }

    /**
     * Bulk operations on crisis keywords
     */
    public function bulkAction(Request $request): JsonResponse
    {
        try {
            Log::info('=== BULK CRISIS KEYWORD ACTION ===', [
                'user_id' => $request->user()->id,
                'action' => $request->input('action'),
            ]);

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:activate,deactivate,delete,change_severity',
                'keyword_ids' => 'required|array|min:1',
                'keyword_ids.*' => 'exists:crisis_keywords,id',
                'severity_level' => 'required_if:action,change_severity|in:low,medium,high,critical',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $keywordIds = $request->keyword_ids;
            $action = $request->action;
            $affected = 0;

            DB::beginTransaction();

            try {
                switch ($action) {
                    case 'activate':
                        $affected = CrisisKeyword::whereIn('id', $keywordIds)->update([
                            'is_active' => true,
                            'updated_by' => $request->user()->id,
                        ]);
                        break;

                    case 'deactivate':
                        $affected = CrisisKeyword::whereIn('id', $keywordIds)->update([
                            'is_active' => false,
                            'updated_by' => $request->user()->id,
                        ]);
                        break;

                    case 'change_severity':
                        $affected = CrisisKeyword::whereIn('id', $keywordIds)->update([
                            'severity_level' => $request->severity_level,
                            'updated_by' => $request->user()->id,
                        ]);
                        break;

                    case 'delete':
                        $affected = CrisisKeyword::whereIn('id', $keywordIds)->count();
                        CrisisKeyword::whereIn('id', $keywordIds)->delete();
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
                ], "Successfully applied '{$action}' to {$affected} keywords");

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Bulk crisis keyword action failed', [
                'action' => $request->input('action'),
                'error' => $e->getMessage(),
            ]);
            
            return $this->handleException($e, 'Bulk crisis keyword action');
        }
    }

    /**
     * Test crisis detection against text
     */
    public function testDetection(Request $request): JsonResponse
    {
        try {
            Log::info('=== TESTING CRISIS DETECTION ===', [
                'user_id' => $request->user()->id,
            ]);

            $validator = Validator::make($request->all(), [
                'text' => 'required|string|max:5000',
                'category_id' => 'nullable|exists:ticket_categories,id',
            ], [
                'text.required' => 'Text to test is required',
                'text.max' => 'Text cannot exceed 5000 characters',
                'category_id.exists' => 'Invalid category selected',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $text = $request->text;
            $categoryId = $request->category_id;

            // Run detection test
            $testResult = CrisisKeyword::testDetection($text, $categoryId);

            // Get detailed breakdown
            $detectionBreakdown = [
                'input' => [
                    'text' => $text,
                    'text_length' => strlen($text),
                    'word_count' => str_word_count($text),
                    'category_id' => $categoryId,
                    'category_name' => $categoryId ? TicketCategory::find($categoryId)?->name : 'All Categories',
                ],
                'detection_results' => $testResult,
                'keywords_tested' => CrisisKeyword::active()
                    ->forCategory($categoryId)
                    ->count(),
                'recommendation_details' => [
                    'auto_flag_crisis' => $testResult['is_crisis'],
                    'suggested_priority' => $testResult['is_crisis'] ? 'Urgent' : 'Medium',
                    'immediate_notification' => $testResult['crisis_score'] >= 1000,
                    'counselor_assignment' => $testResult['is_crisis'] ? 'Auto-assign to crisis specialist' : 'Normal assignment',
                ],
                'severity_breakdown' => [],
            ];

            // Add severity breakdown
            $severityGroups = collect($testResult['detected_keywords'])->groupBy('severity_level');
            foreach (['critical', 'high', 'medium', 'low'] as $severity) {
                $keywords = $severityGroups->get($severity, collect());
                $detectionBreakdown['severity_breakdown'][$severity] = [
                    'count' => $keywords->count(),
                    'keywords' => $keywords->pluck('keyword')->toArray(),
                    'total_weight' => $keywords->sum('severity_weight'),
                ];
            }

            Log::info('✅ Crisis detection test completed', [
                'is_crisis' => $testResult['is_crisis'],
                'crisis_score' => $testResult['crisis_score'],
                'detected_count' => count($testResult['detected_keywords']),
            ]);

            return $this->successResponse($detectionBreakdown, 'Crisis detection test completed');

        } catch (Exception $e) {
            Log::error('❌ Crisis detection test failed', [
                'error' => $e->getMessage(),
            ]);
            
            return $this->handleException($e, 'Crisis detection test');
        }
    }

    /**
     * Import crisis keywords from CSV or predefined sets
     */
    public function import(Request $request): JsonResponse
    {
        try {
            Log::info('=== IMPORTING CRISIS KEYWORDS ===', [
                'user_id' => $request->user()->id,
                'import_type' => $request->input('import_type'),
            ]);

            $validator = Validator::make($request->all(), [
                'import_type' => 'required|in:csv,predefined',
                'csv_file' => 'required_if:import_type,csv|file|mimes:csv,txt|max:2048',
                'predefined_set' => 'required_if:import_type,predefined|in:mental_health,suicide_prevention,self_harm,general_crisis',
                'category_id' => 'nullable|exists:ticket_categories,id',
                'default_severity' => 'required|in:low,medium,high,critical',
                'overwrite_existing' => 'boolean',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $importedCount = 0;
            $errors = [];

            DB::beginTransaction();

            try {
                if ($request->import_type === 'csv') {
                    // Handle CSV import
                    $importedCount = $this->importFromCSV($request, $errors);
                } else {
                    // Handle predefined set import
                    $importedCount = $this->importPredefinedSet($request, $errors);
                }

                DB::commit();

                Log::info('✅ Crisis keywords imported successfully', [
                    'imported_count' => $importedCount,
                    'error_count' => count($errors),
                ]);

                return $this->successResponse([
                    'imported_count' => $importedCount,
                    'errors' => $errors,
                    'total_processed' => $importedCount + count($errors),
                ], "Successfully imported {$importedCount} crisis keywords");

            } catch (Exception $dbError) {
                DB::rollBack();
                throw $dbError;
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('🚨 Crisis keywords import failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->handleException($e, 'Crisis keywords import');
        }
    }

    /**
     * Export crisis keywords
     */
    public function export(Request $request): JsonResponse
    {
        try {
            Log::info('=== EXPORTING CRISIS KEYWORDS ===', [
                'user_id' => $request->user()->id,
                'filters' => $request->only(['category_id', 'severity_level', 'format']),
            ]);

            $validator = Validator::make($request->all(), [
                'format' => 'sometimes|in:csv,json',
                'category_id' => 'sometimes|exists:ticket_categories,id',
                'severity_level' => 'sometimes|in:low,medium,high,critical',
                'include_stats' => 'boolean',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $query = CrisisKeyword::with(['category:id,name']);

            // Apply filters
            if ($request->has('category_id')) {
                if ($request->category_id === 'global') {
                    $query->whereNull('category_id');
                } else {
                    $query->where('category_id', $request->category_id);
                }
            }

            if ($request->has('severity_level')) {
                $query->where('severity_level', $request->severity_level);
            }

            $keywords = $query->orderBy('severity_level')
                ->orderBy('keyword')
                ->get();

            // Prepare export data
            $exportData = $keywords->map(function ($keyword) use ($request) {
                $data = [
                    'keyword' => $keyword->keyword,
                    'severity_level' => $keyword->severity_level,
                    'category' => $keyword->category?->name ?? 'Global',
                    'is_active' => $keyword->is_active ? 'Yes' : 'No',
                    'exact_match' => $keyword->exact_match ? 'Yes' : 'No',
                    'case_sensitive' => $keyword->case_sensitive ? 'Yes' : 'No',
                ];

                if ($request->boolean('include_stats')) {
                    $data['trigger_count'] = $keyword->trigger_count;
                    $data['last_triggered_at'] = $keyword->last_triggered_at?->format('Y-m-d H:i:s') ?? '';
                    $data['created_at'] = $keyword->created_at->format('Y-m-d H:i:s');
                }

                return $data;
            });

            $format = $request->get('format', 'csv');
            $filename = "crisis_keywords_export_" . now()->format('Y-m-d_H-i-s') . ".{$format}";

            Log::info("✅ Exporting {$keywords->count()} crisis keywords in {$format} format");

            return $this->successResponse([
                'keywords' => $exportData,
                'filename' => $filename,
                'format' => $format,
                'count' => $keywords->count(),
                'exported_at' => now()->toISOString(),
                'filters_applied' => $request->only(['category_id', 'severity_level']),
            ], "Successfully exported {$keywords->count()} crisis keywords");

        } catch (Exception $e) {
            Log::error('🚨 Crisis keywords export failed', [
                'error' => $e->getMessage(),
            ]);
            
            return $this->handleException($e, 'Crisis keywords export');
        }
    }

    /**
     * Get crisis detection statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            Log::info('=== FETCHING CRISIS DETECTION STATISTICS ===');

            $timeframe = $request->get('timeframe', 30); // days
            $startDate = now()->subDays($timeframe);

            $stats = [
                'overview' => [
                    'total_keywords' => CrisisKeyword::count(),
                    'active_keywords' => CrisisKeyword::where('is_active', true)->count(),
                    'global_keywords' => CrisisKeyword::whereNull('category_id')->count(),
                    'category_specific' => CrisisKeyword::whereNotNull('category_id')->count(),
                ],
                'by_severity' => CrisisKeyword::selectRaw('severity_level, count(*) as count, sum(trigger_count) as total_triggers')
                    ->groupBy('severity_level')
                    ->get()
                    ->keyBy('severity_level'),
                'trigger_activity' => [
                    'total_triggers' => CrisisKeyword::sum('trigger_count'),
                    'recent_triggers' => CrisisKeyword::where('last_triggered_at', '>=', $startDate)->sum('trigger_count'),
                    'most_triggered' => CrisisKeyword::orderByDesc('trigger_count')
                        ->take(10)
                        ->get(['keyword', 'severity_level', 'trigger_count', 'last_triggered_at']),
                    'least_triggered' => CrisisKeyword::where('trigger_count', 0)
                        ->orWhere('last_triggered_at', '<', now()->subMonths(6))
                        ->count(),
                ],
                'by_category' => TicketCategory::withCount([
                    'crisisKeywords',
                    'crisisKeywords as active_keywords' => function ($query) {
                        $query->where('is_active', true);
                    }
                ])->get()->map(function ($category) {
                    return [
                        'category_name' => $category->name,
                        'total_keywords' => $category->crisis_keywords_count,
                        'active_keywords' => $category->active_keywords,
                        'crisis_detection_enabled' => $category->crisis_detection_enabled,
                    ];
                }),
                'detection_effectiveness' => [
                    'keywords_with_triggers' => CrisisKeyword::where('trigger_count', '>', 0)->count(),
                    'unused_keywords' => CrisisKeyword::where('trigger_count', 0)->count(),
                    'avg_triggers_per_keyword' => CrisisKeyword::avg('trigger_count'),
                    'detection_rate' => $this->calculateDetectionRate($timeframe),
                ],
            ];

            return $this->successResponse($stats, 'Crisis detection statistics retrieved successfully');

        } catch (Exception $e) {
            Log::error('❌ Crisis detection stats fetch failed', [
                'error' => $e->getMessage(),
            ]);
            
            return $this->handleException($e, 'Crisis detection stats fetch');
        }
    }

    /**
     * Private helper methods
     */
    private function importFromCSV(Request $request, array &$errors): int
    {
        // CSV import logic would go here
        // For now, return 0 as placeholder
        return 0;
    }

    private function importPredefinedSet(Request $request, array &$errors): int
    {
        $predefinedSets = [
            'mental_health' => [
                ['keyword' => 'depression', 'severity' => 'medium'],
                ['keyword' => 'anxiety', 'severity' => 'medium'],
                ['keyword' => 'panic attack', 'severity' => 'high'],
                ['keyword' => 'mental health crisis', 'severity' => 'high'],
            ],
            'suicide_prevention' => [
                ['keyword' => 'suicide', 'severity' => 'critical'],
                ['keyword' => 'kill myself', 'severity' => 'critical'],
                ['keyword' => 'end my life', 'severity' => 'critical'],
                ['keyword' => 'want to die', 'severity' => 'critical'],
                ['keyword' => 'suicidal thoughts', 'severity' => 'critical'],
            ],
            'self_harm' => [
                ['keyword' => 'self-harm', 'severity' => 'high'],
                ['keyword' => 'cutting', 'severity' => 'high'],
                ['keyword' => 'hurt myself', 'severity' => 'high'],
                ['keyword' => 'self-injury', 'severity' => 'high'],
            ],
            'general_crisis' => [
                ['keyword' => 'emergency', 'severity' => 'high'],
                ['keyword' => 'crisis', 'severity' => 'high'],
                ['keyword' => 'urgent help', 'severity' => 'high'],
                ['keyword' => 'immediate assistance', 'severity' => 'high'],
            ],
        ];

        $set = $predefinedSets[$request->predefined_set] ?? [];
        $importedCount = 0;

        foreach ($set as $keywordData) {
            try {
                // Check if keyword already exists
                $existing = CrisisKeyword::where('keyword', $keywordData['keyword'])
                    ->where('category_id', $request->category_id)
                    ->first();

                if ($existing && !$request->boolean('overwrite_existing')) {
                    $errors[] = "Keyword '{$keywordData['keyword']}' already exists";
                    continue;
                }

                if ($existing && $request->boolean('overwrite_existing')) {
                    $existing->update([
                        'severity_level' => $keywordData['severity'],
                        'updated_by' => $request->user()->id,
                    ]);
                } else {
                    CrisisKeyword::create([
                        'keyword' => $keywordData['keyword'],
                        'severity_level' => $keywordData['severity'],
                        'category_id' => $request->category_id,
                        'is_active' => true,
                        'exact_match' => false,
                        'case_sensitive' => false,
                        'trigger_count' => 0,
                        'created_by' => $request->user()->id,
                    ]);
                }

                $importedCount++;

            } catch (Exception $e) {
                $errors[] = "Failed to import '{$keywordData['keyword']}': " . $e->getMessage();
            }
        }

        return $importedCount;
    }

    private function calculateDetectionRate(int $days): array
    {
        // This would calculate how effective the detection is
        // For now, return placeholder data
        return [
            'tickets_with_crisis_detected' => 0,
            'total_tickets_processed' => 0,
            'detection_rate_percentage' => 0,
        ];
    }
}