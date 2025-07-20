<?php
// config/mail.php - ENHANCED

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.gmail.com'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'), // Changed from 'ssl' to 'tls'
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => env('MAIL_TIMEOUT', 60),
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),


            // Gmail-specific options
            'options' => [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ],
        ],

        'gmail_ssl' => [
            'transport' => 'smtp',
            'host' => 'smtp.gmail.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => 60,
            'options' => [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ],
        ],

        'ses' => [
            'transport' => 'ses',
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'options' => [
                'ConfigurationSetName' => env('SES_CONFIGURATION_SET'),
                'EmailTags' => [
                    ['Name' => 'Environment', 'Value' => env('APP_ENV', 'production')],
                ],
            ],
        ],

        'postmark' => [
            'transport' => 'postmark',
            'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            'client' => [
                'timeout' => 5,
            ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@dlisupport.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'DLI Support')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@dlisupport.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'DLI Support')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Support Email Configuration
    |--------------------------------------------------------------------------
    |
    | Email addresses for different types of support and administrative emails.
    |
    */

    'support_email' => env('MAIL_SUPPORT_EMAIL', 'dlienquiries@unilag.edu.ng'),
    'admin_email' => env('MAIL_ADMIN_EMAIL', 'admin@dlisupport.com'),
    'no_reply_email' => env('MAIL_NO_REPLY_EMAIL', 'noreply@dlisupport.com'),

    /*
    |--------------------------------------------------------------------------
    | Email Feature Configuration
    |--------------------------------------------------------------------------
    |
    | Control various email features throughout the application.
    |
    */

    'welcome_emails_enabled' => env('SEND_WELCOME_EMAILS', true),
    'bulk_emails_enabled' => env('SEND_BULK_OPERATION_REPORTS', true),
    'admin_notifications_enabled' => env('SEND_ADMIN_NOTIFICATIONS', true),
    'password_reset_emails_enabled' => env('SEND_PASSWORD_RESET_EMAILS', true),
    'status_change_emails_enabled' => env('SEND_EMAIL_ON_STATUS_CHANGE', true),

    /*
    |--------------------------------------------------------------------------
    | Email Template Paths
    |--------------------------------------------------------------------------
    |
    | Define the view paths for different email templates.
    |
    */

    'templates' => [
        'welcome' => env('WELCOME_EMAIL_TEMPLATE', 'emails.welcome-user'),
        'bulk_report' => env('BULK_REPORT_TEMPLATE', 'emails.bulk-creation-report'),
        'password_reset' => env('PASSWORD_RESET_TEMPLATE', 'emails.password-reset'),
        'status_change' => env('STATUS_CHANGE_TEMPLATE', 'emails.status-change'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure queue settings for different types of emails.
    |
    */

    'queues' => [
        'welcome' => env('QUEUE_WELCOME_EMAILS', 'user-emails'),
        'admin_reports' => env('QUEUE_ADMIN_REPORTS', 'admin-reports'),
        'status_changes' => env('QUEUE_STATUS_CHANGES', 'status-changes'),
        'password_resets' => env('QUEUE_PASSWORD_RESETS', 'password-resets'),
        'bulk_operations' => env('QUEUE_BULK_OPERATIONS', 'bulk-operations'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for email sending to prevent abuse.
    |
    */

    'rate_limiting' => [
        'enabled' => env('MAIL_RATE_LIMITING_ENABLED', true),
        'max_emails_per_minute' => env('MAIL_MAX_PER_MINUTE', 60),
        'max_emails_per_hour' => env('MAIL_MAX_PER_HOUR', 1000),
        'max_emails_per_day' => env('MAIL_MAX_PER_DAY', 10000),
        'bulk_operation_limit' => env('MAIL_BULK_OPERATION_LIMIT', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure retry settings for failed emails.
    |
    */

    'retry' => [
        'max_attempts' => env('MAIL_MAX_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('MAIL_RETRY_DELAY', 60), // seconds
        'exponential_backoff' => env('MAIL_EXPONENTIAL_BACKOFF', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Tracking and Analytics
    |--------------------------------------------------------------------------
    |
    | Configure email tracking and analytics features.
    |
    */

    'tracking' => [
        'enabled' => env('MAIL_TRACKING_ENABLED', false),
        'open_tracking' => env('MAIL_OPEN_TRACKING', false),
        'click_tracking' => env('MAIL_CLICK_TRACKING', false),
        'bounce_tracking' => env('MAIL_BOUNCE_TRACKING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related email configuration.
    |
    */

    'security' => [
        'verify_peer' => env('MAIL_VERIFY_PEER', true),
        'verify_peer_name' => env('MAIL_VERIFY_PEER_NAME', true),
        'allow_self_signed' => env('MAIL_ALLOW_SELF_SIGNED', false),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bulk Email Configuration
    |--------------------------------------------------------------------------
    |
    | Settings specific to bulk email operations.
    |
    */

    'bulk' => [
        'batch_size' => env('BULK_EMAIL_BATCH_SIZE', 10),
        'delay_between_batches' => env('BULK_EMAIL_DELAY_SECONDS', 2),
        'max_recipients' => env('MAX_BULK_EMAIL_RECIPIENTS', 1000),
        'timeout' => env('BULK_EMAIL_TIMEOUT', 480), // 8 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Content Settings
    |--------------------------------------------------------------------------
    |
    | Default content and formatting settings.
    |
    */

    'content' => [
        'default_charset' => 'UTF-8',
        'line_length' => 70,
        'eol' => "\r\n",
        'enable_html' => true,
        'enable_plain_text' => true,
        'auto_generate_plain_text' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Development and Testing Settings
    |--------------------------------------------------------------------------
    |
    | Settings for development and testing environments.
    |
    */

    'testing' => [
        'log_all_emails' => env('MAIL_LOG_ALL_EMAILS', false),
        'catch_all_email' => env('MAIL_CATCH_ALL_EMAIL'),
        'disable_delivery' => env('MAIL_DISABLE_DELIVERY', false),
        'fake_queue_in_testing' => env('MAIL_FAKE_QUEUE_IN_TESTING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configure when to send notifications to administrators.
    |
    */

    'notifications' => [
        'notify_admin_on_bulk_operations' => env('NOTIFY_ADMIN_ON_BULK_OPERATIONS', true),
        'notify_admin_on_failed_emails' => env('NOTIFY_ADMIN_ON_FAILED_EMAILS', true),
        'notify_admin_on_user_creation' => env('NOTIFY_ADMIN_ON_USER_CREATION', true),
        'admin_notification_threshold' => env('ADMIN_NOTIFICATION_THRESHOLD', 10), // failures
    ],

];