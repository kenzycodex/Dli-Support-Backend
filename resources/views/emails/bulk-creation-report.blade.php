{{-- resources/views/emails/bulk-creation-report.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bulk User Creation Report - {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; margin: 0; padding: 10px; background-color: #f9f9f9; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        
        <!-- Header -->
        <div style="background: #2563eb; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
            <h1 style="margin: 0; font-size: 24px;">📊 Bulk User Creation Report</h1>
            <p style="margin: 8px 0 0 0; opacity: 0.9;">{{ now()->format('M d, Y \a\t g:i A') }}</p>
        </div>

        <!-- Content -->
        <div style="padding: 20px;">
            <h2 style="margin-top: 0; color: #1f2937;">Hello {{ $adminUser->name ?? 'Administrator' }},</h2>
            <p>Your bulk user creation operation has been completed. Here's a summary of the results:</p>

            <!-- Statistics -->
            <div style="background: #f3f4f6; border-radius: 6px; padding: 15px; margin: 15px 0;">
                <h3 style="margin-top: 0; color: #374151;">📈 Summary</h3>
                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <div style="text-align: center; min-width: 80px;">
                        <div style="font-size: 20px; font-weight: bold; color: #059669;">{{ $results['successful'] ?? 0 }}</div>
                        <div style="font-size: 12px; color: #6b7280;">Successful</div>
                    </div>
                    <div style="text-align: center; min-width: 80px;">
                        <div style="font-size: 20px; font-weight: bold; color: #dc2626;">{{ $results['failed'] ?? 0 }}</div>
                        <div style="font-size: 12px; color: #6b7280;">Failed</div>
                    </div>
                    <div style="text-align: center; min-width: 80px;">
                        <div style="font-size: 20px; font-weight: bold; color: #d97706;">{{ $results['skipped'] ?? 0 }}</div>
                        <div style="font-size: 12px; color: #6b7280;">Skipped</div>
                    </div>
                    @if(isset($results['emails_sent']))
                    <div style="text-align: center; min-width: 80px;">
                        <div style="font-size: 20px; font-weight: bold; color: #2563eb;">{{ $results['emails_sent'] }}</div>
                        <div style="font-size: 12px; color: #6b7280;">Emails Sent</div>
                    </div>
                    @endif
                </div>
            </div>

            @if(count($createdUsers ?? []) > 0)
            <!-- Created Users -->
            <div style="margin: 20px 0;">
                <h3 style="color: #059669;">✅ Successfully Created Users ({{ count($createdUsers) }})</h3>
                <div style="max-height: 200px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 6px;">
                    @foreach(array_slice($createdUsers, 0, 20) as $userData)
                    <div style="padding: 8px 12px; border-bottom: 1px solid #f3f4f6; {{ $loop->even ? 'background: #f9fafb;' : '' }}">
                        <strong>{{ $userData['name'] ?? $userData['user']->name ?? 'Unknown' }}</strong><br>
                        <span style="color: #6b7280; font-size: 14px;">
                            {{ $userData['email'] ?? $userData['user']->email ?? 'No email' }} - 
                            {{ ucfirst($userData['role'] ?? $userData['user']->role ?? 'student') }}
                        </span>
                    </div>
                    @endforeach
                    @if(count($createdUsers) > 20)
                    <div style="padding: 8px 12px; text-align: center; color: #6b7280; font-style: italic;">
                        ... and {{ count($createdUsers) - 20 }} more users
                    </div>
                    @endif
                </div>
            </div>
            @endif

            @if(isset($results['errors']) && count($results['errors']) > 0)
            <!-- Errors -->
            <div style="margin: 20px 0;">
                <h3 style="color: #dc2626;">❌ Errors ({{ count($results['errors']) }})</h3>
                <div style="max-height: 150px; overflow-y: auto; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 10px;">
                    @foreach(array_slice($results['errors'], 0, 10) as $error)
                    <div style="margin-bottom: 8px; padding: 6px; background: white; border-radius: 4px; font-size: 14px;">
                        <strong>Row {{ ($error['index'] ?? 0) + 1 }}:</strong> {{ $error['email'] ?? 'Unknown' }}<br>
                        <span style="color: #dc2626;">{{ $error['error'] ?? 'Unknown error' }}</span>
                    </div>
                    @endforeach
                    @if(count($results['errors']) > 10)
                    <div style="text-align: center; color: #6b7280; font-style: italic; margin-top: 8px;">
                        ... and {{ count($results['errors']) - 10 }} more errors
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Next Steps -->
            <div style="background: #dbeafe; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0;">
                <h3 style="margin-top: 0; color: #1e40af;">📝 Next Steps</h3>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Welcome emails have been sent to successfully created users</li>
                    <li>Users will need to change their temporary passwords on first login</li>
                    <li>Monitor the admin dashboard for user login activity</li>
                    @if(($results['failed'] ?? 0) > 0)
                    <li>Review and address the failed user creations listed above</li>
                    @endif
                    @if(($results['skipped'] ?? 0) > 0)
                    <li>Check skipped users for duplicate email addresses</li>
                    @endif
                </ul>
            </div>

            <!-- Action Button -->
            <div style="text-align: center; margin: 25px 0;">
                <a href="{{ config('app.frontend_url', config('app.url')) }}/admin/users" 
                   style="display: inline-block; background: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600;">
                    View User Management Dashboard
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div style="background: #f9fafb; padding: 15px; text-align: center; color: #6b7280; font-size: 14px; border-radius: 0 0 8px 8px;">
            <p style="margin: 0;">© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <p style="margin: 5px 0 0 0;">Report generated for {{ $adminUser->email ?? 'administrator' }}</p>
        </div>
    </div>
</body>
</html>