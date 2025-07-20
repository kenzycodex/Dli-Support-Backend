<?php
// config/cors.php - FIXED: Enhanced CORS configuration for file downloads

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => [
        'api/*', 
        'sanctum/csrf-cookie',
        'storage/*', // Add storage paths for file access
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3000'),
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'https://localhost:3000',
        // Add your production domains here
    ],

    'allowed_origins_patterns' => [
        // Allow localhost with any port for development
        '/^http:\/\/localhost:\d+$/',
        '/^http:\/\/127\.0\.0\.1:\d+$/',
    ],

    'allowed_headers' => [
        '*',
        'Accept',
        'Authorization', 
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
        'Origin',
        'Cache-Control',
        'Pragma',
        // File download specific headers
        'Range',
        'If-Range',
        'If-Modified-Since',
        'If-None-Match',
    ],

    'exposed_headers' => [
        'Content-Disposition',
        'Content-Length',
        'Content-Type',
        'Content-Range',
        'Accept-Ranges',
        'Last-Modified',
        'ETag',
        'Cache-Control',
        'Expires',
        'X-Filename',
    ],

    'max_age' => 0,

    'supports_credentials' => true,

];