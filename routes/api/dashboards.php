<?php
// routes/api/dashboards.php - Role-Specific Dashboard Routes

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Role-Specific Dashboard API Routes
|--------------------------------------------------------------------------
*/

// ========== STUDENT DASHBOARD ==========
Route::middleware('role:student')->prefix('student')->group(function () {
    
    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();
        $ticketStats = \App\Models\Ticket::getStatsForUser($user);
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'ticket_stats' => $ticketStats,
                'permissions' => [
                    'can_create_tickets' => true,
                    'can_view_own_tickets' => true,
                    'can_respond_to_tickets' => true,
                    'can_close_own_tickets' => false,
                ]
            ]
        ]);
    })->middleware('throttle:60,1');

    Route::get('/help-dashboard', function (Request $request) {
        $user = $request->user();
        
        $data = [
            'popular_faqs' => \App\Models\FAQ::published()
                ->orderBy('view_count', 'desc')
                ->take(5)
								->get(['id', 'question', 'view_count']),
            
            'featured_faqs' => \App\Models\FAQ::published()
                ->featured()
                ->with(['category:id,name,slug,color'])
                ->take(3)
                ->get(['id', 'question', 'category_id']),
            
            'recent_faqs' => \App\Models\FAQ::published()
                ->orderBy('published_at', 'desc')
                ->take(5)
                ->get(['id', 'question', 'published_at', 'view_count']),
            
            'help_stats' => [
                'total_faqs' => \App\Models\FAQ::published()->count(),
                'total_categories' => \App\Models\HelpCategory::active()->count(),
            ]
        ];
        
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    })->middleware('throttle:60,1');

    // Resource dashboard for counselors
    Route::get('/resource-dashboard', function (Request $request) {
        $user = $request->user();
        
        $data = [
            'crisis_resources' => \App\Models\Resource::published()
                ->whereHas('category', function ($query) {
                    $query->where('slug', 'crisis-resources');
                })
                ->orderBy('rating', 'desc')
                ->take(5)
                ->get(['id', 'title', 'type', 'rating']),
            
            'mental_health_resources' => \App\Models\Resource::published()
                ->whereHas('category', function ($query) {
                    $query->where('slug', 'like', '%mental%');
                })
                ->orderBy('access_count', 'desc')
                ->take(5)
                ->get(['id', 'title', 'type', 'access_count']),
            
            'resource_stats' => [
                'total_resources' => \App\Models\Resource::published()->count(),
                'crisis_resources' => \App\Models\Resource::published()
                    ->whereHas('category', function ($query) {
                        $query->where('slug', 'crisis-resources');
                    })->count(),
                'mental_health_resources' => \App\Models\Resource::published()
                    ->whereHas('category', function ($query) {
                        $query->where('slug', 'like', '%mental%');
                    })->count(),
            ]
        ];
        
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    })->middleware('throttle:60,1');

    // Resource dashboard for students
    Route::get('/resource-dashboard', function (Request $request) {
        $user = $request->user();
        
        $data = [
            'featured_resources' => \App\Models\Resource::published()
                ->featured()
                ->with('category:id,name,color')
                ->take(3)
                ->get(['id', 'title', 'type', 'category_id', 'rating']),
            
            'recent_bookmarks' => \Illuminate\Support\Facades\DB::table('user_bookmarks')
                ->join('resources', 'user_bookmarks.bookmarkable_id', '=', 'resources.id')
                ->where('user_bookmarks.user_id', $user->id)
                ->where('user_bookmarks.bookmarkable_type', 'App\\Models\\Resource')
                ->orderBy('user_bookmarks.created_at', 'desc')
                ->take(5)
                ->get(['resources.id', 'resources.title', 'resources.type']),
            
            'popular_resources' => \App\Models\Resource::published()
                ->orderBy('access_count', 'desc')
                ->take(5)
                ->get(['id', 'title', 'type', 'access_count']),
            
            'resource_stats' => [
                'total_resources' => \App\Models\Resource::published()->count(),
                'user_bookmarks' => \Illuminate\Support\Facades\DB::table('user_bookmarks')
                    ->where('user_id', $user->id)
                    ->where('bookmarkable_type', 'App\\Models\\Resource')
                    ->count(),
                'categories_count' => \App\Models\ResourceCategory::active()->count(),
            ]
        ];
        
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    })->middleware('throttle:60,1');

    Route::get('/tickets', [\App\Http\Controllers\TicketController::class, 'index'])
         ->middleware('throttle:100,1');
    
    Route::get('/stats', function (Request $request) {
        $user = $request->user();
        $stats = \App\Models\Ticket::getStatsForUser($user);
        
        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    })->middleware('throttle:30,1');
});

// ========== COUNSELOR DASHBOARD ==========
Route::middleware('role:counselor')->prefix('counselor')->group(function () {
    
    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();
        $ticketStats = \App\Models\Ticket::getStatsForUser($user);
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'ticket_stats' => $ticketStats,
                'assigned_tickets' => $user->assignedTickets()
                                          ->whereIn('status', ['Open', 'In Progress'])
                                          ->count(),
                'crisis_tickets' => $user->assignedTickets()
                                        ->where('crisis_flag', true)
                                        ->count(),
                'permissions' => [
                    'can_view_assigned_tickets' => true,
                    'can_modify_tickets' => true,
                    'can_add_internal_notes' => true,
                    'can_reassign_tickets' => false,
                    'specialization' => ['mental-health', 'crisis']
                ]
            ]
        ]);
    })->middleware('throttle:60,1');

    Route::get('/help-dashboard', function (Request $request) {
        $user = $request->user();
        
        $data = [
            'helpful_faqs' => \App\Models\FAQ::published()
                ->orderBy('helpful_count', 'desc')
                ->take(5)
                ->get(['id', 'question', 'helpful_count', 'category_id']),
            
            'pending_suggestions' => \App\Models\FAQ::where('created_by', $user->id)
                ->where('is_published', false)
                ->count(),
            
            'approved_suggestions' => \App\Models\FAQ::where('created_by', $user->id)
                ->where('is_published', true)
                ->count(),
            
            'content_stats' => [
                'total_faqs' => \App\Models\FAQ::published()->count(),
                'mental_health_faqs' => \App\Models\FAQ::published()
                    ->whereHas('category', function ($query) {
                        $query->where('slug', 'like', '%mental%')
                              ->orWhere('slug', 'like', '%crisis%');
                    })->count(),
                'recent_feedback' => \App\Models\FAQFeedback::where('created_at', '>=', now()->subDays(7))->count(),
            ]
        ];
        
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    })->middleware('throttle:60,1');

    Route::get('/assigned-tickets', function (Request $request) {
        $user = $request->user();
        $tickets = \App\Models\Ticket::forCounselor($user->id)
                                    ->with(['user', 'responses'])
                                    ->orderBy('updated_at', 'desc')
                                    ->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $tickets
        ]);
    })->middleware('throttle:100,1');
    
    Route::get('/crisis-tickets', function (Request $request) {
        $user = $request->user();
        $crisisTickets = \App\Models\Ticket::forCounselor($user->id)
                                          ->crisis()
                                          ->with(['user'])
                                          ->orderBy('created_at', 'desc')
                                          ->get();
        
        return response()->json([
            'success' => true,
            'data' => $crisisTickets
        ]);
    })->middleware('throttle:60,1');
});

// ========== ADVISOR DASHBOARD ==========
Route::middleware('role:advisor')->prefix('advisor')->group(function () {
    
    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();
        $ticketStats = \App\Models\Ticket::getStatsForUser($user);
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'ticket_stats' => $ticketStats,
                'assigned_tickets' => $user->assignedTickets()
                                          ->whereIn('status', ['Open', 'In Progress'])
                                          ->count(),
                'permissions' => [
                    'can_view_assigned_tickets' => true,
                    'can_modify_tickets' => true,
                    'can_add_internal_notes' => true,
                    'can_reassign_tickets' => false,
                    'specialization' => ['academic', 'general', 'technical']
                ]
            ]
        ]);
    })->middleware('throttle:60,1');

    Route::get('/assigned-tickets', function (Request $request) {
        $user = $request->user();
        $tickets = \App\Models\Ticket::forAdvisor($user->id)
                                    ->with(['user', 'responses'])
                                    ->orderBy('updated_at', 'desc')
                                    ->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $tickets
        ]);
    })->middleware('throttle:100,1');
});

// ========== STAFF DASHBOARD (Counselors + Advisors + Admins) ==========
Route::middleware('role:counselor,advisor,admin')->prefix('staff')->group(function () {
    
    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();
        $ticketStats = \App\Models\Ticket::getStatsForUser($user);
        
        return response()->json([
            'success' => true,
            'message' => 'Staff dashboard access granted',
            'data' => [
                'user' => $user,
                'ticket_stats' => $ticketStats,
                'permissions' => [
                    'can_view_assigned_tickets' => true,
                    'can_modify_tickets' => true,
                    'can_add_internal_notes' => true,
                    'can_manage_tags' => true,
                    'can_reassign' => $user->isAdmin(),
                ]
            ]
        ]);
    })->middleware('throttle:60,1');

    Route::get('/assigned-tickets', function (Request $request) {
        $user = $request->user();
        $tickets = $user->assignedTickets()
                       ->with(['user', 'responses'])
                       ->orderBy('updated_at', 'desc')
                       ->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $tickets
        ]);
    })->middleware('throttle:100,1');
    
    Route::get('/stats', function (Request $request) {
        $user = $request->user();
        $stats = \App\Models\Ticket::getStatsForUser($user);
        
        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    })->middleware('throttle:30,1');

    Route::get('/workload', function (Request $request) {
        $user = $request->user();
        $assignedTickets = $user->assignedTickets();
        
        $workload = [
            'total_assigned' => $assignedTickets->count(),
            'open_tickets' => (clone $assignedTickets)->where('status', 'Open')->count(),
            'in_progress' => (clone $assignedTickets)->where('status', 'In Progress')->count(),
            'high_priority' => (clone $assignedTickets)->where('priority', 'High')->count(),
            'crisis_cases' => (clone $assignedTickets)->where('crisis_flag', true)->count(),
            'this_week' => (clone $assignedTickets)->where('created_at', '>=', now()->startOfWeek())->count(),
            'avg_response_time' => '2.3 hours',
        ];
        
        return response()->json([
            'success' => true,
            'data' => $workload
        ]);
    })->middleware('throttle:30,1');
});