{{-- resources/views/emails/welcome-user.blade.php --}}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ config('app.name') }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8fafc;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .content {
            padding: 30px;
        }
        .welcome-box {
            background: #f0f9ff;
            border: 2px solid #0ea5e9;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .credentials-box {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .credentials-box h3 {
            color: #92400e;
            margin-top: 0;
        }
        .credential-item {
            margin: 10px 0;
            padding: 8px 12px;
            background: white;
            border-radius: 4px;
            border-left: 4px solid #f59e0b;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 10px 0;
        }
        .security-notice {
            background: #fee2e2;
            border: 2px solid #ef4444;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .security-notice h3 {
            color: #dc2626;
            margin-top: 0;
        }
        .footer {
            background: #f8fafc;
            padding: 20px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .role-student { background: #dbeafe; color: #1e40af; }
        .role-counselor { background: #fce7f3; color: #be185d; }
        .role-advisor { background: #d1fae5; color: #047857; }
        .role-admin { background: #e0e7ff; color: #3730a3; }
        
        @media (max-width: 480px) {
            body { padding: 10px; }
            .header, .content { padding: 20px; }
            .header h1 { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🎉 Welcome to {{ config('app.name') }}!</h1>
            <p>Your account has been successfully created</p>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="welcome-box">
                <h2>Hello {{ $user->name }}! 👋</h2>
                <p>Welcome to our Student Support Platform! We're excited to have you join our community.</p>
                <p>Your account has been created with the following role: 
                    <span class="role-badge role-{{ $user->role }}">{{ ucfirst($user->role) }}</span>
                </p>
            </div>

            <!-- Login Credentials -->
            <div class="credentials-box">
                <h3>🔐 Your Login Credentials</h3>
                <p>Please use the following credentials to access your account:</p>
                
                <div class="credential-item">
                    <strong>Email:</strong> {{ $user->email }}
                </div>
                
                <div class="credential-item">
                    <strong>Temporary Password:</strong> <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-family: monospace;">{{ $temporaryPassword }}</code>
                </div>

                @if($user->student_id)
                <div class="credential-item">
                    <strong>Student ID:</strong> {{ $user->student_id }}
                </div>
                @endif

                @if($user->employee_id)
                <div class="credential-item">
                    <strong>Employee ID:</strong> {{ $user->employee_id }}
                </div>
                @endif
            </div>

            <!-- Security Notice -->
            <div class="security-notice">
                <h3>🛡️ Important Security Notice</h3>
                <ul>
                    <li><strong>Change Your Password:</strong> You must change your password on first login</li>
                    <li><strong>Keep Credentials Safe:</strong> Don't share your login credentials with anyone</li>
                    <li><strong>Secure Access:</strong> Always log out when using shared computers</li>
                    <li><strong>Password Requirements:</strong> Use at least 8 characters with a mix of letters, numbers, and symbols</li>
                </ul>
            </div>

            <!-- Role-specific Information -->
            @if($user->role === 'student')
            <div style="background: #dbeafe; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: #1e40af; margin-top: 0;">📚 Student Resources</h3>
                <p>As a student, you can:</p>
                <ul>
                    <li>Submit support tickets for academic and personal concerns</li>
                    <li>Access mental health resources and counseling services</li>
                    <li>Browse our comprehensive resource library</li>
                    <li>Track your support requests and responses</li>
                </ul>
            </div>
            @elseif($user->role === 'counselor')
            <div style="background: #fce7f3; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: #be185d; margin-top: 0;">💜 Counselor Dashboard</h3>
                <p>As a counselor, you can:</p>
                <ul>
                    <li>Manage mental health and crisis support tickets</li>
                    <li>Access student profiles and support history</li>
                    <li>Contribute to the resource library</li>
                    <li>Collaborate with advisors and administrators</li>
                </ul>
            </div>
            @elseif($user->role === 'advisor')
            <div style="background: #d1fae5; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: #047857; margin-top: 0;">🎯 Advisor Portal</h3>
                <p>As an advisor, you can:</p>
                <ul>
                    <li>Handle academic and general support tickets</li>
                    <li>Provide guidance and academic counseling</li>
                    <li>Access student academic information</li>
                    <li>Collaborate with counselors and administrators</li>
                </ul>
            </div>
            @elseif($user->role === 'admin')
            <div style="background: #e0e7ff; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: #3730a3; margin-top: 0;">⚡ Administrator Access</h3>
                <p>As an administrator, you have:</p>
                <ul>
                    <li>Full system access and user management</li>
                    <li>Analytics and reporting capabilities</li>
                    <li>Content and resource management</li>
                    <li>System configuration and maintenance</li>
                </ul>
            </div>
            @endif

            <!-- Login Button -->
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ config('app.frontend_url', config('app.url')) }}/login" class="btn">
                    🚀 Login to Your Account
                </a>
            </div>

            <!-- Support Information -->
            <div style="background: #f0f9ff; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: #1e40af; margin-top: 0;">📞 Need Help?</h3>
                <p>If you have any questions or need assistance, please contact us:</p>
                <ul>
                    <li><strong>Email:</strong> {{ config('mail.support_email', 'support@' . parse_url(config('app.url'), PHP_URL_HOST)) }}</li>
                    <li><strong>Phone:</strong> {{ config('app.support_phone', '+1 (555) 123-4567') }}</li>
                    <li><strong>Help Center:</strong> <a href="{{ config('app.frontend_url', config('app.url')) }}/help">Visit our Help Center</a></li>
                    <li><strong>Office Hours:</strong> Monday - Friday, 8:00 AM - 6:00 PM</li>
                </ul>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
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