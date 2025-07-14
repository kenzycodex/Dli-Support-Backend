<?php
// routes/api/notifications.php - Notification Routes

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationController;

/*
|--------------------------------------------------------------------------
| Notification API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('notifications')->group(function () {
    // All authenticated users
    Route::get('/unread-count', [NotificationController::class, 'getUnreadCount'])
         ->middleware('throttle:120,1');
    
    Route::get('/', [NotificationController::class, 'index'])
         ->middleware('throttle:120,1');
    
    Route::get('/options', [NotificationController::class, 'getOptions'])
         ->middleware('throttle:30,1');
    
    Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])
         ->middleware('throttle:30,1');
    
    Route::post('/bulk-action', [NotificationController::class, 'bulkAction'])
         ->middleware('throttle:60,1');
    
    Route::patch('/{notification}/read', [NotificationController::class, 'markAsRead'])
         ->middleware('throttle:300,1');
    
    Route::patch('/{notification}/unread', [NotificationController::class, 'markAsUnread'])
         ->middleware('throttle:300,1');
    
    Route::delete('/{notification}', [NotificationController::class, 'destroy'])
         ->middleware('throttle:200,1');
    
    // Admin only notification routes
    Route::middleware('role:admin')->group(function () {
        Route::post('/', [NotificationController::class, 'store'])
             ->middleware('throttle:10,1');
        
        Route::get('/stats', [NotificationController::class, 'getStats'])
             ->middleware('throttle:20,1');
    });
});