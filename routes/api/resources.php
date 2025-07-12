<?php
// routes/api/resources.php - Resource Library Routes

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\Admin\AdminResourceController;

/*
|--------------------------------------------------------------------------
| Resources Routes (All authenticated users)
|--------------------------------------------------------------------------
*/

Route::prefix('resources')->group(function () {
    
    // Get resource categories
    Route::get('/categories', [ResourceController::class, 'getCategories'])
         ->middleware('throttle:60,1');
    
    // Get resources with filtering
    Route::get('/', [ResourceController::class, 'getResources'])
         ->middleware('throttle:120,1');
    
    // Get single resource
    Route::get('/{resource}', [ResourceController::class, 'showResource'])
         ->middleware('throttle:200,1');
    
    // Access resource (get URL and track usage)
    Route::post('/{resource}/access', [ResourceController::class, 'accessResource'])
         ->middleware('throttle:100,1');
    
    // Provide feedback/rating on resource
    Route::post('/{resource}/feedback', [ResourceController::class, 'provideFeedback'])
         ->middleware('throttle:30,1');
    
    // Bookmark/unbookmark resource
    Route::post('/{resource}/bookmark', [ResourceController::class, 'bookmarkResource'])
         ->middleware('throttle:60,1');
    
    // Get user's bookmarks
    Route::get('/user/bookmarks', [ResourceController::class, 'getBookmarks'])
         ->middleware('throttle:60,1');
    
    // Get resource statistics
    Route::get('/stats', [ResourceController::class, 'getStats'])
         ->middleware('throttle:30,1');
    
    // Get resource options for forms
    Route::get('/options', [ResourceController::class, 'getOptions'])
         ->middleware('throttle:30,1');
});

/*
|--------------------------------------------------------------------------
| Admin Resources Routes (Admin only)
|--------------------------------------------------------------------------
*/

Route::middleware('role:admin')->prefix('admin/resources')->group(function () {
    
    // Resource Categories Management
    Route::get('/categories', [AdminResourceController::class, 'getCategories'])
         ->middleware('throttle:60,1');
    
    Route::post('/categories', [AdminResourceController::class, 'storeCategory'])
         ->middleware('throttle:20,1');
    
    Route::put('/categories/{resourceCategory}', [AdminResourceController::class, 'updateCategory'])
         ->middleware('throttle:30,1');
    
    Route::delete('/categories/{resourceCategory}', [AdminResourceController::class, 'destroyCategory'])
         ->middleware('throttle:10,1');
    
    // Resources Management
    Route::get('/', [AdminResourceController::class, 'getResources'])
         ->middleware('throttle:100,1');
    
    Route::post('/', [AdminResourceController::class, 'storeResource'])
         ->middleware('throttle:20,1');
    
    Route::put('/{resource}', [AdminResourceController::class, 'updateResource'])
         ->middleware('throttle:30,1');
    
    Route::delete('/{resource}', [AdminResourceController::class, 'destroyResource'])
         ->middleware('throttle:10,1');
    
    // Bulk resource actions
    Route::post('/bulk-action', [AdminResourceController::class, 'bulkActionResources'])
         ->middleware('throttle:10,1');
    
    // Resource analytics
    Route::get('/analytics', [AdminResourceController::class, 'getAnalytics'])
         ->middleware('throttle:30,1');
    
    // Export resources
    Route::get('/export', [AdminResourceController::class, 'exportResources'])
         ->middleware('throttle:5,1');
});