# User Management Workflow & Email Integration Guide

## 🚀 Overview

This guide explains how the enhanced user management system works, including the configurable email integration, bulk operations, and workflow processes.

## 📋 Table of Contents

1. [Single User Creation Workflow](#single-user-creation-workflow)
2. [Bulk User Creation Workflow](#bulk-user-creation-workflow)
3. [Email System Configuration](#email-system-configuration)
4. [Email Templates & Content](#email-templates--content)
5. [Queue System](#queue-system)
6. [Testing & Verification](#testing--verification)
7. [Troubleshooting](#troubleshooting)

---

## 🔄 Single User Creation Workflow

### Step 1: Admin Creates User
```http
POST /api/admin/users
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john.doe@university.edu",
  "role": "student",
  "status": "active",
  "send_welcome_email": true,
  "generate_password": true
}
```

### Step 2: System Processing
1. **Validation**: Email uniqueness, role validity, required fields
2. **User Creation**: Database record created with auto-generated password
3. **Email Decision**: Check if emails should be sent based on:
   - Global setting: `SEND_WELCOME_EMAILS=true`
   - Request parameter: `send_welcome_email=true`
4. **Queue Email Job**: If emails enabled, `SendWelcomeEmail` job queued
5. **Response**: Immediate response with user data

### Step 3: Email Processing (Asynchronous)
1. **Job Execution**: `SendWelcomeEmail` job processes
2. **Email Generation**: Welcome email created with:
   - Role-specific content
   - Temporary password
   - Security instructions
   - Next steps
3. **Email Sending**: Email sent via configured mail service
4. **User Update**: `welcome_email_sent_at` timestamp updated

### Configuration Controls
```bash
# Master email toggle
SEND_WELCOME_EMAILS=true/false

# Individual request control
send_welcome_email=true/false (API parameter)

# Password generation
AUTO_GENERATE_PASSWORDS=true/false
TEMPORARY_PASSWORD_LENGTH=12
```

---

## 📊 Bulk User Creation Workflow

### Step 1: Admin Initiates Bulk Creation

#### Option A: CSV Upload
```http
POST /api/admin/users/bulk-create
Content-Type: multipart/form-data

csv_file: [CSV file]
skip_duplicates: true
send_welcome_email: true
generate_passwords: true
```

#### Option B: Data Array
```http
POST /api/admin/users/bulk-create
Content-Type: application/json

{
  "users_data": [
    {
      "name": "Student One",
      "email": "student1@uni.edu",
      "role": "student"
    },
    {
      "name": "Counselor Jane",
      "email": "jane@uni.edu", 
      "role": "counselor"
    }
  ],
  "skip_duplicates": true,
  "send_welcome_email": true,
  "generate_passwords": true
}
```

### Step 2: Processing Pipeline
1. **File Processing**: CSV parsed and validated
2. **Data Validation**: Each user record validated
3. **Duplicate Handling**: Skip or report duplicate emails
4. **User Creation**: Users created in batches with database transactions
5. **Password Generation**: Temporary passwords generated for each user
6. **Email Queue**: If enabled, `SendBulkWelcomeEmails` job queued
7. **Response**: Immediate summary response

### Step 3: Bulk Email Processing (Asynchronous)
1. **Job Execution**: `SendBulkWelcomeEmails` job processes
2. **Batch Processing**: Users processed in configurable batches (default: 10)
3. **Individual Emails**: Welcome email sent to each user
4. **Rate Limiting**: Delays between emails to prevent service overload
5. **Progress Tracking**: Success/failure tracking
6. **Admin Report**: Completion report sent to admin

### Configuration Controls
```bash
# Bulk operation settings
MAX_BULK_USERS_PER_IMPORT=1000
BULK_EMAIL_BATCH_SIZE=10
BULK_EMAIL_DELAY_SECONDS=2
MAX_BULK_EMAIL_RECIPIENTS=500

# Admin reporting
SEND_BULK_OPERATION_REPORTS=true/false
```

---

## 📧 Email System Configuration

### Master Email Controls

The email system has multiple layers of control:

#### 1. Global Configuration (.env)
```bash
# Master switches
SEND_WELCOME_EMAILS=true          # Global welcome email toggle
SEND_BULK_OPERATION_REPORTS=true  # Admin reports toggle
SEND_ADMIN_NOTIFICATIONS=true     # Admin notifications toggle
SEND_PASSWORD_RESET_EMAILS=true   # Password reset emails toggle

# Email behavior
AUTO_GENERATE_PASSWORDS=true      # Auto-generate passwords
REQUIRE_EMAIL_VERIFICATION=false  # Require email verification
SEND_EMAIL_ON_STATUS_CHANGE=true  # Email on status changes
SEND_EMAIL_ON_ROLE_CHANGE=true    # Email on role changes
```

#### 2. Runtime Configuration (API Request)
```json
{
  "send_welcome_email": true,      // Override per request
  "generate_password": true,       // Generate password for this user
  "notify_user": true             // Send notifications
}
```

#### 3. Email Service Configuration
```bash
# Basic SMTP settings
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io        # Development: Mailtrap
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@dlisupport.com"
MAIL_FROM_NAME="DLI Support Platform"

# Production options
# Gmail: smtp.gmail.com:587
# SendGrid: smtp.sendgrid.net:587
# Mailgun: smtp.mailgun.org:587
```

### Email Decision Logic

```php
// Email will be sent if:
function shouldSendEmail($request) {
    $globalEnabled = config('app.send_welcome_emails', env('SEND_WELCOME_EMAILS', true));
    $requestEnabled = $request->boolean('send_welcome_email', true);
    
    return $globalEnabled && $requestEnabled;
}
```

---

## 📄 Email Templates & Content

### Welcome Email Content Structure

#### Dynamic Content Based on Role:
- **Students**: Resource access, support options, crisis support
- **Counselors**: Case management, crisis handling, collaboration tools
- **Advisors**: Academic guidance, student tracking, reporting
- **Admins**: System management, user oversight, analytics

#### Security Information:
- Password requirements
- Two-factor authentication info
- Session management tips
- Role-specific security considerations

#### Next Steps:
- Login instructions
- Profile completion
- Platform orientation
- Role-specific onboarding

### Bulk Creation Report Content:
- Operation summary
- Success/failure statistics
- User role breakdown
- Recommendations
- Next actions
- Detailed CSV attachment (for large operations)

---

## ⚙️ Queue System

### Queue Configuration
```bash
# Queue connection
QUEUE_CONNECTION=database         # Development
# QUEUE_CONNECTION=redis          # Production

# Queue names
QUEUE_EMAILS=emails
QUEUE_BULK_EMAILS=bulk-emails
QUEUE_ADMIN_REPORTS=admin-reports
QUEUE_USER_EMAILS=user-emails
QUEUE_STAFF_EMAILS=staff-emails
QUEUE_ADMIN_EMAILS=admin-emails
QUEUE_PASSWORD_RESETS=password-resets
```

### Queue Workers
```bash
# Start queue workers
php artisan queue:work --queue=emails,bulk-emails,admin-reports

# Monitor queue status
php artisan queue:monitor emails bulk-emails admin-reports

# Clear failed jobs
php artisan queue:clear

# Restart workers (after code changes)
php artisan queue:restart
```

### Job Priorities
1. **Admin Emails**: Highest priority
2. **Password Resets**: High priority  
3. **Staff Emails**: Medium priority
4. **User Emails**: Normal priority
5. **Bulk Emails**: Lower priority (to prevent overwhelming)

---

## 🧪 Testing & Verification

### Development Testing with Mailtrap

1. **Setup Mailtrap Account**:
   - Sign up at [mailtrap.io](https://mailtrap.io)
   - Get SMTP credentials from your inbox
   - Update `.env` with credentials

2. **Test Single User Creation**:
```bash
# Via API
curl -X POST http://localhost:8000/api/admin/users \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "role": "student",
    "send_welcome_email": true,
    "generate_password": true
  }'

# Via Tinker
php artisan tinker
>>> $user = App\Models\User::factory()->create(['role' => 'student']);
>>> App\Jobs\SendWelcomeEmail::dispatch($user, 'TempPass123', true);
```

3. **Test Bulk Creation**:
```bash
# Create CSV file with test data
name,email,role,status
John Doe,john@test.com,student,active
Jane Smith,jane@test.com,counselor,active

# Upload via API or admin interface
```

4. **Verify in Mailtrap**:
   - Check inbox for welcome emails
   - Verify email content and formatting
   - Test email links and buttons

### Production Testing

1. **Use Test Email Addresses**:
```bash
# Test with real email service but safe recipients
MAIL_HEALTH_CHECK_RECIPIENT="admin@yourdomain.com"
```

2. **Monitor Email Logs**:
```bash
# Watch email activity
tail -f storage/logs/laravel.log | grep -i email

# Check queue jobs
php artisan queue:monitor emails
```

3. **Email Service Verification**:
   - SendGrid: Check delivery statistics
   - Gmail: Verify sent items
   - Mailgun: Check delivery logs

---

## 🔧 Troubleshooting

### Common Issues & Solutions

#### 1. Emails Not Sending
**Check:**
- [ ] `SEND_WELCOME_EMAILS=true` in `.env`
- [ ] Queue workers running: `php artisan queue:work`
- [ ] SMTP credentials correct
- [ ] No firewall blocking SMTP ports
- [ ] Check failed jobs: `php artisan queue:failed`

**Debug:**
```bash
# Test SMTP connection
php artisan tinker
>>> Mail::raw('Test message', function($msg) { $msg->to('test@example.com')->subject('Test'); });

# Check email logs
grep -i "email" storage/logs/laravel.log

# Monitor queue in real-time
php artisan queue:work --verbose
```

#### 2. Bulk Operations Failing
**Check:**
- [ ] CSV file format correct
- [ ] File size within limits (`MAX_CSV_FILE_SIZE_KB`)
- [ ] User limit not exceeded (`MAX_BULK_USERS_PER_IMPORT`)
- [ ] Database connection stable
- [ ] Memory limits sufficient

**Debug:**
```bash
# Check bulk operation logs
grep -i "bulk" storage/logs/laravel.log

# Monitor database transactions
tail -f storage/logs/laravel.log | grep -i "transaction"
```

#### 3. Queue Jobs Failing
**Check:**
- [ ] Queue table exists: `php artisan queue:table`
- [ ] Failed jobs table: `php artisan queue:failed-table`
- [ ] Job timeout sufficient (`QUEUE_WORKER_TIMEOUT`)
- [ ] Memory limits adequate

**Debug:**
```bash
# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear all jobs
php artisan queue:clear
```

#### 4. Email Template Issues
**Check:**
- [ ] Template files exist in `resources/views/emails/`
- [ ] Template syntax correct
- [ ] Required variables passed to template
- [ ] View cache cleared: `php artisan view:clear`

### Configuration Verification Script

```bash
# Create a simple verification script
php artisan tinker

# Check email configuration
>>> config('mail.mailer')
>>> config('app.send_welcome_emails')
>>> config('queue.default')

# Test user creation workflow
>>> $user = App\Models\User::create([
...   'name' => 'Test User',
...   'email' => 'test@example.com',
...   'password' => Hash::make('password'),
...   'role' => 'student',
...   'status' => 'active'
... ]);

# Test email job
>>> App\Jobs\SendWelcomeEmail::dispatch($user, 'TempPass123', true);

# Check if job was queued
>>> DB::table('jobs')->count()
```

### Monitoring Dashboard

Create a simple monitoring endpoint:

```php
// Add to routes/web.php
Route::get('/admin/email-status', function() {
    return [
        'email_config' => [
            'mailer' => config('mail.mailer'),
            'host' => config('mail.host'),
            'from' => config('mail.from.address'),
        ],
        'feature_flags' => [
            'welcome_emails' => config('app.send_welcome_emails'),
            'bulk_reports' => config('app.send_bulk_operation_reports'),
            'admin_notifications' => config('app.send_admin_notifications'),
        ],
        'queue_stats' => [
            'pending_jobs' => DB::table('jobs')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
        ],
        'recent_users' => User::where('created_at', '>=', now()->subDay())->count(),
    ];
})->middleware('auth:sanctum', 'role:admin');
```

---

## 🏁 Summary

The user management system provides:

✅ **Configurable Email System**: Global and per-request email controls  
✅ **Robust Bulk Operations**: CSV upload and data array processing  
✅ **Asynchronous Processing**: Queue-based email sending  
✅ **Role-Based Content**: Customized emails per user role  
✅ **Admin Reporting**: Detailed operation reports  
✅ **Error Handling**: Comprehensive error tracking and retry logic  
✅ **Development Testing**: Mailtrap integration for safe testing  
✅ **Production Ready**: Support for major email services  

### Quick Start Checklist:

1. ✅ Configure `.env` email settings
2. ✅ Set `SEND_WELCOME_EMAILS=true`
3. ✅ Start queue workers: `php artisan queue:work`
4. ✅ Test with single user creation
5. ✅ Verify emails in Mailtrap/email service
6. ✅ Test bulk operations with small CSV
7. ✅ Monitor logs and queue status
8. ✅ Configure production email service when ready

The system is designed to be fail-safe: if emails are disabled or fail, user creation still succeeds, ensuring core functionality is never blocked by email issues.