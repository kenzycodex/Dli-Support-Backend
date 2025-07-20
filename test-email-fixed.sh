#!/bin/bash
echo "🚀 Quick Email Fix for DLI Support Platform"
echo "==========================================="

# Step 1: Backup current files (optional)
echo "📁 Creating backup of current files..."
cp app/Mail/WelcomeUser.php app/Mail/WelcomeUser.php.backup 2>/dev/null || echo "WelcomeUser.php not found"
cp app/Jobs/SendWelcomeEmail.php app/Jobs/SendWelcomeEmail.php.backup 2>/dev/null || echo "SendWelcomeEmail.php not found"

# Step 2: Clear everything
echo "🧹 Clearing caches and failed jobs..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan queue:flush

# Step 3: Ensure email template directory exists
echo "📁 Ensuring email template directory exists..."
mkdir -p resources/views/emails

# Step 4: Test basic email connectivity
echo "📧 Testing basic email connectivity..."
php artisan tinker --execute="
try {
    Mail::raw('Quick test email from DLI Support', function(\$m) { 
        \$m->to('kenzycodex@gmail.com')->subject('DLI Quick Test - ' . now()->format('H:i:s')); 
    });
    echo '✅ Basic email test PASSED\n';
} catch (Exception \$e) {
    echo '❌ Basic email test FAILED: ' . \$e->getMessage() . '\n';
    echo 'Check your .env mail configuration\n';
}"

echo ""
echo "📋 Manual Steps Required:"
echo "1. Replace app/Mail/WelcomeUser.php with the fixed version"
echo "2. Replace app/Jobs/SendWelcomeEmail.php with the fixed version"
echo "3. Create/update resources/views/emails/welcome-user.blade.php"
echo "4. Run: php artisan config:clear"
echo "5. Test with: php artisan tinker"
echo ""
echo "🔧 Test Command for Tinker:"
echo "try { \$user = App\Models\User::first(); \$job = new App\Jobs\SendWelcomeEmail(\$user, 'Test123', true); \$job->handle(); echo 'SUCCESS!'; } catch (Exception \$e) { echo 'ERROR: ' . \$e->getMessage(); }"