{{-- resources/views/emails/password-reset.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Password Reset - {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; margin: 0; padding: 10px; background-color: #f9f9f9; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        
        <!-- Header -->
        <div style="background: #dc2626; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
            <h1 style="margin: 0; font-size: 24px;">🔐 Password Reset</h1>
            <p style="margin: 8px 0 0 0; opacity: 0.9;">Your password has been reset</p>
        </div>

        <!-- Content -->
        <div style="padding: 20px;">
            <h2 style="margin-top: 0; color: #1f2937;">Hello {{ $user->name ?? 'User' }},</h2>
            
            <!-- Security Alert -->
            <div style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 15px; margin: 15px 0;">
                <h3 style="margin-top: 0; color: #dc2626;">🚨 Important Security Notice</h3>
                <p style="margin: 0;">Your password has been reset by {{ $adminUser->name ?? 'an administrator' }}.</p>
                @if($resetReason ?? '')
                <p style="margin: 8px 0 0 0;"><strong>Reason:</strong> {{ $resetReason }}</p>
                @endif
                <p style="margin: 8px 0 0 0; font-size: 14px;">If you did not request this, please contact support immediately.</p>
            </div>

            <p>Your account password has been reset. Please use the temporary password below to log in and change your password immediately.</p>

            <!-- Login Credentials -->
            <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px; padding: 15px; margin: 15px 0;">
                <h3 style="margin-top: 0; color: #92400e;">🔑 Your New Login Credentials</h3>
                
                <div style="background: white; padding: 10px; border-radius: 4px; margin: 8px 0;">
                    <strong>Email:</strong> {{ $user->email ?? 'your-email@example.com' }}
                </div>
                
                <div style="background: white; padding: 10px; border-radius: 4px; margin: 8px 0;">
                    <strong>Temporary Password:</strong> 
                    <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-family: monospace;">{{ $temporaryPassword ?? 'temp-password' }}</code>
                </div>

                <p style="margin: 10px 0 0 0; color: #dc2626; font-weight: 600; font-size: 14px;">
                    ⚠️ This password will expire in 7 days
                </p>
            </div>

            <!-- Security Instructions -->
            <div style="background: #dbeafe; border-left: 4px solid #2563eb; padding: 15px; margin: 15px 0;">
                <h3 style="margin-top: 0; color: #1e40af;">🛡️ Security Requirements</h3>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Log in immediately and change your password</li>
                    <li>Use a strong, unique password</li>
                    <li>Do not share your login credentials</li>
                    <li>Log out from all devices after changing password</li>
                    <li>Contact support if you didn't request this reset</li>
                </ul>
            </div>

            <!-- Action Steps -->
            <div style="background: #f0f9ff; border-radius: 6px; padding: 15px; margin: 15px 0;">
                <h3 style="margin-top: 0; color: #0c4a6e;">📋 What to do next</h3>
                <ol style="margin: 0; padding-left: 20px;">
                    <li>Click the login button below</li>
                    <li>Use your email and the temporary password above</li>
                    <li>Change your password immediately (required)</li>
                    <li>Review your account security settings</li>
                    <li>Check for any suspicious account activity</li>
                </ol>
            </div>

            <!-- Login Button -->
            <div style="text-align: center; margin: 25px 0;">
                <a href="{{ config('app.frontend_url', config('app.url')) }}/login" 
                   style="display: inline-block; background: #dc2626; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600;">
                    🔐 Login & Change Password
                </a>
            </div>

            <!-- Important Reminders -->
            <div style="background: #fef3c7; border-radius: 6px; padding: 15px; margin: 15px 0;">
                <h4 style="margin-top: 0; color: #92400e;">⚠️ Important Reminders</h4>
                <ul style="margin: 0; padding-left: 20px; color: #92400e; font-size: 14px;">
                    <li>You must change this temporary password on first login</li>
                    <li>All your active sessions have been terminated for security</li>
                    <li>Your new password must be different from previous passwords</li>
                    <li>Enable two-factor authentication if available</li>
                </ul>
            </div>

            <!-- Support Information -->
            <div style="background: #f0f9ff; border-radius: 6px; padding: 15px; margin: 15px 0;">
                <h3 style="margin-top: 0; color: #1e40af;">📞 Need Help?</h3>
                <p style="margin: 0 0 8px 0;">If you have questions about this password reset:</p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li><strong>Email:</strong> {{ config('mail.support_email', 'support@example.com') }}</li>
                    <li><strong>Phone:</strong> {{ config('app.support_phone', '+1 (555) 123-4567') }}</li>
                    <li><strong>Help Center:</strong> <a href="{{ config('app.frontend_url', config('app.url')) }}/help" style="color: #2563eb;">Visit Help Center</a></li>
                </ul>
                <p style="margin: 8px 0 0 0; color: #1e40af; font-weight: 600; font-size: 14px;">
                    If you believe this reset was unauthorized, contact us immediately.
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div style="background: #f9fafb; padding: 15px; text-align: center; color: #6b7280; font-size: 14px; border-radius: 0 0 8px 8px;">
            <p style="margin: 0;">© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <p style="margin: 5px 0 0 0;">Password reset performed on {{ now()->format('M d, Y \a\t g:i A') }}</p>
            @if($adminUser ?? null)
            <p style="margin: 5px 0 0 0; font-size: 12px;">Reset by: {{ $adminUser->name }} ({{ $adminUser->email }})</p>
            @endif
        </div>
    </div>
</body>
</html>