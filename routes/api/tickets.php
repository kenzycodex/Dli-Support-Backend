<?php
// routes/api/tickets.php - Ticket Management Routes

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketController;

/*
|--------------------------------------------------------------------------
| Ticket Management API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('tickets')->group(function () {
    
    // ========== ALL AUTHENTICATED USERS ==========
    Route::get('/', [TicketController::class, 'index'])
         ->middleware('throttle:200,1');
    
    Route::get('/{ticket}', [TicketController::class, 'show'])
         ->middleware('throttle:300,1');
    
    Route::get('/options', [TicketController::class, 'getOptions'])
         ->middleware('throttle:60,1');
    
    Route::get('/attachments/{attachment}/download', [TicketController::class, 'downloadAttachment'])
         ->middleware('throttle:120,1');
    
    Route::get('/analytics', [TicketController::class, 'getAnalytics'])
         ->middleware('throttle:30,1');

    Route::get('/{ticket}/analytics', [TicketController::class, 'getTicketAnalytics'])
         ->middleware('throttle:60,1');

    // ========== STUDENTS + ADMIN ==========
    Route::post('/', [TicketController::class, 'store'])
         ->middleware(['role:student,admin', 'throttle:30,1']);

    // ========== STUDENTS + STAFF ==========
    Route::post('/{ticket}/responses', [TicketController::class, 'addResponse'])
         ->middleware('throttle:100,1');

    // ========== STAFF ONLY (Counselors, Advisors, Admins) ==========
    Route::middleware('role:counselor,advisor,admin')->group(function () {
        Route::patch('/{ticket}', [TicketController::class, 'update'])
             ->middleware('throttle:200,1');
        
        Route::get('/{ticket}/available-staff', [TicketController::class, 'getAvailableStaff'])
             ->middleware('throttle:60,1');
        
        Route::post('/{ticket}/tags', [TicketController::class, 'manageTags'])
             ->middleware('throttle:100,1');
    });

    // ========== ADMIN ONLY ==========
    Route::middleware('role:admin')->group(function () {
        Route::post('/{ticket}/assign', [TicketController::class, 'assign'])
             ->middleware('throttle:100,1');
        
        // Unified delete endpoint - supports both DELETE method and POST with reason
        Route::delete('/{ticket}', [TicketController::class, 'destroy'])
             ->middleware('throttle:20,1');
        
        Route::post('/{ticket}/delete', [TicketController::class, 'destroy'])
             ->middleware('throttle:20,1');
        
        Route::post('/{ticket}/reassign', [TicketController::class, 'assign'])
             ->middleware('throttle:100,1');
    });
});