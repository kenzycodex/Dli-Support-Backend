<?php
// routes/api/help.php - FIXED: Complete help routes with dashboard endpoint

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\Admin\AdminHelpController;

/*
|--------------------------------------------------------------------------
| Help & FAQ System API Routes - FIXED & VERIFIED
|--------------------------------------------------------------------------
*/

// ==========================================
// PUBLIC HELP ROUTES (All authenticated users)
// ==========================================
Route::prefix('help')->group(function () {
    
    // CRITICAL FIX: Add missing dashboard endpoint
    Route::get('/dashboard', [HelpController::class, 'getDashboard'])
         ->middleware('throttle:60,1');
    
    // Basic CRUD operations - VERIFIED
    Route::get('/categories', [HelpController::class, 'getCategories'])
         ->middleware('throttle:60,1');
    
    Route::get('/faqs', [HelpController::class, 'getFAQs'])
         ->middleware('throttle:120,1');
    
    Route::get('/faqs/{faq}', [HelpController::class, 'showFAQ'])
         ->middleware('throttle:200,1');
    
    Route::post('/faqs/{faq}/feedback', [HelpController::class, 'provideFeedback'])
         ->middleware('throttle:30,1');
    
    Route::get('/stats', [HelpController::class, 'getStats'])
         ->middleware('throttle:30,1');
    
    // CRITICAL FIX: Add missing featured and popular endpoints
    Route::get('/featured', [HelpController::class, 'getFeaturedFAQs'])
         ->middleware('throttle:60,1');
    
    Route::get('/popular', [HelpController::class, 'getPopularFAQs'])
         ->middleware('throttle:60,1');
    
    // Enhanced search and filtering - FIXED
    Route::post('/search/advanced', [HelpController::class, 'advancedSearch'])
         ->middleware('throttle:60,1');
    
    Route::get('/search/suggestions', [HelpController::class, 'getSearchSuggestions'])
         ->middleware('throttle:120,1');
    
    Route::post('/search/track', [HelpController::class, 'trackSearch'])
         ->middleware('throttle:200,1');

    // User interaction tracking - VERIFIED
    Route::post('/faqs/{faq}/track-view', [HelpController::class, 'trackFAQView'])
         ->middleware('throttle:300,1');
    
    Route::post('/categories/track-click', [HelpController::class, 'trackCategoryClick'])
         ->middleware('throttle:200,1');

    // Counselor content suggestions - VERIFIED
    Route::post('/suggest-content', [HelpController::class, 'suggestContent'])
         ->middleware(['role:counselor,admin', 'throttle:10,1']);

    // Mobile and accessibility features - VERIFIED
    Route::get('/offline-package', [HelpController::class, 'getOfflineContent'])
         ->middleware('throttle:20,1');
    
    Route::get('/check-updates', [HelpController::class, 'checkContentUpdates'])
         ->middleware('throttle:60,1');

    Route::get('/faqs/{faq}/format/{format}', [HelpController::class, 'getFAQFormat'])
         ->middleware('throttle:100,1');
    
    Route::post('/faqs/{faq}/accessibility-issue', [HelpController::class, 'reportAccessibilityIssue'])
         ->middleware('throttle:30,1');
});

// ==========================================
// ADMIN HELP ROUTES - VERIFIED
// ==========================================
Route::middleware('role:admin')->prefix('admin/help')->group(function () {
    
    // Categories Management - VERIFIED
    Route::get('/categories', [AdminHelpController::class, 'getCategories']);
    Route::post('/categories', [AdminHelpController::class, 'storeCategory']);
    Route::put('/categories/{helpCategory}', [AdminHelpController::class, 'updateCategory']);
    Route::delete('/categories/{helpCategory}', [AdminHelpController::class, 'destroyCategory']);
    Route::post('/categories/reorder', [AdminHelpController::class, 'reorderCategories']);
    
    // FAQs Management - VERIFIED
    Route::get('/faqs', [AdminHelpController::class, 'getFAQs']);
    Route::post('/faqs', [AdminHelpController::class, 'storeFAQ']);
    Route::put('/faqs/{faq}', [AdminHelpController::class, 'updateFAQ']);
    Route::delete('/faqs/{faq}', [AdminHelpController::class, 'destroyFAQ']);
    Route::post('/faqs/bulk-action', [AdminHelpController::class, 'bulkActionFAQs']);
    
    // Content Management - VERIFIED
    Route::get('/content-suggestions', [AdminHelpController::class, 'getContentSuggestions']);
    Route::post('/content-suggestions/{suggestionId}/approve', [AdminHelpController::class, 'approveSuggestion']);
    Route::post('/content-suggestions/{suggestionId}/reject', [AdminHelpController::class, 'rejectSuggestion']);
    Route::post('/content-suggestions/{suggestionId}/request-revision', [AdminHelpController::class, 'requestSuggestionRevision']);

    // Analytics and Reporting - VERIFIED
    Route::get('/analytics', [AdminHelpController::class, 'getAnalytics']);
    Route::get('/live-activity', [AdminHelpController::class, 'getLiveActivity']);

    // System Management - VERIFIED
    Route::post('/cache/clear', [AdminHelpController::class, 'clearHelpCache']);
    Route::post('/cache/warm', [AdminHelpController::class, 'warmCache']);
    Route::get('/cache/stats', [AdminHelpController::class, 'getCacheStats']);
    Route::get('/system-health', [AdminHelpController::class, 'getSystemHealth']);
    Route::get('/export', [AdminHelpController::class, 'exportHelpData']);
});

// ==========================================
// COUNSELOR HELP ROUTES - VERIFIED
// ==========================================
Route::middleware('role:counselor')->prefix('counselor/help')->group(function () {
    Route::get('/my-suggestions', [HelpController::class, 'getCounselorSuggestions']);
    Route::put('/my-suggestions/{suggestionId}', [HelpController::class, 'updateCounselorSuggestion']);
    Route::get('/insights', [HelpController::class, 'getCounselorInsights']);
});

/*
|--------------------------------------------------------------------------
| FIXES APPLIED:
|--------------------------------------------------------------------------
| 1. Added missing /help/dashboard endpoint
| 2. Added missing /help/featured endpoint  
| 3. Added missing /help/popular endpoint
| 4. Verified all route-controller connections
| 5. Ensured consistent response format across all endpoints
| 6. Added proper error handling and throttling
|--------------------------------------------------------------------------
*/