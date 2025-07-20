# Laravel Email System Troubleshooting Guide

## 🎯 Overview

This guide covers common issues encountered when implementing queued email systems in Laravel and their solutions.

## 🚨 Common Issues & Solutions

### Issue 1: "Serialization of 'Closure' not allowed"

**Symptoms:**
- Queue jobs fail with serialization error
- Jobs show as failed in `php artisan queue:failed`
- Error appears when dispatching jobs to queue

**Root Cause:**
Mailables contain methods with closures (anonymous functions) that can't be serialized when jobs are queued.

**Common Sources:**
```php
// ❌ CAUSES ERROR - Don't do this
public function attachments(): array
{
    return [
        Attachment::fromPath($this->getFile())
            ->as('document.pdf')
            ->withMime('application/pdf'),
    ];
}

// ❌ CAUSES ERROR - Complex methods with closures
public function build()
{
    return $this->view('emails.template')
                ->with([
                    'data' => collect($this->items)->map(function($item) {
                        return $item->transform(); // Closure here!
                    })
                ]);
}
```

**✅ Solution:**
```php
// ✅ CORRECT - Simple, clean approach
public function build()
{
    return $this->subject('Email Subject')
                ->view('emails.template');
}

// ✅ CORRECT - No attachments method, or very simple one
// Remove attachments() method entirely or make it extremely simple
```

### Issue 2: Missing Class Imports

**Symptoms:**
- "Class not found" errors in jobs
- Jobs fail with undefined class errors

**Common Missing Imports:**
```php
// ✅ Always include these at the top of jobs/mailables
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;
```

### Issue 3: Template Variable Errors

**Symptoms:**
- "Undefined variable" errors in email templates
- Templates fail to render

**✅ Solution:**
```blade
{{-- ❌ Don't do this --}}
{{ $user->name }}

{{-- ✅ Do this instead --}}
{{ $user->name ?? 'User' }}
{{ $appName ?? config('app.name') }}
{{ $supportEmail ?? 'support@example.com' }}
```

## 🛠️ Standard Fix Pattern

### For Mailables:

```php
<?php
namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class YourMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    // Simple public properties only
    public $user;
    public $data;

    public function __construct(User $user, $data = null)
    {
        $this->user = $user;
        $this->data = $data;
        $this->onQueue('emails'); // Set appropriate queue
    }

    public function build()
    {
        // Keep this method simple - no complex logic
        return $this->subject('Your Subject')
                    ->view('emails.your-template');
    }

    // ❌ Remove attachments() method completely
    // ❌ Remove any helper methods with closures
    // ❌ Remove complex data processing methods
}
```

### For Jobs:

```php
<?php
namespace App\Jobs;

use App\Models\User;
use App\Mail\YourMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema; // CRITICAL!
use Exception;

class YourEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $data;
    public $tries = 3;
    public $timeout = 60;

    public function __construct(User $user, $data = null)
    {
        $this->user = $user;
        $this->data = $data;
        $this->onQueue('emails');
    }

    public function handle()
    {
        try {
            Log::info('Starting email job', [
                'user_id' => $this->user->id,
                'email' => $this->user->email
            ]);

            // Check if user still exists
            $user = User::find($this->user->id);
            if (!$user) {
                Log::warning('User no longer exists');
                return;
            }

            // Send email
            $email = new YourMailable($user, $this->data);
            Mail::to($user->email)->send($email);

            // Safe database updates
            try {
                $user->update([
                    'last_email_sent_at' => now(),
                ]);
            } catch (Exception $e) {
                Log::warning('Failed to update email tracking: ' . $e->getMessage());
                // Don't fail the job for tracking issues
            }

            Log::info('Email sent successfully', ['user_id' => $user->id]);

        } catch (Exception $e) {
            Log::error('Email job failed', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage()
            ]);
            throw $e; // Re-throw to trigger retry
        }
    }

    public function failed(Exception $exception)
    {
        Log::error('Email job failed permanently', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage()
        ]);
    }
}
```

### For Templates:

```blade
{{-- Always provide defaults for variables --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $subject ?? 'Email from ' . config('app.name') }}</title>
</head>
<body style="font-family: Arial, sans-serif; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: white; padding: 20px;">
        
        <h1>{{ $title ?? 'Email Title' }}</h1>
        
        <p>Hello {{ $user->name ?? 'User' }},</p>
        
        <p>{{ $message ?? 'Default message content' }}</p>
        
        {{-- Always check if variables exist --}}
        @if(isset($data) && $data)
            <div>
                {{-- Process data safely --}}
            </div>
        @endif
        
        <p>
            Best regards,<br>
            {{ config('app.name') }} Team
        </p>
        
        <div style="color: #666; font-size: 12px; margin-top: 20px;">
            Sent at {{ now()->format('Y-m-d H:i:s') }}
        </div>
    </div>
</body>
</html>
```

## 🔧 Testing Commands

```bash
# Clear everything
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan queue:flush

# Test mailable directly
php artisan tinker
$user = App\Models\User::first();
$email = new App\Mail\YourMailable($user, 'test-data');
Mail::to('test@example.com')->send($email);

# Test job execution
$job = new App\Jobs\YourEmailJob($user, 'test-data');
$job->handle();

# Test queue dispatch
App\Jobs\YourEmailJob::dispatch($user, 'test-data');

# Run queue worker
php artisan queue:work --verbose --tries=1
```

## 📋 Checklist for Email System Health

- [ ] All mailables have simple `build()` methods
- [ ] No `attachments()` methods with closures
- [ ] All required imports are present
- [ ] Templates have default values for variables
- [ ] Queue workers are running
- [ ] Failed jobs table is empty
- [ ] Email configuration is correct
- [ ] Test emails send successfully

## 🚨 Red Flags to Avoid

❌ **Never do these in mailables/jobs:**
- Complex closures or anonymous functions
- Database queries in mailable constructor
- File operations with callbacks
- Complex data transformations in build() method
- Missing try-catch blocks in jobs
- Forgetting Schema import in jobs

✅ **Always do these:**
- Keep mailables simple
- Add proper imports
- Use try-catch in jobs
- Provide template defaults
- Test thoroughly
- Monitor queue health

## 🎯 Quick Fix Workflow

1. **Identify the failing job/mailable**
2. **Remove all closure-based methods**
3. **Add missing imports**
4. **Simplify templates**
5. **Clear queues and caches**
6. **Test the fix**
7. **Monitor queue worker**

This pattern has proven successful for fixing serialization issues in Laravel email systems.