<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $temple['name'] }} · Forgot Password OTP</title>
</head>
<body style="background-color: #fbf8f1; margin: 0; padding: 20px; font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #2d2520;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #ebdcc5; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
    <!-- Header banner -->
    <div style="background: linear-gradient(135deg, {{ $temple['accent_color'] }} 0%, {{ $temple['primary_color'] }} 50%, {{ $temple['dark_color'] }} 100%); padding: 30px; text-align: center;">
      <h1 style="margin: 0; color: #ffffff; font-family: 'Playfair Display', 'Georgia', serif; font-size: 24px; font-weight: 700; letter-spacing: 1px; text-shadow: 0 2px 4px rgba(0,0,0,0.15);">
        🛕 {{ $temple['name'] }}
      </h1>
      <p style="margin: 5px 0 0 0; color: #ffd8bd; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px;">
        Password Reset
      </p>
    </div>

    <!-- Body content -->
    <div style="padding: 40px 30px; text-align: center;">
      <h2 style="margin-top: 0; color: #17110a; font-size: 20px; font-weight: 600;">
        Vanakkam, {{ $name ?? 'Devotee' }}
      </h2>
      <p style="color: #52473c; font-size: 16px; line-height: 1.6; margin-bottom: 30px;">
        You have requested to reset your password. Use the following One-Time Password (OTP) code to verify your identity and complete the process:
      </p>

      <!-- OTP code box -->
      <div style="background-color: rgba(255, 111, 0, 0.05); border: 2px dashed {{ $temple['primary_color'] }}; border-radius: 12px; padding: 20px; margin: 30px auto; max-width: 250px; text-align: center;">
        <span style="font-family: monospace; font-size: 36px; font-weight: 800; color: {{ $temple['primary_color'] }}; letter-spacing: 6px; display: block; line-height: 1;">
          {{ $otp }}
        </span>
      </div>

      <p style="color: #7a6e63; font-size: 14px; margin-top: 30px;">
        This OTP is valid for <strong>{{ $expiry }}</strong>.
      </p>

      <div style="margin: 30px 0; border-top: 1px solid #f3ede2;"></div>

      <p style="color: #8a7b6e; font-size: 13px; line-height: 1.5; margin: 0; text-align: left;">
        ⚠️ <strong>Security Notice:</strong> Never share this OTP code with anyone. Temple staff will never ask for your passwords or reset verification codes. If you did not initiate this reset request, please ignore this email or contact support.
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
