<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),

    // ==========================================
    // API LOGGING CONFIGURATION
    // ==========================================
    
    'api_enabled' => env('API_LOGGING_ENABLED', false),
    'api_request_logging' => env('API_REQUEST_LOGGING', true),
    'api_response_logging' => env('API_RESPONSE_LOGGING', true),
    'api_errors_only' => env('API_LOG_ERRORS_ONLY', false),
    'api_minimal' => env('API_LOG_MINIMAL', false),
    'api_detailed' => env('API_LOG_DETAILED', false),
    'api_log_responses' => env('API_LOG_RESPONSES', false),

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'api_requests'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        // API Request logging channel
        'api_requests' => [
            'driver' => 'daily',
            'path' => storage_path('logs/api-requests.log'),
            'level' => env('API_LOG_LEVEL', 'debug'),
            'days' => env('API_LOG_RETENTION_DAYS', 7),
            'replace_placeholders' => true,
        ],

        // API Errors only channel (for production)
        'api_errors' => [
            'driver' => 'daily',
            'path' => storage_path('logs/api-errors.log'),
            'level' => 'error',
            'days' => env('API_ERROR_LOG_RETENTION_DAYS', 30),
            'replace_placeholders' => true,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => Monolog\Handler\StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => LOG_USER,
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],

];