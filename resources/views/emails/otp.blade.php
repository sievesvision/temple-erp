<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $temple['name'] }} · Email Verification</title>
</head>
<body style="background-color: #f5f3ef; margin: 0; padding: 20px; font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #2d2520;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2ddd3;">
    <!-- Header -->
    <div style="background-color: {{ $temple['dark_color'] }}; padding: 24px 30px; text-align: left; border-bottom: 3px solid {{ $temple['primary_color'] }};">
      <h1 style="margin: 0; color: #ffffff; font-family: 'DM Sans', sans-serif; font-size: 19px; font-weight: 700;">
        {{ $temple['name'] }}
      </h1>
      <p style="margin: 6px 0 0 0; color: #d9d4c9; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">
        Email Verification
      </p>
    </div>

    <!-- Body content -->
    <div style="padding: 35px 30px;">
      <p style="color: #2d2520; font-size: 16px; line-height: 1.6; margin: 0 0 18px 0;">
        Vanakkam, {{ $name ?? 'Devotee' }}
      </p>
      <p style="color: #52473c; font-size: 15px; line-height: 1.6; margin-bottom: 26px;">
        Thank you for registering with {{ $temple['name'] }}. To complete your registration, please use the verification code below.
      </p>

      <!-- OTP code -->
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e2ddd3; border-collapse: collapse; margin: 0 0 22px 0;">
        <tr>
          <td style="padding: 20px 16px; text-align: center;">
            <span style="font-family: 'Courier New', monospace; font-size: 32px; font-weight: 700; color: {{ $temple['dark_color'] }}; letter-spacing: 8px;">{{ $otp }}</span>
          </td>
        </tr>
      </table>

      <p style="color: #52473c; font-size: 14px; line-height: 1.6; margin: 0 0 22px 0;">
        This code is valid for <strong>{{ $expiry }}</strong>.
      </p>

      <div style="border-top: 1px solid #e2ddd3; margin: 22px 0;"></div>

      <p style="color: #7b6b5a; font-size: 13px; line-height: 1.6; margin: 0;">
        <strong>Security notice:</strong> do not share this code with anyone. Temple officials will never ask for your password or verification code. If you did not request this, please ignore this email.
      </p>
    </div>

    <!-- Footer -->
    <div style="background-color: #f5f3ef; color: #7b6b5a; text-align: left; padding: 16px 30px; font-size: 12px; border-top: 1px solid #e2ddd3;">
      © {{ date('Y') }} {{ $temple['name'] }}. All rights reserved.
    </div>
  </div>
</body>
</html>
