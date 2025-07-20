{{-- resources/views/emails/status-change.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Account Status Update - {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; margin: 0; padding: 10px; background-color: #f9f9f9; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        
        <!-- Header -->
        <div style="background: {{ $newStatus === 'active' ? '#059669' : ($newStatus === 'suspended' ? '#dc2626' : '#d97706') }}; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
            <h1 style="margin: 0; font-size: 24px;">
                @if($newStatus === 'active')
                ✅ Account Activated
                @elseif($newStatus === 'suspended')
                🚫 Account Suspended
                @else
                ⏸️ Account Status Changed
                @endif
            </h1>
            <p style="margin: 8px 0 0 0; opacity: 0.9;">Your account status has been updated</p>
        </div>

        <!-- Content -->
        <div style="padding: 20px;">
            <h2 style="margin-top: 0; color: #1f2937;">Hello {{ $user->name ?? 'User' }},</h2>
            
            <!-- Status Change Notice -->
            <div style="background: {{ $newStatus === 'active' ? '#d1fae5' : ($newStatus === 'suspended' ? '#fef2f2' : '#fef3c7') }}; border-left: 4px solid {{ $newStatus === 'active' ? '#059669' : ($newStatus === 'suspended' ? '#dc2626' : '#d97706') }}; padding: 15px; margin: 15px 0;">
                <h3 style="margin-top: 0; color: {{ $newStatus === 'active' ? '#047857' : ($newStatus === 'suspended' ? '#dc2626' : '#92400e') }};">
                    @if($newStatus === 'active')
                    ✅ Account Activated Successfully
                    @elseif($newStatus === 'suspended')
                    🚫 Account Has Been Suspended
                    @elseif($newStatus === 'inactive')
                    ⏸️ Account Has Been Deactivated
                    @else
                    🔄 Account Status Updated
                    @endif
                </h3>
                <p style="margin: 8px 0;">
                    @if($newStatus === 'active')
                    Your account is now active and you have full access to the platform.
                    @elseif($newStatus === 'suspended')
                    Your account has been suspended due to policy violations or security concerns.
                    @elseif($newStatus === 'inactive')
                    Your account has been temporarily deactivated. You will not be able to log in.
                    @else
                    Your account status has been changed to {{ ucfirst($newStatus ?? 'unknown') }}.
                    @endif
                </p>
                @if($reason ?? '')
                <p style="margin: 8px 0 0 0;"><strong>Reason:</strong> {{ $reason }}</p>
                @endif
                <p style="margin: 8px 0 0 0; font-size: 14px;">
                    <strong>Changed by:</strong> {{ $adminUser->name ?? 'System Administrator' }}<br>
                    <strong>Date:</strong> {{ now()->format('M d, Y \a\t g:i A') }}
                </p>
            </div>

            <!-- Status Information -->
            <div style="background: #f3f4f6; border-radius: 6px; padding: 15px; margin: 15px 0;">
                <h3 style="margin-top: 0; color: #374151;">📊 Status Summary</h3>
                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <div style="text-align: center; min-width: 120px;">
                        <div style="font-size: 14px; color: #6b7280;">Previous Status</div>
                        <div style="font-size: 16px; font-weight: bold; color: #374151;">{{ ucfirst($oldStatus ?? 'Unknown') }}</div>
                    </div>
                    <div style="text-align: center; min-width: 120px;">
                        <div style="font-size: 14px; color: #6b7280;">Current Status</div>
                        <div style="font-size: 16px; font-weight: bold; color: {{ $newStatus === 'active' ? '#059669' : ($newStatus === 'suspended' ? '#dc2626' : '#d97706') }};">{{ ucfirst($newStatus ?? 'Unknown') }}</div>
                    </div>
                    <div style="text-align: center; min-width: 120px;">
                        <div style="font-size: 14px; color: #6b7280;">Access Level</div>
                        <div style="font-size: 16px; font-weight: bold; color: #374151;">
                            @if($newStatus === 'active')
                            Full Access
                            @elseif($newStatus === 'suspended')
                            No Access
                            @elseif($newStatus === 'inactive')
                            Restricted
                            @else
                            Unknown
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- What This Means -->
            <div style="background: {{ $newStatus === 'active' ? '#ecfdf5' : ($newStatus === 'suspended' ? '#fef2f2' : '#fefbf3') }}; border-radius: 6px; padding: 15px; margin: 15px 0;">
                <h3 style="margin-top: 0; color: {{ $newStatus === 'active' ? '#047857' : ($newStatus === 'suspended' ? '#dc2626' : '#92400e') }};">
                    @if($newStatus === 'active')
                    🎉 What you can now do:
                    @elseif($newStatus === 'suspended')
                    🚫 Current restrictions:
                    @else
                    ⏸️ Current limitations:
                    @endif
                </h3>
                <ul style="margin: 0; padding-left: 20px;">
                    @if($newStatus === 'active')
                    <li>Login to your account</li>
                    <li>Access all platform features</li>
                    <li>Submit support requests</li>
                    <li>Browse resources and help content</li>
                    <li>Receive notifications and updates</li>
                    @elseif($newStatus === 'suspended')
                    <li>Account login is completely blocked</li>
                    <li>All platform features are disabled</li>
                    <li>Login attempts will be rejected</li>
                    <li>Contact support immediately for assistance</li>
                    @elseif($newStatus === 'inactive')
                    <li>Account login is disabled</li>
                    <li>Platform access is restricted</li>
                    <li>Email notifications may be limited</li>
                    <li>Contact support for reactivation</li>
                    @else
                    <li>Contact support for more information about your current access level</li>
                    @endif
                </ul>
            </div>

            <!-- Next Steps -->
            <div style="background: #f0f9ff; border-radius: 6px; padding: 15px; margin: 15px 0;">
                <h3 style="margin-top: 0; color: #0c4a6e;">📋 Next Steps</h3>
                <ol style="margin: 0; padding-left: 20px;">
                    @if($newStatus === 'active')
                    <li>Log in to your account using the button below</li>
                    <li>Review any missed notifications or updates</li>
                    <li>Update your profile if needed</li>
                    <li>Contact support if you experience any issues</li>
                    @elseif($newStatus === 'suspended')
                    <li>Contact support immediately to discuss this suspension</li>
                    <li>Review our terms of service and community guidelines</li>
                    <li>Provide any requested information for account review</li>
                    <li>Do not attempt to circumvent this suspension</li>
                    @elseif($newStatus === 'inactive')
                    <li>Contact support to understand the reason for deactivation</li>
                    <li>Provide any requested information or documentation</li>
                    <li>Wait for confirmation of account reactivation</li>
                    <li>Do not attempt to create a new account</li>
                    @else
                    <li>Contact support for clarification about your account status</li>
                    <li>Check your email for additional information</li>
                    <li>Review any recent account activity</li>
                    @endif
                </ol>
            </div>

            <!-- Action Button (only for active accounts) -->
            @if($newStatus === 'active')
            <div style="text-align: center; margin: 25px 0;">
                <a href="{{ config('app.frontend_url', config('app.url')) }}/login" 
                   style="display: inline-block; background: #059669; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600;">
                    🚀 Access Your Account
                </a>
            </div>
            @endif

            <!-- Support for Suspensions -->
            @if($newStatus === 'suspended')
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 15px; margin: 15px 0;">
                <h3 style="margin-top: 0; color: #dc2626;">⚖️ Appeal Process</h3>
                <p style="margin: 0 0 8px 0;">If you believe this suspension was made in error, you may appeal:</p>
                <ul style="margin: 0; padding-left: 20px; color: #dc2626;">
                    <li>Contact our appeals team within 30 days</li>
                    <li>Email: appeals@{{ parse_url(config('app.url'), PHP_URL_HOST) ?? 'example.com' }}</li>
                    <li>Include your full name, email, and detailed explanation</li>
                    <li>Appeals are reviewed within 5-10 business days</li>
                </ul>
            </div>
            @endif

            <!-- Support Information -->
            <div style="background: #f0f9ff; border-radius: 6px; padding: 15px; margin: 15px 0;">
                <h3 style="margin-top: 0; color: #1e40af;">📞 Need Assistance?</h3>
                <p style="margin: 0 0 8px 0;">If you have questions about this status change:</p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li><strong>Email:</strong> {{ config('mail.support_email', 'support@example.com') }}</li>
                    <li><strong>Phone:</strong> {{ config('app.support_phone', '+1 (555) 123-4567') }}</li>
                    <li><strong>Help Center:</strong> <a href="{{ config('app.frontend_url', config('app.url')) }}/help" style="color: #2563eb;">Visit Help Center</a></li>
                    @if($newStatus === 'suspended')
                    <li><strong>Appeals:</strong> appeals@{{ parse_url(config('app.url'), PHP_URL_HOST) ?? 'example.com' }}</li>
                    @endif
                </ul>
            </div>
        </div>

        <!-- Footer -->
        <div style="background: #f9fafb; padding: 15px; text-align: center; color: #6b7280; font-size: 14px; border-radius: 0 0 8px 8px;">
            <p style="margin: 0;">© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <p style="margin: 5px 0 0 0;">Status change notification sent to {{ $user->email ?? 'user@example.com' }}</p>
            @if($adminUser ?? null)
            <p style="margin: 5px 0 0 0; font-size: 12px;">Changed by: {{ $adminUser->name }} ({{ $adminUser->email }})</p>
            @endif
        </div>
    </div>
</body>
</html>