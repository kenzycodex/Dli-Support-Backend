#!/bin/bash
# rotate-keys.sh - Run this monthly or when keys are compromised

echo "🔄 Rotating Laravel application keys..."

# Generate new APP_KEY using Laravel
NEW_APP_KEY=$(php artisan key:generate --show 2>/dev/null)
if [ -n "$NEW_APP_KEY" ]; then
  echo "✅ New APP_KEY generated"
else
  echo "❌ Failed to generate APP_KEY"
fi

# Generate new JWT_SECRET using Node.js crypto
NEW_JWT_SECRET=$(node -e "console.log(require('crypto').randomBytes(64).toString('base64'))")
if [ -n "$NEW_JWT_SECRET" ]; then
  echo "✅ New JWT_SECRET generated"
else
  echo "❌ Failed to generate JWT_SECRET"
fi

# Display and copy to Railway
echo ""
echo "📋 Update these in Railway:"
echo "APP_KEY=$NEW_APP_KEY"
echo "JWT_SECRET=$NEW_JWT_SECRET"

echo ""
echo "⚠️  WARNING: After updating Railway variables:"
echo "1. All users will be logged out"
echo "2. Run: php artisan cache:clear"
echo "3. Run: php artisan config:clear"
echo "4. Consider clearing sessions table (if used)"

echo ""
echo "🔒 Keep these keys secure and never commit them to version control!"
