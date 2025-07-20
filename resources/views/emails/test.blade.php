{{-- Create this file: resources/views/emails/test.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Test</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h1 style="margin: 0;">🎉 Email Configuration Test</h1>
        <p style="margin: 10px 0 0 0;">This is a test email from {{ config('app.name') }}</p>
    </div>
    
    <div style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0;">
        <h2 style="color: #667eea; margin-top: 0;">Email System Working!</h2>
        <p>If you're reading this, your email configuration is working correctly.</p>
        
        <div style="background: #f8fafc; padding: 15px; border-radius: 6px; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #4a5568;">Configuration Details:</h3>
            <ul style="margin: 0;">
                <li><strong>Mail Driver:</strong> {{ config('mail.default') }}</li>
                <li><strong>Mail Host:</strong> {{ config('mail.mailers.smtp.host') }}</li>
                <li><strong>Mail Port:</strong> {{ config('mail.mailers.smtp.port') }}</li>
                <li><strong>Encryption:</strong> {{ config('mail.mailers.smtp.encryption') ?? 'None' }}</li>
                <li><strong>From Address:</strong> {{ config('mail.from.address') }}</li>
                <li><strong>Queue Connection:</strong> {{ config('queue.default') }}</li>
            </ul>
        </div>
        
        <p style="color: #10b981; font-weight: bold;">✅ Email system is configured and working properly!</p>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 14px; color: #6b7280;">
            <p>Sent at: {{ now()->format('Y-m-d H:i:s T') }}</p>
            <p>From: {{ config('app.name') }} Email System</p>
        </div>
    </div>
</body>
</html>