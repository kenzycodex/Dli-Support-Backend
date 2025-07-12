<?php
// routes/api/system.php - System Routes (Health, Documentation, Fallback)

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| System Routes - Health Check, Documentation, and Fallback
|--------------------------------------------------------------------------
*/

// Health check route (no rate limiting for monitoring)
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Role-Based Ticketing API is running',
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
            'rate_limiting' => 'active',
            'caching' => 'active',
            'crisis_detection' => 'active',
            'auto_assignment' => 'active',
            'admin_functionality' => 'active',
            'bulk_operations' => 'active',
            'analytics' => 'active',
            'export_functionality' => 'active',
        ],
        'roles' => [
            'student' => 'Can create and view own tickets, access help and resources',
            'counselor' => 'Can handle mental health and crisis tickets, suggest content',
            'advisor' => 'Can handle academic and general tickets',
            'admin' => 'Full system access and management',
        ],
        'delete_endpoints' => [
            'standard_rest' => 'DELETE /api/tickets/{id} - RESTful approach with JSON body',
            'form_style' => 'POST /api/tickets/{id}/delete - Form-style deletion',
            'note' => 'Both endpoints use the same controller method for consistency'
        ],
        'performance' => [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => round((microtime(true) - LARAVEL_START) * 1000, 2) . 'ms'
        ]
    ]);
});

// API documentation endpoint
Route::get('/docs', function () {
    return response()->json([
        'success' => true,
        'message' => 'Student Support Platform API - Enhanced with Help & Resources',
        'version' => '2.1.0',
        'architecture' => [
            'refactored' => 'Routes organized into focused files by category',
            'main_file' => 'routes/api.php - imports all route files',
            'route_files' => [
                'auth.php' => 'Authentication routes',
                'tickets.php' => 'Ticket management routes',
                'notifications.php' => 'Notification routes',
                'users.php' => 'User management routes',
                'admin.php' => 'Admin-specific routes',
                'help.php' => 'Help & FAQ routes',
                'resources.php' => 'Resource library routes',
                'system.php' => 'System routes (health, docs, fallback)',
            ]
        ],
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
        'new_endpoints' => [
            'help_faqs' => [
                'GET /api/help/categories' => 'Get help categories',
                'GET /api/help/faqs' => 'Get FAQs with filtering',
                'GET /api/help/faqs/{id}' => 'Get single FAQ',
                'POST /api/help/faqs/{id}/feedback' => 'Provide FAQ feedback',
                'POST /api/help/suggest-content' => 'Suggest FAQ content (counselors)',
                'GET /api/help/stats' => 'Get help statistics',
            ],
            'resources' => [
                'GET /api/resources/categories' => 'Get resource categories',
                'GET /api/resources' => 'Get resources with filtering',
                'GET /api/resources/{id}' => 'Get single resource',
                'POST /api/resources/{id}/access' => 'Access resource (track usage)',
                'POST /api/resources/{id}/feedback' => 'Rate/review resource',
                'POST /api/resources/{id}/bookmark' => 'Bookmark resource',
                'GET /api/resources/user/bookmarks' => 'Get user bookmarks',
                'GET /api/resources/stats' => 'Get resource statistics',
            ],
            'admin_help' => [
                'GET /api/admin/help/categories' => 'Manage help categories',
                'POST /api/admin/help/categories' => 'Create help category',
                'GET /api/admin/help/faqs' => 'Manage FAQs',
                'POST /api/admin/help/faqs' => 'Create FAQ',
                'POST /api/admin/help/faqs/bulk-action' => 'Bulk FAQ operations',
                'GET /api/admin/help/analytics' => 'Help analytics',
            ],
            'admin_resources' => [
                'GET /api/admin/resources/categories' => 'Manage resource categories',
                'POST /api/admin/resources/categories' => 'Create resource category',
                'GET /api/admin/resources' => 'Manage resources',
                'POST /api/admin/resources' => 'Create resource',
                'POST /api/admin/resources/bulk-action' => 'Bulk resource operations',
                'GET /api/admin/resources/analytics' => 'Resource analytics',
                'GET /api/admin/resources/export' => 'Export resources',
            ]
        ],
        'role_based_features' => [
            'student_routes' => '/api/student/*',
            'counselor_routes' => '/api/counselor/*',
            'advisor_routes' => '/api/advisor/*',
            'admin_routes' => '/api/admin/*',
            'staff_routes' => '/api/staff/*',
        ],
        'ticket_deletion' => [
            'FIXED' => 'Single standardized delete method handles both approaches',
            'DELETE /api/tickets/{id}' => 'Standard RESTful deletion with JSON body',
            'POST /api/tickets/{id}/delete' => 'Alternative form-style deletion',
            'required_fields' => ['reason' => 'string|min:10|max:500', 'notify_user' => 'boolean|optional'],
            'admin_only' => 'Only administrators can delete tickets',
            'immediate_cleanup' => 'Files and database records cleaned up atomically'
        ],
        'permissions_summary' => [
            'students' => 'Create tickets, browse help/resources, bookmark content',
            'counselors' => 'Student permissions + handle mental health tickets + suggest content',
            'advisors' => 'Student permissions + handle academic tickets',
            'admins' => 'Full CRUD on all content, user management, system analytics',
        ],
        'rate_limits' => [
            'help_browsing' => '60-120 per minute',
            'resource_access' => '100-200 per minute',
            'feedback_submission' => '30 per minute',
            'content_suggestions' => '10 per minute (counselors)',
            'admin_operations' => '10-30 per minute',
            'analytics' => '30 per minute',
            'exports' => '5 per minute',
            'notifications' => '60-120 per minute',
            'ticket_operations' => '100-200 per minute',
            'user_management' => '20-50 per minute',
            'bulk_operations' => '10-20 per minute',
            'delete_operations' => '20 per minute (admin only)'
        ],
        'authentication' => [
            'type' => 'Laravel Sanctum Token-based',
            'login' => 'POST /api/auth/login',
            'logout' => 'POST /api/auth/logout',
            'demo_login' => 'POST /api/auth/demo-login',
            'user_info' => 'GET /api/auth/user',
        ],
        'improvements' => [
            'organized_structure' => 'Routes split into focused files by functionality',
            'single_delete_method' => 'Unified delete handling prevents UI freezing',
            'atomic_operations' => 'Database transactions ensure data consistency',
            'proper_cleanup' => 'Files and records deleted in single transaction',
            'enhanced_validation' => 'Better error messages and validation',
            'immediate_response' => 'Fast response prevents frontend timeouts',
            'maintainable_code' => 'Easier to maintain and extend individual route files'
        ],
        'documentation' => 'Contact admin for detailed API documentation'
    ]);
})->middleware('throttle:20,1');

// Fallback route for 404
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found',
        'available_endpoints' => [
            'GET /api/health' => 'Health check',
            'GET /api/docs' => 'API documentation',
            'POST /api/auth/login' => 'User login',
            'POST /api/auth/demo-login' => 'Demo login',
            'GET /api/tickets' => 'Get tickets (role-filtered)',
            'POST /api/tickets' => 'Create ticket (students only)',
            'DELETE /api/tickets/{id}' => 'Delete ticket (admin only) - FIXED',
            'POST /api/tickets/{id}/delete' => 'Alternative delete (admin only) - FIXED',
            'GET /api/user/permissions' => 'Get user permissions',
            'GET /api/help/faqs' => 'Browse help content',
            'GET /api/resources' => 'Browse resource library',
        ],
        'role_specific_endpoints' => [
            'students' => '/api/student/*',
            'counselors' => '/api/counselor/*',
            'advisors' => '/api/advisor/*',
            'admins' => '/api/admin/*',
            'all_staff' => '/api/staff/*',
        ],
        'route_organization' => [
            'auth.php' => 'Authentication (login, logout, user info)',
            'tickets.php' => 'Ticket management and role-specific dashboards',
            'notifications.php' => 'Notification system',
            'users.php' => 'User profiles and permissions',
            'admin.php' => 'Admin management and user administration',
            'help.php' => 'Help system and FAQ management',
            'resources.php' => 'Resource library and bookmarks',
            'system.php' => 'Health checks and documentation',
        ],
        'fixed_issues' => [
            'duplicate_delete_routes' => 'Removed - now single method handles both',
            'ui_freezing' => 'Fixed with atomic operations and fast responses',
            'state_cleanup' => 'Proper frontend state management implemented',
            'large_route_file' => 'Refactored into organized, focused files'
        ],
        'suggestion' => 'Check /api/docs for complete endpoint documentation or individual route files for specific functionality'
    ], 404);
});