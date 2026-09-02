<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $temple['name'] }} · Password Reset</title>
</head>
<body style="background-color: #fbf8f1; margin: 0; padding: 20px; font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #2d2520;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #ebdcc5; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
    <!-- Header banner -->
    <div style="background: linear-gradient(135deg, {{ $temple['accent_color'] }} 0%, {{ $temple['primary_color'] }} 50%, {{ $temple['dark_color'] }} 100%); padding: 30px; text-align: center;">
      <h1 style="margin: 0; color: #ffffff; font-family: 'Playfair Display', 'Georgia', serif; font-size: 24px; font-weight: 700; letter-spacing: 1px; text-shadow: 0 2px 4px rgba(0,0,0,0.15);">
        🛕 {{ $temple['name'] }}
      </h1>
      <p style="margin: 5px 0 0 0; color: #ffd8bd; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px;">
        Password Reset Requested
      </p>
    </div>

    <!-- Body content -->
    <div style="padding: 40px 30px; text-align: center;">
      <h2 style="margin-top: 0; color: #17110a; font-size: 20px; font-weight: 600;">
        Vanakkam, {{ $name ?? 'there' }}
      </h2>
      <p style="color: #52473c; font-size: 16px; line-height: 1.6; margin-bottom: 30px;">
        An administrator has requested a password reset for your account. Click the button below to set a new password.
      </p>

      <a href="{{ $url }}" style="display: inline-block; background-color: {{ $temple['primary_color'] }}; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 16px; padding: 14px 36px; border-radius: 40px; margin: 10px 0 30px;">
        Set New Password
      </a>

      <p style="color: #7a6e63; font-size: 14px; margin-top: 10px;">
        This link is valid for <strong>60 minutes</strong> and can only be used once.
      </p>

      <div style="margin: 30px 0; border-top: 1px solid #f3ede2;"></div>

      <p style="color: #8a7b6e; font-size: 13px; line-height: 1.5; margin: 0; text-align: left;">
        ⚠️ <strong>Security Notice:</strong> If you did not expect this email, you can safely ignore it — your password will not change unless you click the link above and set a new one.
      </p>
    </div>

    <!-- Footer -->
    <div style="background-color: #17110a; color: #ffd8bd; text-align: center; padding: 20px; font-size: 12px;">
      <p style="margin: 0 0 5px 0; font-weight: 500;">
        © {{ date('Y') }} {{ $temple['name'] }}. All rights reserved.
      </p>
      <p style="margin: 0; color: rgba(255, 255, 255, 0.45);">
        Secure Vedic Administration System
      </p>
    </div>
  </div>
</body>
</html>
