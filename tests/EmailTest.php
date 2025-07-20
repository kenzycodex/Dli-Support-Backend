<?php
// Create this file: tests/EmailTest.php or run in tinker

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use App\Models\User;
use App\Jobs\SendWelcomeEmail;
use App\Mail\WelcomeUser;

/**
 * Comprehensive Email Testing Script
 * Run this in tinker: php artisan tinker
 * Then copy and paste each section
 */

// 1. Test basic configuration
echo "🔧 Testing Email Configuration...\n";
echo "Mail Driver: " . config('mail.default') . "\n";
echo "Mail Host: " . config('mail.mailers.smtp.host') . "\n";
echo "Mail Port: " . config('mail.mailers.smtp.port') . "\n";
echo "Mail Encryption: " . config('mail.mailers.smtp.encryption') . "\n";
echo "From Address: " . config('mail.from.address') . "\n";
echo "Queue Connection: " . config('queue.default') . "\n\n";

// 2. Test basic raw email (synchronous)
echo "📧 Testing Basic Raw Email (Sync)...\n";
try {
    Mail::raw('This is a test email from DLI Support Platform. If you receive this, basic email is working!', function($message) {
        $message->to('kenzycodex@gmail.com')
                ->subject('DLI Support - Basic Email Test - ' . now()->format('H:i:s'));
    });
    echo "✅ Basic email sent successfully!\n\n";
} catch (Exception $e) {
    echo "❌ Basic email failed: " . $e->getMessage() . "\n\n";
}

// 3. Test HTML email template (synchronous)
echo "🎨 Testing HTML Email Template (Sync)...\n";
try {
    Mail::send('emails.test', [], function($message) {
        $message->to('kenzycodex@gmail.com')
                ->subject('DLI Support - HTML Template Test - ' . now()->format('H:i:s'));
    });
    echo "✅ HTML email sent successfully!\n\n";
} catch (Exception $e) {
    echo "❌ HTML email failed: " . $e->getMessage() . "\n\n";
}

// 4. Test Welcome Email Mailable (synchronous)
echo "👋 Testing Welcome Email Mailable (Sync)...\n";
try {
    $testUser = User::first();
    if (!$testUser) {
        // Create a test user if none exists
        $testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'student'
        ]);
        echo "Created test user: " . $testUser->email . "\n";
    }
    
    $welcomeEmail = new WelcomeUser($testUser, 'TestPassword123', true);
    Mail::to('kenzycodex@gmail.com')->send($welcomeEmail);
    echo "✅ Welcome email mailable sent successfully!\n\n";
} catch (Exception $e) {
    echo "❌ Welcome email mailable failed: " . $e->getMessage() . "\n\n";
}

// 5. Test queue job dispatch
echo "⚡ Testing Email Queue Job...\n";
try {
    $testUser = User::first();
    if ($testUser) {
        SendWelcomeEmail::dispatch($testUser, 'QueueTestPassword123', true);
        echo "✅ Email job dispatched to queue successfully!\n";
        echo "🔔 Now run: php artisan queue:work --verbose\n\n";
    } else {
        echo "❌ No user found to test queue job\n\n";
    }
} catch (Exception $e) {
    echo "❌ Queue job dispatch failed: " . $e->getMessage() . "\n\n";
}

// 6. Check queue status
echo "📊 Checking Queue Status...\n";
try {
    $pendingJobs = DB::table('jobs')->count();
    $failedJobs = DB::table('failed_jobs')->count();
    echo "Pending jobs: " . $pendingJobs . "\n";
    echo "Failed jobs: " . $failedJobs . "\n\n";
} catch (Exception $e) {
    echo "❌ Could not check queue status: " . $e->getMessage() . "\n\n";
}

// 7. Test with different configurations
echo "🔄 Testing Alternative SMTP Settings...\n";
echo "Try these settings if current config doesn't work:\n";
echo "Option 1 (Gmail TLS): MAIL_HOST=smtp.gmail.com, MAIL_PORT=587, MAIL_ENCRYPTION=tls\n";
echo "Option 2 (Gmail SSL): MAIL_HOST=smtp.gmail.com, MAIL_PORT=465, MAIL_ENCRYPTION=ssl\n";
echo "Option 3 (Gmail StartTLS): MAIL_HOST=smtp.gmail.com, MAIL_PORT=25, MAIL_ENCRYPTION=tls\n\n";

echo "🎯 Email Testing Complete!\n";
echo "Check your email inbox (kenzycodex@gmail.com) for test emails.\n";
echo "If no emails received, check spam folder and verify Gmail app password.\n";