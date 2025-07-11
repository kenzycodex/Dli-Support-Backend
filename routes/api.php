<?php
// routes/api.php - Main API Routes (Refactored)

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for Student Support Platform
|--------------------------------------------------------------------------
*/

// ==========================================
// PUBLIC ROUTES (No Authentication Required)
// ==========================================
Route::prefix('auth')->group(function () {
    Route::post('/login', [\App\Http\Controllers\Auth\AuthController::class, 'login']);
    Route::post('/demo-login', [\App\Http\Controllers\Auth\AuthController::class, 'demoLogin']);
});

// Health check route (no rate limiting for monitoring)
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Student Support Platform API is running',
        'timestamp' => now(),
        'version' => '2.1.0',
        'features' => [
            'role_based_access' => 'active',
            'authentication' => 'active',
            'notifications' => 'active',
            'ticket_management' => 'active',
            'user_management' => 'active',
            'help_faqs' => 'active',
            'resources_library' => 'active',
            'content_management' => 'active',
            'analytics' => 'active',
            'export_functionality' => 'active',
        ],
        'performance' => [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => round((microtime(true) - LARAVEL_START) * 1000, 2) . 'ms'
        ]
    ]);
});

// ==========================================
// PROTECTED ROUTES WITH ROLE-BASED ACCESS
// ==========================================
Route::middleware(['auth:sanctum'])->group(function () {
    
    // Authentication routes
    require __DIR__ . '/api/auth.php';
    
    // Notification routes
    require __DIR__ . '/api/notifications.php';
    
    // Ticket management routes
    require __DIR__ . '/api/tickets.php';
    
    // Help & FAQ routes
    require __DIR__ . '/api/help.php';
    
    // Resources routes
    require __DIR__ . '/api/resources.php';
    
    // User management and profile routes
    require __DIR__ . '/api/users.php';
    
    // Role-specific dashboard routes
    require __DIR__ . '/api/dashboards.php';
    
    // Admin-specific routes
    require __DIR__ . '/api/admin.php';
});

// ==========================================
// DOCUMENTATION AND FALLBACK
// ==========================================
Route::get('/docs', function () {
    return response()->json([
        'success' => true,
        'message' => 'Student Support Platform API Documentation',
        'version' => '2.1.0',
        'endpoints' => [
            'authentication' => 'POST /api/auth/login, /logout, /demo-login',
            'tickets' => 'GET|POST|PATCH|DELETE /api/tickets/*',
            'help_system' => 'GET|POST /api/help/*',
            'resources' => 'GET|POST /api/resources/*',
            'notifications' => 'GET|POST|PATCH|DELETE /api/notifications/*',
            'user_management' => 'GET|POST|PUT|DELETE /api/admin/users/*',
            'dashboards' => 'GET /api/{role}/dashboard',
        ],
        'roles' => [
            'student' => 'Can create tickets, access help content',
            'counselor' => 'Can handle mental health tickets, suggest content',
            'advisor' => 'Can handle academic tickets',
            'admin' => 'Full system access and management',
        ],
        'documentation' => 'Visit /api/health for system status'
    ]);
})->middleware('throttle:20,1');

// Fallback route for 404
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found',
        'suggestion' => 'Check /api/docs for available endpoints'
    ], 404);
});