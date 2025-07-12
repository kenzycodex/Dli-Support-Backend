<?php
// routes/api/help.php - Help & FAQ Routes

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\Admin\AdminHelpController;

/*
|--------------------------------------------------------------------------
| Help & FAQ Routes (All authenticated users)
|--------------------------------------------------------------------------
*/

Route::prefix('help')->group(function () {
    
    // Get help categories
    Route::get('/categories', [HelpController::class, 'getCategories'])
         ->middleware('throttle:60,1');
    
    // Get FAQs with filtering
    Route::get('/faqs', [HelpController::class, 'getFAQs'])
         ->middleware('throttle:120,1');
    
    // Get single FAQ
    Route::get('/faqs/{faq}', [HelpController::class, 'showFAQ'])
         ->middleware('throttle:200,1');
    
    // Provide feedback on FAQ
    Route::post('/faqs/{faq}/feedback', [HelpController::class, 'provideFeedback'])
         ->middleware('throttle:30,1');
    
    // Suggest content (counselors and admins only)
    Route::post('/suggest-content', [HelpController::class, 'suggestContent'])
         ->middleware(['role:counselor,admin', 'throttle:10,1']);
    
    // Get help statistics
    Route::get('/stats', [HelpController::class, 'getStats'])
         ->middleware('throttle:30,1');
});

/*
|--------------------------------------------------------------------------
| Admin Help & FAQ Routes (Admin only)
|--------------------------------------------------------------------------
*/

Route::middleware('role:admin')->prefix('admin/help')->group(function () {
    
    // Help Categories Management
    Route::get('/categories', [AdminHelpController::class, 'getCategories'])
         ->middleware('throttle:60,1');
    
    Route::post('/categories', [AdminHelpController::class, 'storeCategory'])
         ->middleware('throttle:20,1');
    
    Route::put('/categories/{helpCategory}', [AdminHelpController::class, 'updateCategory'])
         ->middleware('throttle:30,1');
    
    Route::delete('/categories/{helpCategory}', [AdminHelpController::class, 'destroyCategory'])
         ->middleware('throttle:10,1');
    
    // FAQs Management
    Route::get('/faqs', [AdminHelpController::class, 'getFAQs'])
         ->middleware('throttle:100,1');
    
    Route::post('/faqs', [AdminHelpController::class, 'storeFAQ'])
         ->middleware('throttle:20,1');
    
    Route::put('/faqs/{faq}', [AdminHelpController::class, 'updateFAQ'])
         ->middleware('throttle:30,1');
    
    Route::delete('/faqs/{faq}', [AdminHelpController::class, 'destroyFAQ'])
         ->middleware('throttle:10,1');
    
    // Bulk FAQ actions
    Route::post('/faqs/bulk-action', [AdminHelpController::class, 'bulkActionFAQs'])
         ->middleware('throttle:10,1');
    
    // Help analytics
    Route::get('/analytics', [AdminHelpController::class, 'getAnalytics'])
         ->middleware('throttle:30,1');
});

/*
|--------------------------------------------------------------------------
| Role-Specific Help Routes
|--------------------------------------------------------------------------
*/

// Student-specific help routes
Route::middleware('role:student')->prefix('student')->group(function () {
    
    // Student help dashboard
    Route::get('/help-dashboard', function (Request $request) {
        $user = $request->user();
        
        // Get popular FAQs
        $popularFAQs = \App\Models\FAQ::published()
            ->orderBy('view_count', 'desc')
            ->take(5)
            ->get(['id', 'question', 'view_count']);
        
        // Get featured resources
        $featuredResources = \App\Models\Resource::published()
            ->featured()
            ->with('category:id,name,color')
            ->take(3)
            ->get(['id', 'title', 'type', 'category_id', 'rating']);
        
        // Get user's recent bookmarks
        $recentBookmarks = DB::table('user_bookmarks')
            ->join('resources', 'user_bookmarks.resource_id', '=', 'resources.id')
            ->where('user_bookmarks.user_id', $user->id)
            ->orderBy('user_bookmarks.created_at', 'desc')
            ->take(3)
            ->get(['resources.id', 'resources.title', 'resources.type']);
        
        return response()->json([
            'success' => true,
            'data' => [
                'popular_faqs' => $popularFAQs,
                'featured_resources' => $featuredResources,
                'recent_bookmarks' => $recentBookmarks,
                'help_stats' => [
                    'total_faqs' => \App\Models\FAQ::published()->count(),
                    'total_resources' => \App\Models\Resource::published()->count(),
                    'user_bookmarks' => DB::table('user_bookmarks')->where('user_id', $user->id)->count(),
                ]
            ]
        ]);
    })->middleware('throttle:60,1');
});

// Counselor-specific help routes
Route::middleware('role:counselor')->prefix('counselor')->group(function () {
    
    // Counselor help dashboard
    Route::get('/help-dashboard', function (Request $request) {
        $user = $request->user();
        
        // Get most helpful FAQs for reference
        $helpfulFAQs = \App\Models\FAQ::published()
            ->orderBy('helpful_count', 'desc')
            ->take(5)
            ->get(['id', 'question', 'helpful_count', 'category_id']);
        
        // Get crisis-related resources
        $crisisResources = \App\Models\Resource::published()
            ->whereHas('category', function ($query) {
                $query->where('slug', 'crisis-resources');
            })
            ->orderBy('rating', 'desc')
            ->take(5)
            ->get(['id', 'title', 'type', 'rating']);
        
        // Get pending content suggestions (if any by this counselor)
        $pendingSuggestions = \App\Models\FAQ::where('created_by', $user->id)
            ->where('is_published', false)
            ->count();
        
        return response()->json([
            'success' => true,
            'data' => [
                'helpful_faqs' => $helpfulFAQs,
                'crisis_resources' => $crisisResources,
                'pending_suggestions' => $pendingSuggestions,
                'help_stats' => [
                    'total_faqs' => \App\Models\FAQ::published()->count(),
                    'total_resources' => \App\Models\Resource::published()->count(),
                    'mental_health_resources' => \App\Models\Resource::published()
                        ->whereHas('category', function ($query) {
                            $query->where('slug', 'mental-health');
                        })->count(),
                ]
            ]
        ]);
    })->middleware('throttle:60,1');
    
    // Get suggested content by this counselor
    Route::get('/my-suggestions', function (Request $request) {
        $user = $request->user();
        
        $suggestions = \App\Models\FAQ::where('created_by', $user->id)
            ->where('is_published', false)
            ->with('category:id,name,color')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        return response()->json([
            'success' => true,
            'data' => [
                'suggestions' => $suggestions->items(),
                'pagination' => [
                    'current_page' => $suggestions->currentPage(),
                    'last_page' => $suggestions->lastPage(),
                    'per_page' => $suggestions->perPage(),
                    'total' => $suggestions->total(),
                ]
            ]
        ]);
    })->middleware('throttle:60,1');
});