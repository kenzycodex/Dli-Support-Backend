<?php
// config/app.php - ENHANCED with missing configurations

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'DLI Support'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Frontend Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used for generating frontend links in emails and notifications.
    | Set this to your frontend application URL (React, Vue, etc.).
    |
    */

    'frontend_url' => env('APP_FRONTEND_URL', env('APP_URL', 'http://localhost:3000')),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Performance Settings
    |--------------------------------------------------------------------------
    */
    'max_execution_time' => env('MAX_EXECUTION_TIME', 120), // 2 minutes
    'max_input_time' => env('MAX_INPUT_TIME', 60),
    'memory_limit' => env('MEMORY_LIMIT', '256M'),
    'upload_max_filesize' => env('UPLOAD_MAX_FILESIZE', '10M'),
    'post_max_size' => env('POST_MAX_SIZE', '50M'),

    /*
    |--------------------------------------------------------------------------
    | Support Contact Information
    |--------------------------------------------------------------------------
    |
    | These values are used in email templates and error pages to provide
    | users with contact information for support.
    |
    */

    'support_email' => env('MAIL_SUPPORT_EMAIL', 'dlienquiries@unilag.edu.ng'),
    'support_phone' => env('APP_SUPPORT_PHONE', '+234 (0) 1 234 5678'),
    'admin_email' => env('MAIL_ADMIN_EMAIL', 'admin@dlisupport.com'),

    /*
    |--------------------------------------------------------------------------
    | Password Policy Configuration
    |--------------------------------------------------------------------------
    |
    | These settings define the password requirements for user accounts.
    |
    */

    'password_min_length' => env('PASSWORD_MIN_LENGTH', 8),
    'password_require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
    'password_require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
    'password_require_numbers' => env('PASSWORD_REQUIRE_NUMBERS', true),
    'password_require_symbols' => env('PASSWORD_REQUIRE_SYMBOLS', false),
    'password_expiry_days' => env('PASSWORD_EXPIRY_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Temporary Password Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for temporary passwords used in welcome emails and resets.
    |
    */

    'temporary_password_expiry_days' => env('TEMPORARY_PASSWORD_EXPIRY_DAYS', 7),
    'temporary_password_length' => env('TEMPORARY_PASSWORD_LENGTH', 12),
    'force_password_change_on_first_login' => env('FORCE_PASSWORD_CHANGE_ON_FIRST_LOGIN', true),

    /*
    |--------------------------------------------------------------------------
    | Account Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related configuration for user accounts.
    |
    */

    'max_login_attempts' => env('MAX_LOGIN_ATTEMPTS', 5),
    'max_password_reset_requests' => env('MAX_PASSWORD_RESET_REQUESTS', 3),
    'account_lockout_duration' => env('ACCOUNT_LOCKOUT_DURATION', 30), // minutes
    'session_lifetime' => env('SESSION_LIFETIME', 120), // minutes
    'auto_logout_inactive_users' => env('AUTO_LOGOUT_INACTIVE_USERS', true),

    /*
    |--------------------------------------------------------------------------
    | User Management Defaults
    |--------------------------------------------------------------------------
    |
    | Default settings for user creation and management.
    |
    */

    'default_user_status' => env('DEFAULT_USER_STATUS', 'active'),
    'default_user_role' => env('DEFAULT_USER_ROLE', 'student'),
    'require_unique_student_id' => env('REQUIRE_UNIQUE_STUDENT_ID', true),
    'require_unique_employee_id' => env('REQUIRE_UNIQUE_EMPLOYEE_ID', true),
    'auto_verify_admin_created_users' => env('AUTO_VERIFY_ADMIN_CREATED_USERS', true),

    /*
    |--------------------------------------------------------------------------
    | Email Feature Configuration
    |--------------------------------------------------------------------------
    |
    | Control various email features throughout the application.
    |
    */

    'send_welcome_emails' => env('SEND_WELCOME_EMAILS', true),
    'send_bulk_operation_reports' => env('SEND_BULK_OPERATION_REPORTS', true),
    'send_admin_notifications' => env('SEND_ADMIN_NOTIFICATIONS', true),
    'send_password_reset_emails' => env('SEND_PASSWORD_RESET_EMAILS', true),
    'send_email_on_status_change' => env('SEND_EMAIL_ON_STATUS_CHANGE', true),
    'send_email_on_role_change' => env('SEND_EMAIL_ON_ROLE_CHANGE', true),

    /*
    |--------------------------------------------------------------------------
    | Bulk Operations Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for bulk user operations and email handling.
    |
    */

    'bulk_email_batch_size' => env('BULK_EMAIL_BATCH_SIZE', 10),
    'bulk_email_delay_seconds' => env('BULK_EMAIL_DELAY_SECONDS', 2),
    'max_bulk_email_recipients' => env('MAX_BULK_EMAIL_RECIPIENTS', 1000),
    'auto_generate_passwords' => env('AUTO_GENERATE_PASSWORDS', true),

    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for file uploads including CSV imports.
    |
    */

    'max_csv_import_size' => env('MAX_CSV_IMPORT_SIZE', '5M'),
    'max_csv_import_rows' => env('MAX_CSV_IMPORT_ROWS', 1000),
    'allowed_import_extensions' => ['csv', 'txt'],

    /*
    |--------------------------------------------------------------------------
    | Notification Preferences
    |--------------------------------------------------------------------------
    |
    | Default notification settings for the application.
    |
    */

    'notify_admin_on_user_creation' => env('NOTIFY_ADMIN_ON_USER_CREATION', true),
    'notify_admin_on_bulk_operations' => env('NOTIFY_ADMIN_ON_BULK_OPERATIONS', true),
    'notify_admin_on_failed_emails' => env('NOTIFY_ADMIN_ON_FAILED_EMAILS', true),
    'notify_user_on_status_change' => env('NOTIFY_USER_ON_STATUS_CHANGE', true),
    'notify_user_on_role_change' => env('NOTIFY_USER_ON_ROLE_CHANGE', true),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue names and priorities for different types of jobs.
    |
    */

    'queue_priorities' => [
        'critical' => 'critical-emails',
        'admin' => 'admin-emails',
        'staff' => 'staff-emails',
        'users' => 'user-emails',
        'reports' => 'admin-reports',
        'status_changes' => 'status-changes',
        'password_resets' => 'password-resets',
        'bulk_operations' => 'bulk-operations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Template Configuration
    |--------------------------------------------------------------------------
    |
    | Template paths for different email types.
    |
    */

    'email_templates' => [
        'welcome' => env('WELCOME_EMAIL_TEMPLATE', 'emails.welcome-user'),
        'bulk_report' => env('BULK_REPORT_TEMPLATE', 'emails.bulk-creation-report'),
        'password_reset' => env('PASSWORD_RESET_TEMPLATE', 'emails.password-reset'),
        'status_change' => env('STATUS_CHANGE_TEMPLATE', 'emails.status-change'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Features
    |--------------------------------------------------------------------------
    |
    | Feature flags to enable/disable certain functionality.
    |
    */

    'features' => [
        'user_registration' => env('FEATURE_USER_REGISTRATION', false),
        'bulk_user_import' => env('FEATURE_BULK_USER_IMPORT', true),
        'email_verification' => env('FEATURE_EMAIL_VERIFICATION', false),
        'two_factor_auth' => env('FEATURE_TWO_FACTOR_AUTH', false),
        'user_profiles' => env('FEATURE_USER_PROFILES', true),
        'admin_notifications' => env('FEATURE_ADMIN_NOTIFICATIONS', true),
        'audit_logging' => env('FEATURE_AUDIT_LOGGING', true),
        'api_rate_limiting' => env('FEATURE_API_RATE_LIMITING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => [
        /*
         * Laravel Framework Service Providers...
         */
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,

        /*
         * Package Service Providers...
         */
        // Add any package service providers here

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        // App\Providers\LoggingServiceProvider::class, // Uncomment if you have this
    ],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */

    'aliases' => [
        'App' => Illuminate\Support\Facades\App::class,
        'Arr' => Illuminate\Support\Arr::class,
        'Artisan' => Illuminate\Support\Facades\Artisan::class,
        'Auth' => Illuminate\Support\Facades\Auth::class,
        'Blade' => Illuminate\Support\Facades\Blade::class,
        'Broadcast' => Illuminate\Support\Facades\Broadcast::class,
        'Bus' => Illuminate\Support\Facades\Bus::class,
        'Cache' => Illuminate\Support\Facades\Cache::class,
        'Config' => Illuminate\Support\Facades\Config::class,
        'Cookie' => Illuminate\Support\Facades\Cookie::class,
        'Crypt' => Illuminate\Support\Facades\Crypt::class,
        'Date' => Illuminate\Support\Facades\Date::class,
        'DB' => Illuminate\Support\Facades\DB::class,
        'Eloquent' => Illuminate\Database\Eloquent\Model::class,
        'Event' => Illuminate\Support\Facades\Event::class,
        'File' => Illuminate\Support\Facades\File::class,
        'Gate' => Illuminate\Support\Facades\Gate::class,
        'Hash' => Illuminate\Support\Facades\Hash::class,
        'Http' => Illuminate\Support\Facades\Http::class,
        'Js' => Illuminate\Support\Js::class,
        'Lang' => Illuminate\Support\Facades\Lang::class,
        'Log' => Illuminate\Support\Facades\Log::class,
        'Mail' => Illuminate\Support\Facades\Mail::class,
        'Notification' => Illuminate\Support\Facades\Notification::class,
        'Password' => Illuminate\Support\Facades\Password::class,
        'Queue' => Illuminate\Support\Facades\Queue::class,
        'RateLimiter' => Illuminate\Support\Facades\RateLimiter::class,
        'Redirect' => Illuminate\Support\Facades\Redirect::class,
        'Request' => Illuminate\Support\Facades\Request::class,
        'Response' => Illuminate\Support\Facades\Response::class,
        'Route' => Illuminate\Support\Facades\Route::class,
        'Schema' => Illuminate\Support\Facades\Schema::class,
        'Session' => Illuminate\Support\Facades\Session::class,
        'Storage' => Illuminate\Support\Facades\Storage::class,
        'Str' => Illuminate\Support\Str::class,
        'URL' => Illuminate\Support\Facades\URL::class,
        'Validator' => Illuminate\Support\Facades\Validator::class,
        'View' => Illuminate\Support\Facades\View::class,
    ],

];