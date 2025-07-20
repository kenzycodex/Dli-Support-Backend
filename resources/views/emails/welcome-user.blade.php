{{-- resources/views/emails/welcome-user.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome to {{ config('app.name') }}</title>
</head>
<body style="font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5;">
    <div style="max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        
        <!-- Header -->
        <div style="text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
            <h1 style="margin: 0; font-size: 28px;">🎉 Welcome to {{ config('app.name') }}!</h1>
            <p style="margin: 10px 0 0 0;">Your account has been successfully created</p>
        </div>

        <!-- Content -->
        <div>
            <h2>Hello {{ $user->name }}! 👋</h2>
            
            <p>Welcome to our Student Support Platform! We're excited to have you join our community.</p>
            
            <p>Your account has been created with the role: <strong>{{ ucfirst($user->role) }}</strong></p>

            <!-- Credentials -->
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h3 style="color: #856404; margin-top: 0;">🔐 Your Login Credentials</h3>
                
                <div style="background: white; padding: 10px; border-radius: 5px; margin: 10px 0;">
                    <strong>Email:</strong> {{ $user->email }}
                </div>
                
                <div style="background: white; padding: 10px; border-radius: 5px; margin: 10px 0;">
                    <strong>Temporary Password:</strong> 
                    <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-family: monospace;">{{ $temporaryPassword }}</code>
                </div>

                @if($user->student_id)
                <div style="background: white; padding: 10px; border-radius: 5px; margin: 10px 0;">
                    <strong>Student ID:</strong> {{ $user->student_id }}
                </div>
                @endif

                @if($user->employee_id)
                <div style="background: white; padding: 10px; border-radius: 5px; margin: 10px 0;">
                    <strong>Employee ID:</strong> {{ $user->employee_id }}
                </div>
                @endif
            </div>

            <!-- Security Notice -->
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h3 style="color: #721c24; margin-top: 0;">🛡️ Important Security Notice</h3>
                <ul style="margin: 0; padding-left: 20px;">
                    <li><strong>Change Your Password:</strong> You must change your password on first login</li>
                    <li><strong>Keep Credentials Safe:</strong> Don't share your login credentials with anyone</li>
                    <li><strong>Secure Access:</strong> Always log out when using shared computers</li>
                </ul>
            </div>

            <!-- Login Button -->
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ config('app.frontend_url', config('app.url')) }}/login" 
                   style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px;">
                    🚀 Login to Your Account
                </a>
            </div>

            <!-- Support Info -->
            <div style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h3 style="color: #0c5460; margin-top: 0;">📞 Need Help?</h3>
                <p>If you have any questions or need assistance:</p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li><strong>Email:</strong> {{ config('mail.support_email', 'dlienquiries@unilag.edu.ng') }}</li>
                    <li><strong>Phone:</strong> {{ config('app.support_phone', '+234 (0) 1 234 5678') }}</li>
                    <li><strong>Office Hours:</strong> Monday - Friday, 8:00 AM - 6:00 PM</li>
                </ul>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align: center; color: #6c757d; font-size: 14px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
            <p>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <p>This email was sent to {{ $user->email }} because an account was created for you.</p>
            <p style="font-size: 12px; margin-top: 15px;">
                For security reasons, this temporary password will expire in 7 days.<br>
                Please log in and change your password as soon as possible.
            </p>
        </div>
    </div>
</body>
</html>